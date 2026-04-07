<?php

namespace ComplyanceSDK\Models;

use ComplyanceSDK\Exceptions\SDKException;
use ComplyanceSDK\Models\CircuitBreaker;
use ComplyanceSDK\Models\CircuitBreakerConfig;
use ComplyanceSDK\Models\ErrorDetail;

class PersistentQueueManager
{
    private const QUEUE_DIR = 'queue';
    private const PENDING_DIR = 'pending';
    private const PROCESSING_DIR = 'processing';
    private const SUCCESS_DIR = 'success';
    private const FAILED_DIR = 'failed';

    private $apiKey;
    private $local;
    private $queueBasePath;
    private $circuitBreaker;
    private $isRunning = false;
    private $processingLock;
    private $lockHandles = [];
    private $scheduler;

    public function __construct($apiKey, $local = false, $circuitBreaker = null)
    {
        $this->apiKey = $apiKey;
        $this->local = $local;
        $this->queueBasePath = dirname(dirname(dirname(dirname(dirname(__DIR__))))) . '/' . self::QUEUE_DIR;
        $this->processingLock = new \stdClass();

        error_log("🔥 QUEUE: Constructor - Current __DIR__: " . __DIR__);
        error_log("🔥 QUEUE: Constructor - Resolved queue base path: " . $this->queueBasePath);
        error_log("🔥 QUEUE: Constructor - Current working directory: " . getcwd());

        // Initialize circuit breaker with 3 failure threshold and 1 minute timeout (matching Java)
        $this->circuitBreaker = $circuitBreaker ?? new CircuitBreaker(new CircuitBreakerConfig(3, 60));
        $this->circuitBreaker->setQueueManager($this);

        $this->initializeQueueDirectories();
        error_log("PersistentQueueManager initialized with queue directory: " . $this->queueBasePath);

        // Automatically start processing and retry any existing failed submissions
        $this->startProcessing();
        $this->retryFailedSubmissions();
    }

    private function initializeQueueDirectories()
    {
        error_log("Initializing queue directories at base path: " . $this->queueBasePath);

        $directories = [
            $this->queueBasePath . '/' . self::PENDING_DIR,
            $this->queueBasePath . '/' . self::PROCESSING_DIR,
            $this->queueBasePath . '/' . self::SUCCESS_DIR,
            $this->queueBasePath . '/' . self::FAILED_DIR
        ];

        foreach ($directories as $dir) {
            error_log("Checking directory: " . $dir);
            if (!is_dir($dir)) {
                error_log("Creating directory: " . $dir);
                if (!mkdir($dir, 0755, true)) {
                    throw new \RuntimeException("Failed to create queue directory: " . $dir);
                }
                error_log("Successfully created directory: " . $dir);
            } else {
                error_log("Directory already exists: " . $dir);
            }
        }

        // Clean up any stale files in processing directory
        $this->cleanupProcessingDirectory();

        error_log("Queue directories initialized and cleaned");

        // Verify directories exist and are writable
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                error_log("ERROR: Directory does not exist after creation: " . $dir);
            }
            if (!is_writable($dir)) {
                error_log("ERROR: Directory is not writable: " . $dir);
            }
        }
    }

    private function cleanupProcessingDirectory()
    {
        $processingDir = $this->queueBasePath . '/' . self::PROCESSING_DIR;
        $files = glob($processingDir . '/*.json');

        if (empty($files)) {
            return;
        }

        error_log("Found " . count($files) . " stale files in processing directory");

        foreach ($files as $file) {
            try {
                // Check if file is locked
                $lockFile = $file . '.lock';
                if (file_exists($lockFile)) {
                    $lockAge = time() - filemtime($lockFile);
                    if ($lockAge < 300) { // 5 minutes
                        error_log("Skipping locked file (age: {$lockAge}s): " . basename($file));
                        continue;
                    }
                    // Lock is stale, remove it
                    if (!@unlink($lockFile) && file_exists($lockFile)) {
                        error_log("Warning: failed to remove stale lock file: " . $lockFile);
                    }
                }

                // Check file age
                $fileAge = time() - filemtime($file);
                if ($fileAge > 3600) { // 1 hour
                    // File is too old, move to failed
                    $this->moveToDirectory($file, self::FAILED_DIR);
                    error_log("Moved stale file to failed (age: {$fileAge}s): " . basename($file));
                } else {
                    // File is recent, move back to pending
                    $this->moveToDirectory($file, self::PENDING_DIR);
                    error_log("Moved stale file back to pending (age: {$fileAge}s): " . basename($file));
                }

            } catch (\Exception $e) {
                error_log("Error cleaning up processing file " . basename($file) . ": " . $e->getMessage());
            }
        }
    }

    public function enqueue($submission, $error = null)
    {
        try {
            $pendingDir = $this->queueBasePath . '/' . self::PENDING_DIR;
            if (!is_dir($pendingDir)) {
                if (!mkdir($pendingDir, 0755, true)) {
                    throw new \RuntimeException("Failed to create pending directory: " . $pendingDir);
                }
            }

            // Handle both PayloadSubmission objects and raw maps.
            if (is_object($submission) && method_exists($submission, 'getPayload')) {
                $jsonPayload = (string)$submission->getPayload();
                $unifyRequestMap = json_decode($jsonPayload, true);
                if (!is_array($unifyRequestMap)) {
                    throw new \RuntimeException("Invalid JSON payload: " . json_last_error_msg());
                }
                $sourceId = $submission->getSource()->getName() . ":" . $submission->getSource()->getVersion();
                $countryObj = $submission->getCountry();
                $documentTypeObj = $submission->getDocumentType();
                $country = is_object($countryObj) ? $countryObj->getCode() : (string)$countryObj;
                $documentType = is_object($documentTypeObj) ? $documentTypeObj->getCode() : (string)$documentTypeObj;
            } else {
                $unifyRequestMap = is_array($submission) ? $submission : [];
                $jsonPayload = json_encode($unifyRequestMap);
                $sourceId = $unifyRequestMap['source']['id'] ?? ($unifyRequestMap['source']['identity'] ?? 'unknown');
                $country = $unifyRequestMap['country'] ?? 'unknown';
                $documentType = $unifyRequestMap['documentType'] ?? 'unknown';
            }

            if (!is_array($unifyRequestMap) || empty($unifyRequestMap)) {
                throw new \RuntimeException("Cannot enqueue empty payload");
            }

            $queueItemId = $this->extractQueueItemId($unifyRequestMap);
            $requestId = $this->extractRequestId($unifyRequestMap);
            $fileName = $this->generateFileName($queueItemId, $sourceId, $country, $documentType);
            $filePath = $pendingDir . '/' . $fileName;

            $existing = $this->findExistingFileByQueueItemId($queueItemId);
            if ($existing !== null) {
                $existingDir = basename(dirname($existing));
                if ($existingDir === self::PENDING_DIR || $existingDir === self::PROCESSING_DIR) {
                    error_log("Queue item already active in {$existingDir} for queueItemId={$queueItemId}, skipping enqueue");
                    return;
                }
                if ($existingDir === self::FAILED_DIR || $existingDir === self::SUCCESS_DIR) {
                    @unlink($existing);
                }
            }

            $nowIso = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM);
            $record = new PersistentSubmissionRecord(
                $queueItemId,
                $requestId,
                0,
                $nowIso,
                null,
                null,
                null,
                null,
                $unifyRequestMap,
                $sourceId,
                (string)$country,
                (string)$documentType,
                $nowIso,
                (int)(microtime(true) * 1000)
            );

            if ($error !== null) {
                $record->setLastErrorCode((string)$error->getCode());
            }

            $recordJson = json_encode($record->toArray(), JSON_PRETTY_PRINT);
            if ($recordJson === false) {
                throw new \RuntimeException("Failed to encode record to JSON: " . json_last_error_msg());
            }

            $bytesWritten = file_put_contents($filePath, $recordJson);
            if ($bytesWritten === false) {
                $lastError = error_get_last();
                throw new \RuntimeException("Failed to write to file: " . $filePath . " - " . ($lastError ? $lastError['message'] : 'Unknown error'));
            }

            error_log("Enqueued submission: {$fileName} for source: {$sourceId}, country: {$country}");
            $this->startProcessing();
        } catch (\Exception $e) {
            error_log("Failed to enqueue submission to persistent storage: " . $e->getMessage());
            throw new \RuntimeException("Failed to persist submission", 0, $e);
        }
    }

    private function generateFileName($queueItemId, $sourceId, $country, $documentType)
    {
        $safeQueueItemId = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)$queueItemId);
        $safeSourceId = preg_replace('/[^a-zA-Z0-9]/', '_', (string)$sourceId);
        $safeCountry = preg_replace('/[^a-zA-Z0-9]/', '_', (string)$country);
        $safeDocumentType = preg_replace('/[^a-zA-Z0-9]/', '_', (string)$documentType);
        return sprintf("%s__%s__%s__%s.json", $safeQueueItemId, $safeSourceId, $safeCountry, $safeDocumentType);
    }

    private function extractQueueItemId(array $requestMap)
    {
        $requestId = $this->extractRequestId($requestMap);
        if ($requestId !== null && trim($requestId) !== '') {
            return $requestId;
        }

        $documentId = $this->extractDocumentIdFromMap($requestMap);
        if ($documentId !== null && trim($documentId) !== '') {
            return $documentId;
        }

        return 'queue_' . (int)(microtime(true) * 1000);
    }

    private function extractRequestId(array $requestMap): ?string
    {
        $value = $requestMap['requestId'] ?? ($requestMap['request_id'] ?? null);
        return $value === null ? null : (string)$value;
    }

    private function extractDocumentIdFromMap(array $requestMap): ?string
    {
        try {
            if (isset($requestMap['payload']) && is_array($requestMap['payload'])
                && isset($requestMap['payload']['invoice_data']) && is_array($requestMap['payload']['invoice_data'])
                && isset($requestMap['payload']['invoice_data']['invoice_number'])) {
                return (string)$requestMap['payload']['invoice_data']['invoice_number'];
            }

            return "doc_" . (int)(microtime(true) * 1000);
        } catch (\Exception $e) {
            return "doc_" . (int)(microtime(true) * 1000);
        }
    }

    private function findExistingFileByQueueItemId($queueItemId)
    {
        if (!is_string($queueItemId) || trim($queueItemId) === '') {
            return null;
        }

        $prefix = preg_replace('/[^a-zA-Z0-9_-]/', '_', $queueItemId) . '__';
        foreach ([self::PENDING_DIR, self::PROCESSING_DIR, self::FAILED_DIR, self::SUCCESS_DIR] as $dirName) {
            $dir = $this->queueBasePath . '/' . $dirName;
            if (!is_dir($dir)) {
                continue;
            }
            $files = glob($dir . '/*.json') ?: [];
            foreach ($files as $file) {
                if (strpos(basename($file), $prefix) === 0) {
                    return $file;
                }
            }
        }

        return null;
    }

    public function startProcessing()
    {
        if (!$this->isRunning) {
            $this->isRunning = true;

            // Start background processing using a simple approach
            // In a real implementation, you might use ReactPHP, Swoole, or similar
            $this->scheduleBackgroundProcessing();

            error_log("Started persistent queue processing with 500ms interval");
        }
    }

    private function scheduleBackgroundProcessing()
    {
        // For CLI/sdk sample runs, avoid daemonizing a detached child process.
        // It can outlive the parent command and emit stale warnings later.
        if (PHP_SAPI === 'cli') {
            $this->processPendingSubmissions();
            return;
        }

        // Simple background processing using a loop
        // In production, you'd want to use a proper task scheduler or background worker
        if (function_exists('pcntl_fork')) {
            $pid = pcntl_fork();
            if ($pid == 0) {
                // Child process - run the background processor
                $this->runBackgroundProcessor();
                exit(0);
            }
        } else {
            // Fallback: process immediately (not ideal for production)
            $this->processPendingSubmissions();
        }
    }

    private function runBackgroundProcessor()
    {
        while ($this->isRunning) {
            $this->processPendingSubmissions();
            usleep(500000); // 500ms delay (matching Java implementation)
        }
    }

    public function processPendingSubmissionsNow()
    {
        error_log("Manually triggering processing of pending submissions");

        // Check circuit breaker state before manual processing
        if ($this->circuitBreaker->isOpen()) {
            $currentTime = time() * 1000;
            $timeSinceLastFailure = $currentTime - $this->circuitBreaker->getLastFailureTime();

            if ($timeSinceLastFailure < 60000) { // 1 minute = 60000ms
                $remainingTime = 60000 - $timeSinceLastFailure;
                error_log("🚫 Circuit breaker is OPEN - remaining time: {$remainingTime}ms (" . ($remainingTime / 1000) . " seconds). Manual processing skipped.");
                return;
            } else {
                error_log("✅ Circuit breaker timeout expired ({$timeSinceLastFailure}ms) - proceeding with manual processing");
            }
        } else {
            error_log("✅ Circuit breaker is CLOSED - proceeding with manual processing");
        }

        $this->processPendingSubmissions();
    }

    public function stopProcessing()
    {
        $this->isRunning = false;
        error_log("Stopped persistent queue processing");
    }

    private function processPendingSubmissions()
    {
        if (!$this->isRunning) {
            return;
        }

        // Enhanced locking mechanism with directory-specific locks
        $lockFile = $this->queueBasePath . '/processing.lock';
        if (!$this->acquireLock($lockFile)) {
            return; // Another process is already processing
        }

        try {
            // First check if there are any pending files
            $pendingDir = $this->queueBasePath . '/' . self::PENDING_DIR;
            $pendingFiles = glob($pendingDir . '/*.json');

            if (empty($pendingFiles)) {
                return;
            }

            error_log("Found " . count($pendingFiles) . " pending submissions in queue");

            // Check circuit breaker state before attempting to process
            if ($this->circuitBreaker->isOpen()) {
                $currentTime = time() * 1000;
                $timeSinceLastFailure = $currentTime - $this->circuitBreaker->getLastFailureTime();

                // Wait for full 1 minute timeout before attempting to process
                if ($timeSinceLastFailure < 60000) { // 1 minute = 60000ms
                    $remainingTime = 60000 - $timeSinceLastFailure;
                    error_log("Circuit breaker is OPEN - " . ($remainingTime / 1000) . " seconds remaining. Queue has " . count($pendingFiles) . " items waiting.");
                    $this->releaseLock($lockFile);
                    return;
                } else {
                    error_log("Circuit breaker timeout expired - attempting to process " . count($pendingFiles) . " queued items");
                }
            }

            // Process each file in the queue with individual file locks
            foreach ($pendingFiles as $filePath) {
                $fileLock = $filePath . '.lock';

                // Try to acquire lock for this specific file
                if (!$this->acquireLock($fileLock)) {
                    error_log("File is being processed by another thread: " . basename($filePath));
                    continue;
                }

                try {
                    // Check if file still exists before processing
                    if (file_exists($filePath)) {
                        try {
                            $recordData = json_decode((string)file_get_contents($filePath), true);
                            if (is_array($recordData) && isset($recordData['nextRetryAt']) && is_string($recordData['nextRetryAt'])) {
                                $nextRetryAt = strtotime($recordData['nextRetryAt']);
                                if ($nextRetryAt !== false && $nextRetryAt > time()) {
                                    continue;
                                }
                            }
                            $this->processSubmissionFile($filePath);
                        } catch (\Exception $e) {
                            error_log("Failed to process queued submission $filePath: " . $e->getMessage());
                            // Continue processing other submissions
                        }
                    }
                } finally {
                    $this->releaseLock($fileLock);
                }
            }

        } finally {
            $this->releaseLock($lockFile);
        }
    }

    private function acquireLock($lockFile)
    {
        try {
            $lockHandle = fopen($lockFile, 'w');
            if ($lockHandle === false) {
                error_log("Failed to create lock file: $lockFile");
                return false;
            }

            if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
                fclose($lockHandle);
                return false;
            }

            // Store handle for release
            $this->lockHandles[$lockFile] = $lockHandle;
            return true;

        } catch (\Exception $e) {
            error_log("Error acquiring lock: " . $e->getMessage());
            return false;
        }
    }

    private function releaseLock($lockFile)
    {
        try {
            if (isset($this->lockHandles[$lockFile])) {
                $handle = $this->lockHandles[$lockFile];
                flock($handle, LOCK_UN);
                fclose($handle);
                unset($this->lockHandles[$lockFile]);

                if (file_exists($lockFile) && !@unlink($lockFile) && file_exists($lockFile)) {
                    error_log("Warning: failed to remove lock file: " . $lockFile);
                }
            }
        } catch (\Exception $e) {
            error_log("Error releasing lock: " . $e->getMessage());
        }
    }

    private function moveToDirectory($filePath, $targetDir)
    {
        $fileName = basename($filePath);
        $targetPath = $this->queueBasePath . '/' . $targetDir . '/' . $fileName;

        // Ensure target directory exists
        $targetDirPath = $this->queueBasePath . '/' . $targetDir;
        if (!is_dir($targetDirPath)) {
            mkdir($targetDirPath, 0755, true);
        }

        // If target file exists, append timestamp to make unique
        if (file_exists($targetPath)) {
            $pathInfo = pathinfo($fileName);
            $targetPath = $this->queueBasePath . '/' . $targetDir . '/' . 
                         $pathInfo['filename'] . '_' . time() . '.' . $pathInfo['extension'];
        }

        if (!rename($filePath, $targetPath)) {
            throw new \RuntimeException("Failed to move file from $filePath to $targetPath");
        }

        error_log("Moved file to $targetDir: " . basename($targetPath));
        return $targetPath;
    }

    private function processSubmissionFile($filePath)
    {
        try {
            $fileContents = file_get_contents($filePath);
            $recordData = json_decode((string)$fileContents, true);
            if ($recordData === null) {
                throw new \RuntimeException("Invalid JSON in submission file: " . basename($filePath));
            }

            $record = PersistentSubmissionRecord::fromArray($recordData);
            $record->setAttemptCount($record->getAttemptCount() + 1);
            $record->setLastAttemptAt((new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM));

            // Move to processing directory with proper locking
            $processingPath = $this->moveToDirectory($filePath, self::PROCESSING_DIR);
            error_log("Processing queued submission: " . basename($processingPath) . " (queueItemId=" . $record->getQueueItemId() . ")");

            try {
                $unifyRequestData = $record->getPayload();
                $unifyRequest = $this->buildUnifyRequestFromArray($unifyRequestData);

                $responseRaw = \ComplyanceSDK\GETSUnifySDK::pushToUnifyRequest($unifyRequest);
                $response = is_string($responseRaw) ? json_decode($responseRaw, true) : (is_array($responseRaw) ? $responseRaw : []);

                if ($this->isSuccessfulResponse($response)) {
                    $record->setLastErrorCode(null);
                    $record->setLastHttpStatus(null);
                    $record->setNextRetryAt(null);
                    $this->writeRecordToPath($processingPath, $record);
                    $this->moveToDirectory($processingPath, self::SUCCESS_DIR);
                    error_log("✅ Queue: Successfully processed: " . basename($processingPath));
                    return;
                }

                $record->setLastErrorCode($this->extractErrorCodeFromResponse($response) ?? 'PROCESSING_ERROR');
                $record->setLastHttpStatus($this->extractHttpStatusFromResponse($response));
                $record->setNextRetryAt((new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+30 seconds')->format(DATE_ATOM));
                $this->writeRecordToPath($processingPath, $record);
                $this->moveToDirectory($processingPath, self::FAILED_DIR);
                error_log("Moved non-success queued submission to failed: " . basename($processingPath));
                return;

            } catch (\Exception $e) {
                error_log("Failed to process queued submission: " . $e->getMessage());
                $record->setLastErrorCode($this->extractErrorCodeFromException($e));
                $record->setLastHttpStatus($this->extractHttpStatusFromException($e));
                $record->setNextRetryAt((new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+30 seconds')->format(DATE_ATOM));
                if (file_exists($processingPath)) {
                    $this->writeRecordToPath($processingPath, $record);
                    $this->moveToDirectory($processingPath, self::FAILED_DIR);
                }
                throw $e;
            }

        } catch (\Exception $e) {
            error_log("Error processing submission: " . basename($filePath) . " - " . $e->getMessage());
            throw $e;
        }
    }

    private function buildUnifyRequestFromArray(array $data)
    {
        $request = new \ComplyanceSDK\UnifyRequest();
        $request->setSource($data['source'] ?? []);
        $request->setDocumentType($data['documentType'] ?? null);
        $request->setDocumentTypeString((string)($data['documentType'] ?? ($data['documentTypeString'] ?? 'tax_invoice')));
        $request->setDocumentTypeV2(isset($data['documentType']) && is_array($data['documentType']) ? $data['documentType'] : ($data['documentTypeV2'] ?? null));
        $request->setCountry((string)($data['country'] ?? ''));
        $request->setOperation($data['operation'] ?? null);
        $request->setMode($data['mode'] ?? null);
        $request->setPurpose($data['purpose'] ?? null);
        $request->setPayload(isset($data['payload']) && is_array($data['payload']) ? $data['payload'] : []);
        $request->setDestinations(isset($data['destinations']) && is_array($data['destinations']) ? $data['destinations'] : []);
        $request->setApiKey((string)($data['apiKey'] ?? $this->apiKey));
        $request->setRequestId((string)($data['requestId'] ?? ('req_' . time() . '_' . mt_rand())));
        $request->setTimestamp((string)($data['timestamp'] ?? date('c')));
        $request->setEnv((string)($data['env'] ?? 'sandbox'));
        $request->setCorrelationId(isset($data['correlationId']) ? (string)$data['correlationId'] : null);
        return $request;
    }

    private function writeRecordToPath($path, PersistentSubmissionRecord $record): void
    {
        $json = json_encode($record->toArray(), JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new \RuntimeException("Failed to encode queue record: " . json_last_error_msg());
        }
        if (file_put_contents($path, $json) === false) {
            throw new \RuntimeException("Failed to write queue record to: " . $path);
        }
    }

    private function extractErrorCodeFromResponse($response)
    {
        if (!is_array($response)) {
            return null;
        }
        if (isset($response['error']['code'])) {
            return (string)$response['error']['code'];
        }
        return null;
    }

    private function extractHttpStatusFromResponse($response)
    {
        if (!is_array($response)) {
            return null;
        }
        if (isset($response['error']['context']['httpStatus'])) {
            return (int)$response['error']['context']['httpStatus'];
        }
        if (isset($response['metadata']['httpStatus'])) {
            return (int)$response['metadata']['httpStatus'];
        }
        return null;
    }

    private function extractErrorCodeFromException(\Exception $e)
    {
        if ($e instanceof SDKException && $e->getErrorDetail() !== null) {
            return (string)$e->getErrorDetail()->getCode();
        }
        return 'PROCESSING_ERROR';
    }

    private function extractHttpStatusFromException(\Exception $e)
    {
        if ($e instanceof SDKException && $e->getErrorDetail() !== null) {
            $status = $e->getErrorDetail()->getContextValue('httpStatus');
            if ($status !== null) {
                return (int)$status;
            }
        }
        return null;
    }

    private function logResponseDetails($response)
    {
        error_log("Queue: API Response - Status: " . ($response['status'] ?? 'NULL'));

        if (isset($response['data']['submission'])) {
            $submission = $response['data']['submission'];
            error_log("Queue: Submission - ID: " . ($submission['submissionId'] ?? 'unknown') . 
                     ", Status: " . ($submission['status'] ?? 'unknown') . 
                     ", Country: " . ($submission['country'] ?? 'unknown'));
        }

        if (isset($response['error'])) {
            error_log("Queue: Error Details - " .
                     "Code: " . ($response['error']['code'] ?? 'unknown') . ", " .
                     "Message: " . ($response['error']['message'] ?? 'unknown') . ", " .
                     "Suggestion: " . ($response['error']['suggestion'] ?? 'unknown'));
        }
    }

    private function isSuccessfulResponse($response)
    {
        // Check top-level status
        if (isset($response['status']) && $response['status'] === 'success') {
            return true;
        }

        // Check submission status
        if (isset($response['data']['submission'])) {
            $submission = $response['data']['submission'];
            if (($submission['accepted'] ?? false) || 
                strtolower($submission['status'] ?? '') === 'accepted') {
                return true;
            }
        }

        // Check document status
        if (isset($response['data']['document'])) {
            $document = $response['data']['document'];
            if (strtolower($document['status'] ?? '') === 'success') {
                return true;
            }
        }

        return false;
    }

    public function getQueueStatus()
    {
        try {
            $pendingCount = count(glob($this->queueBasePath . '/' . self::PENDING_DIR . '/*.json'));
            $processingCount = count(glob($this->queueBasePath . '/' . self::PROCESSING_DIR . '/*.json'));
            $failedCount = count(glob($this->queueBasePath . '/' . self::FAILED_DIR . '/*.json'));
            $successCount = count(glob($this->queueBasePath . '/' . self::SUCCESS_DIR . '/*.json'));

            $status = new QueueStatus($pendingCount, $processingCount, $failedCount, $successCount, $this->isRunning);
            error_log("Queue Status: " . $status);
            return $status;

        } catch (\Exception $e) {
            error_log("Failed to get queue status: " . $e->getMessage());
            return new QueueStatus(0, 0, 0, 0, false);
        }
    }

    public function retryFailedSubmissions()
    {
        try {
            $failedDir = $this->queueBasePath . '/' . self::FAILED_DIR;
            $failedFiles = glob($failedDir . '/*.json');

            if (empty($failedFiles)) {
                error_log("No failed submissions to retry");
                return;
            }

            error_log("Found " . count($failedFiles) . " failed submissions to retry");

            foreach ($failedFiles as $filePath) {
                try {
                    $recordData = json_decode((string)file_get_contents($filePath), true);
                    $record = is_array($recordData) ? PersistentSubmissionRecord::fromArray($recordData) : null;
                    $queueItemId = $record ? $record->getQueueItemId() : null;
                    $existing = $queueItemId ? $this->findExistingFileByQueueItemId($queueItemId) : null;
                    if ($existing !== null && realpath($existing) !== realpath($filePath)) {
                        $existingDir = basename(dirname($existing));
                        if ($existingDir === self::PENDING_DIR || $existingDir === self::PROCESSING_DIR) {
                            @unlink($filePath);
                            continue;
                        }
                    }
                    $this->moveToDirectory($filePath, self::PENDING_DIR);
                    error_log("Moved failed submission back to pending for retry: " . basename($filePath));
                } catch (\Exception $e) {
                    error_log("Failed to move submission " . basename($filePath) . " to pending: " . $e->getMessage());
                }
            }

            // Start processing if not already running
            $this->startProcessing();

        } catch (\Exception $e) {
            error_log("Failed to retry failed submissions: " . $e->getMessage());
        }
    }

    /**
     * Retry a specific failed submission by queue item id (filename without .json).
     *
     * @param string $queueItemId
     * @return bool
     */
    public function retryFailedSubmission($queueItemId)
    {
        if (!is_string($queueItemId) || trim($queueItemId) === '') {
            return false;
        }

        $queueItemId = trim($queueItemId);
        $failedPath = $this->queueBasePath . '/' . self::FAILED_DIR . '/' . $queueItemId . '.json';
        if (!file_exists($failedPath)) {
            // Fallback 1: user may pass raw filename including extension.
            $failedPath = $this->queueBasePath . '/' . self::FAILED_DIR . '/' . $queueItemId;
        }
        if (!file_exists($failedPath)) {
            // Fallback 2: resolve by queueItemId prefix in failed directory.
            $safePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '_', $queueItemId) . '__';
            $candidates = glob($this->queueBasePath . '/' . self::FAILED_DIR . '/' . $safePrefix . '*.json') ?: [];
            if (!empty($candidates)) {
                $failedPath = $candidates[0];
            }
        }
        if (!file_exists($failedPath)) {
            return false;
        }

        try {
            $recordData = json_decode((string)file_get_contents($failedPath), true);
            $record = is_array($recordData) ? PersistentSubmissionRecord::fromArray($recordData) : null;
            $queueItemIdFromRecord = $record ? $record->getQueueItemId() : null;
            $existing = $queueItemIdFromRecord ? $this->findExistingFileByQueueItemId($queueItemIdFromRecord) : null;
            if ($existing !== null && realpath($existing) !== realpath($failedPath)) {
                $existingDir = basename(dirname($existing));
                if ($existingDir === self::PENDING_DIR || $existingDir === self::PROCESSING_DIR) {
                    @unlink($failedPath);
                    return true;
                }
            }
            $this->moveToDirectory($failedPath, self::PENDING_DIR);
            $this->startProcessing();
            return true;
        } catch (\Exception $e) {
            error_log("Failed to retry failed submission {$queueItemId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Wait until pending + processing become empty or timeout is reached.
     *
     * @param int $timeoutSeconds
     * @return bool
     */
    public function drainQueue($timeoutSeconds = 30)
    {
        $timeoutSeconds = max(0, (int)$timeoutSeconds);
        $deadline = time() + $timeoutSeconds;

        do {
            $status = $this->getQueueStatus();
            if ($status->getPendingCount() === 0 && $status->getProcessingCount() === 0) {
                return true;
            }

            usleep(250000); // 250ms
        } while (time() <= $deadline);

        $status = $this->getQueueStatus();
        return $status->getPendingCount() === 0 && $status->getProcessingCount() === 0;
    }

    /**
     * Detailed queue status payload for operational introspection.
     *
     * @return array
     */
    public function getQueueStatusDetailed()
    {
        $status = $this->getQueueStatus();
        return [
            'basePath' => $this->queueBasePath,
            'isRunning' => $status->isRunning(),
            'pendingCount' => $status->getPendingCount(),
            'processingCount' => $status->getProcessingCount(),
            'failedCount' => $status->getFailedCount(),
            'successCount' => $status->getSuccessCount(),
        ];
    }

    public function cleanupOldSuccessFiles($daysToKeep)
    {
        try {
            $daysToKeep = max(0, (int)$daysToKeep);
            $cutoff = time() - ($daysToKeep * 24 * 60 * 60);
            $successDir = $this->queueBasePath . '/' . self::SUCCESS_DIR;
            $files = glob($successDir . '/*.json') ?: [];

            foreach ($files as $file) {
                $mtime = @filemtime($file);
                if ($mtime !== false && $mtime < $cutoff) {
                    @unlink($file);
                }
            }
        } catch (\Exception $e) {
            error_log("Failed to cleanup old success files: " . $e->getMessage());
        }
    }

    public function cleanupDuplicateFiles()
    {
        try {
            $priorityByDir = [
                self::PROCESSING_DIR => 4,
                self::PENDING_DIR => 3,
                self::FAILED_DIR => 2,
                self::SUCCESS_DIR => 1,
            ];
            $bestByQueueItem = [];
            foreach ([self::PROCESSING_DIR, self::PENDING_DIR, self::FAILED_DIR, self::SUCCESS_DIR] as $dirName) {
                $dir = $this->queueBasePath . '/' . $dirName;
                $files = glob($dir . '/*.json') ?: [];
                foreach ($files as $file) {
                    $data = json_decode((string)file_get_contents($file), true);
                    if (!is_array($data)) {
                        continue;
                    }
                    $record = PersistentSubmissionRecord::fromArray($data);
                    $queueItemId = $record->getQueueItemId();
                    if (!is_string($queueItemId) || trim($queueItemId) === '') {
                        continue;
                    }

                    if (!isset($bestByQueueItem[$queueItemId])) {
                        $bestByQueueItem[$queueItemId] = ['file' => $file, 'dir' => $dirName];
                        continue;
                    }

                    $existing = $bestByQueueItem[$queueItemId]['file'];
                    $existingDir = $bestByQueueItem[$queueItemId]['dir'];
                    $existingTime = @filemtime($existing) ?: 0;
                    $currentTime = @filemtime($file) ?: 0;

                    $existingPriority = $priorityByDir[$existingDir] ?? 0;
                    $currentPriority = $priorityByDir[$dirName] ?? 0;
                    $preferCurrent = false;
                    if ($currentPriority > $existingPriority) {
                        $preferCurrent = true;
                    } elseif ($currentPriority === $existingPriority && $currentTime >= $existingTime) {
                        $preferCurrent = true;
                    }

                    if ($preferCurrent) {
                        @unlink($existing);
                        $bestByQueueItem[$queueItemId] = ['file' => $file, 'dir' => $dirName];
                    } else {
                        @unlink($file);
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Error during duplicate file cleanup: " . $e->getMessage());
        }
    }

    public function clearAllQueues()
    {
        try {
            error_log("Clearing all queue directories...");

            $directories = [self::PENDING_DIR, self::PROCESSING_DIR, self::SUCCESS_DIR, self::FAILED_DIR];
            foreach ($directories as $dirName) {
                $this->clearDirectory($dirName);
            }

            error_log("All queue directories cleared successfully");

        } catch (\Exception $e) {
            error_log("Error clearing queue directories: " . $e->getMessage());
            throw new \RuntimeException("Failed to clear queues", 0, $e);
        }
    }

    private function clearDirectory($dirName)
    {
        $dir = $this->queueBasePath . '/' . $dirName;
        if (is_dir($dir)) {
            $files = glob($dir . '/*.json');
            foreach ($files as $file) {
                if (!@unlink($file) && file_exists($file)) {
                    error_log("Warning: failed to delete file: " . basename($file));
                    continue;
                }
                error_log("Deleted file: " . basename($file));
            }
            error_log("Cleared " . count($files) . " files from " . $dirName);
        }
    }
}

class PersistentSubmissionRecord
{
    private $queueItemId;
    private $requestId;
    private $attemptCount;
    private $firstEnqueuedAt;
    private $lastAttemptAt;
    private $lastErrorCode;
    private $lastHttpStatus;
    private $nextRetryAt;
    private $payload;
    private $sourceId;
    private $country;
    private $documentType;
    private $enqueuedAt;
    private $timestamp;

    public function __construct(
        $queueItemId,
        $requestId,
        $attemptCount,
        $firstEnqueuedAt,
        $lastAttemptAt,
        $lastErrorCode,
        $lastHttpStatus,
        $nextRetryAt,
        $payload,
        $sourceId,
        $country,
        $documentType,
        $enqueuedAt,
        $timestamp
    )
    {
        $this->queueItemId = $queueItemId;
        $this->requestId = $requestId;
        $this->attemptCount = (int)$attemptCount;
        $this->firstEnqueuedAt = $firstEnqueuedAt;
        $this->lastAttemptAt = $lastAttemptAt;
        $this->lastErrorCode = $lastErrorCode;
        $this->lastHttpStatus = $lastHttpStatus;
        $this->nextRetryAt = $nextRetryAt;
        $this->payload = $payload;
        $this->sourceId = $sourceId;
        $this->country = $country;
        $this->documentType = $documentType;
        $this->enqueuedAt = $enqueuedAt;
        $this->timestamp = $timestamp;
    }

    // Getters
    public function getQueueItemId() { return $this->queueItemId; }
    public function getRequestId() { return $this->requestId; }
    public function getAttemptCount() { return (int)$this->attemptCount; }
    public function getFirstEnqueuedAt() { return $this->firstEnqueuedAt; }
    public function getLastAttemptAt() { return $this->lastAttemptAt; }
    public function getLastErrorCode() { return $this->lastErrorCode; }
    public function getLastHttpStatus() { return $this->lastHttpStatus; }
    public function getNextRetryAt() { return $this->nextRetryAt; }
    public function getPayload() { return $this->payload; }
    public function getSourceId() { return $this->sourceId; }
    public function getCountry() { return $this->country; }
    public function getDocumentType() { return $this->documentType; }
    public function getEnqueuedAt() { return $this->enqueuedAt; }
    public function getTimestamp() { return $this->timestamp; }

    // Setters
    public function setQueueItemId($queueItemId) { $this->queueItemId = $queueItemId; }
    public function setRequestId($requestId) { $this->requestId = $requestId; }
    public function setAttemptCount($attemptCount) { $this->attemptCount = (int)$attemptCount; }
    public function setFirstEnqueuedAt($firstEnqueuedAt) { $this->firstEnqueuedAt = $firstEnqueuedAt; }
    public function setLastAttemptAt($lastAttemptAt) { $this->lastAttemptAt = $lastAttemptAt; }
    public function setLastErrorCode($lastErrorCode) { $this->lastErrorCode = $lastErrorCode; }
    public function setLastHttpStatus($lastHttpStatus) { $this->lastHttpStatus = $lastHttpStatus; }
    public function setNextRetryAt($nextRetryAt) { $this->nextRetryAt = $nextRetryAt; }

    public function toArray()
    {
        return [
            'queueItemId' => $this->queueItemId,
            'requestId' => $this->requestId,
            'attemptCount' => (int)$this->attemptCount,
            'firstEnqueuedAt' => $this->firstEnqueuedAt,
            'lastAttemptAt' => $this->lastAttemptAt,
            'lastErrorCode' => $this->lastErrorCode,
            'lastHttpStatus' => $this->lastHttpStatus,
            'nextRetryAt' => $this->nextRetryAt,
            'payload' => $this->payload,
            'sourceId' => $this->sourceId,
            'country' => $this->country,
            'documentType' => $this->documentType,
            'enqueuedAt' => $this->enqueuedAt,
            'timestamp' => $this->timestamp,
        ];
    }

    public static function fromArray(array $data)
    {
        $payload = isset($data['payload']) && is_array($data['payload']) ? $data['payload'] : [];
        $sourceId = $data['sourceId'] ?? 'unknown';
        $country = $data['country'] ?? 'unknown';
        $documentType = $data['documentType'] ?? 'unknown';
        $enqueuedAt = $data['enqueuedAt'] ?? null;
        $timestamp = $data['timestamp'] ?? (int)(microtime(true) * 1000);

        // Backward compatibility with older records
        $queueItemId = $data['queueItemId'] ?? ($data['requestId'] ?? ('legacy_' . $timestamp));
        $requestId = $data['requestId'] ?? null;
        $attemptCount = $data['attemptCount'] ?? 0;
        $firstEnqueuedAt = $data['firstEnqueuedAt'] ?? $enqueuedAt;
        $lastAttemptAt = $data['lastAttemptAt'] ?? null;
        $lastErrorCode = $data['lastErrorCode'] ?? (isset($data['error']['code']) ? (string)$data['error']['code'] : null);
        $lastHttpStatus = $data['lastHttpStatus'] ?? null;
        $nextRetryAt = $data['nextRetryAt'] ?? null;

        return new self(
            $queueItemId,
            $requestId,
            $attemptCount,
            $firstEnqueuedAt,
            $lastAttemptAt,
            $lastErrorCode,
            $lastHttpStatus,
            $nextRetryAt,
            $payload,
            $sourceId,
            $country,
            $documentType,
            $enqueuedAt,
            $timestamp
        );
    }
}

class QueueStatus
{
    private $pendingCount;
    private $processingCount;
    private $failedCount;
    private $successCount;
    private $isRunning;

    public function __construct($pendingCount, $processingCount, $failedCount, $successCount, $isRunning)
    {
        $this->pendingCount = $pendingCount;
        $this->processingCount = $processingCount;
        $this->failedCount = $failedCount;
        $this->successCount = $successCount;
        $this->isRunning = $isRunning;
    }

    // Getters
    public function getPendingCount() { return $this->pendingCount; }
    public function getProcessingCount() { return $this->processingCount; }
    public function getFailedCount() { return $this->failedCount; }
    public function getSuccessCount() { return $this->successCount; }
    public function isRunning() { return $this->isRunning; }

    public function __toString()
    {
        return sprintf("QueueStatus{pending=%d, processing=%d, failed=%d, success=%d, running=%s}",
            $this->pendingCount, $this->processingCount, $this->failedCount, $this->successCount, $this->isRunning ? 'true' : 'false');
    }
}
