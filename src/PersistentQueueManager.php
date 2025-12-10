<?php

namespace Complyance\SDK;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Manages persistent queue for document submissions
 */
class PersistentQueueManager {
    use LoggerAwareTrait;

    private const QUEUE_DIR = 'complyance-queue';
    private const PENDING_DIR = 'pending';
    private const PROCESSING_DIR = 'processing';
    private const FAILED_DIR = 'failed';
    private const SUCCESS_DIR = 'success';

    private $apiKey;
    private $local;
    private $queueBasePath;
    private $isRunning = false;
    private $processingLock;
    private $circuitBreaker;

    /**
     * @param string $apiKey API key for authentication
     * @param bool $local Whether to use local environment
     * @param CircuitBreaker|null $circuitBreaker Optional shared circuit breaker
     */
    public function __construct(string $apiKey, bool $local, ?CircuitBreaker $circuitBreaker = null) {
        $this->apiKey = $apiKey;
        $this->local = $local;
        $this->queueBasePath = $this->getQueueBasePath();
        $this->logger = new NullLogger();
        $this->processingLock = new \SplMutex();

        // Initialize circuit breaker with 3 failure threshold and 1 minute timeout
        $this->circuitBreaker = $circuitBreaker ?? new CircuitBreaker([
            'failureThreshold' => 3,
            'resetTimeout' => 60
        ]);

        $this->initializeQueueDirectories();
        $this->logger->info("PersistentQueueManager initialized with queue directory: {$this->queueBasePath}");

        // Automatically start processing and retry any existing failed submissions
        $this->startProcessing();
        $this->retryFailedSubmissions();
    }

    /**
     * Get base path for queue directories
     */
    private function getQueueBasePath(): string {
        $homeDir = getenv('HOME') ?: getenv('USERPROFILE');
        return $homeDir . DIRECTORY_SEPARATOR . self::QUEUE_DIR;
    }

    /**
     * Initialize queue directory structure
     */
    private function initializeQueueDirectories(): void {
        $dirs = [
            self::PENDING_DIR,
            self::PROCESSING_DIR,
            self::FAILED_DIR,
            self::SUCCESS_DIR
        ];

        foreach ($dirs as $dir) {
            $path = $this->queueBasePath . DIRECTORY_SEPARATOR . $dir;
            if (!file_exists($path)) {
                if (!mkdir($path, 0777, true)) {
                    throw new \RuntimeException("Failed to create queue directory: {$path}");
                }
            }
        }

        $this->logger->debug("Queue directories initialized");
    }

    /**
     * Enqueue a submission for processing
     * 
     * @param PayloadSubmission $submission Submission to enqueue
     */
    public function enqueue(PayloadSubmission $submission): void {
        try {
            $fileName = $this->generateFileName($submission);
            $filePath = $this->queueBasePath . DIRECTORY_SEPARATOR . self::PENDING_DIR . DIRECTORY_SEPARATOR . $fileName;

            // Check if file already exists (same document ID)
            if (file_exists($filePath)) {
                $this->logger->info("Document already exists in queue: {$fileName}. Skipping duplicate submission.");
                return;
            }

            // Parse the UnifyRequest JSON string
            $jsonPayload = $submission->getPayload();
            $this->logger->debug("Queue: Received payload with length: " . strlen($jsonPayload) . " characters");

            // Verify the payload is not empty
            if (trim($jsonPayload) === '' || $jsonPayload === '{}') {
                $this->logger->error("🔥 QUEUE: ERROR - Received empty or invalid payload: '{$jsonPayload}'");
                throw new \RuntimeException("Cannot enqueue empty payload");
            }

            // Parse the UnifyRequest JSON string to a proper JSON object
            $unifyRequestMap = json_decode($jsonPayload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException("Failed to parse payload JSON: " . json_last_error_msg());
            }

            // Create submission record
            $record = [
                'payload' => $unifyRequestMap,
                'sourceId' => $submission->getSource()->getName() . ':' . $submission->getSource()->getVersion(),
                'country' => $submission->getCountry()->toString(),
                'documentType' => $submission->getDocumentType()->toString(),
                'enqueuedAt' => date('c'),
                'timestamp' => time() * 1000 // Convert to milliseconds for consistency
            ];

            // Write to file
            if (!file_put_contents($filePath, json_encode($record, JSON_PRETTY_PRINT))) {
                throw new \RuntimeException("Failed to write queue file: {$filePath}");
            }

            $this->logger->info("Enqueued submission: {$fileName} for source: {$record['sourceId']}, country: {$record['country']}");

            // Start processing if not already running
            $this->startProcessing();

        } catch (\Exception $e) {
            $this->logger->error("Failed to enqueue submission to persistent storage: " . $e->getMessage());
            throw new \RuntimeException("Failed to persist submission: " . $e->getMessage());
        }
    }

    /**
     * Generate unique filename for submission
     */
    private function generateFileName(PayloadSubmission $submission): string {
        // Extract document ID from payload
        $documentId = $this->extractDocumentId($submission->getPayload());

        // Generate filename using source and document ID for unique reference
        $sourceId = str_replace([' ', '/', '\\', ':', ';'], '_', 
            $submission->getSource()->getName() . ':' . $submission->getSource()->getVersion());
        $country = $submission->getCountry()->toString();
        
        return sprintf("%s_%s_%s_%s.json",
            $sourceId,
            $documentId,
            $country,
            $submission->getDocumentType()->toString()
        );
    }

    /**
     * Extract document ID from payload JSON
     */
    private function extractDocumentId(string $payload): string {
        try {
            $data = json_decode($payload, true);
            if (isset($data['payload']['invoice_data']['invoice_number'])) {
                return $data['payload']['invoice_data']['invoice_number'];
            }
        } catch (\Exception $e) {
            $this->logger->warning("Failed to extract document ID from payload: " . $e->getMessage());
        }

        // Fallback to timestamp if no invoice number found
        return sprintf("doc_%d", time());
    }

    /**
     * Start processing queue items
     */
    public function startProcessing(): void {
        if (!$this->isRunning) {
            $this->isRunning = true;
            $this->logger->info("Started persistent queue processing");
            
            // Start background processing
            if (function_exists('pcntl_fork')) {
                $pid = pcntl_fork();
                if ($pid === -1) {
                    throw new \RuntimeException("Failed to start queue processing");
                } elseif ($pid === 0) {
                    // Child process
                    while ($this->isRunning) {
                        $this->processPendingSubmissions();
                        usleep(500000); // 500ms delay
                    }
                    exit(0);
                }
            } else {
                // No forking available, process in current thread
                $this->processPendingSubmissionsNow();
            }
        }
    }

    /**
     * Process pending submissions immediately
     */
    public function processPendingSubmissionsNow(): void {
        $this->logger->info("Manually triggering processing of pending submissions");

        // Check circuit breaker state before manual processing
        if ($this->circuitBreaker->isOpen()) {
            $currentTime = time();
            $timeSinceLastFailure = $currentTime - $this->circuitBreaker->getLastFailureTime();

            if ($timeSinceLastFailure < 60) { // 1 minute
                $remainingTime = 60 - $timeSinceLastFailure;
                $this->logger->info("🚫 Circuit breaker is OPEN - remaining time: {$remainingTime}s. Manual processing skipped.");
                return;
            } else {
                $this->logger->info("✅ Circuit breaker timeout expired ({$timeSinceLastFailure}s) - proceeding with manual processing");
            }
        } else {
            $this->logger->info("✅ Circuit breaker is CLOSED - proceeding with manual processing");
        }

        $this->processPendingSubmissions();
    }

    /**
     * Stop processing queue items
     */
    public function stopProcessing(): void {
        $this->isRunning = false;
        $this->logger->info("Stopped persistent queue processing");
    }

    /**
     * Process pending submissions in queue
     */
    private function processPendingSubmissions(): void {
        if (!$this->isRunning) {
            return;
        }

        if (!$this->processingLock->lock()) {
            return;
        }

        try {
            $pendingDir = $this->queueBasePath . DIRECTORY_SEPARATOR . self::PENDING_DIR;
            $files = glob($pendingDir . DIRECTORY_SEPARATOR . '*.json');

            if (empty($files)) {
                $this->logger->debug("No pending submissions to process");
                return;
            }

            $this->logger->debug("Found " . count($files) . " pending submissions in queue");

            // Check circuit breaker state before attempting to process
            if ($this->circuitBreaker->isOpen()) {
                $currentTime = time();
                $timeSinceLastFailure = $currentTime - $this->circuitBreaker->getLastFailureTime();

                if ($timeSinceLastFailure < 60) { // 1 minute
                    $remainingTime = 60 - $timeSinceLastFailure;
                    $this->logger->debug("Circuit breaker is OPEN - {$remainingTime} seconds remaining. Queue has " . 
                                       count($files) . " items waiting.");
                    return;
                } else {
                    $this->logger->debug("Circuit breaker timeout expired - attempting to process " . count($files) . " queued items");
                }
            }

            foreach ($files as $filePath) {
                if (file_exists($filePath)) {
                    try {
                        $this->processSubmissionFile($filePath);
                    } catch (\Exception $e) {
                        $this->logger->error("Failed to process queued submission {$filePath}: " . $e->getMessage());
                    }
                }
            }

        } catch (\Exception $e) {
            $this->logger->error("Error processing pending submissions: " . $e->getMessage());
        } finally {
            $this->processingLock->unlock();
        }
    }

    /**
     * Process a single submission file
     */
    private function processSubmissionFile(string $filePath): void {
        try {
            // Read submission record
            $record = json_decode(file_get_contents($filePath), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException("Failed to parse submission record: " . json_last_error_msg());
            }

            $fileName = basename($filePath);
            $processingPath = $this->queueBasePath . DIRECTORY_SEPARATOR . self::PROCESSING_DIR . DIRECTORY_SEPARATOR . $fileName;

            // Move to processing directory
            if (!rename($filePath, $processingPath)) {
                throw new \RuntimeException("Failed to move file to processing directory");
            }

            $this->logger->debug("Processing submission: {$fileName} for source: {$record['sourceId']}");

            try {
                // Convert stored payload back to UnifyRequest
                $unifyRequest = new UnifyRequest($record['payload']);
                
                // Use the SDK's pushToUnify method with circuit breaker
                $response = $this->circuitBreaker->execute(function() use ($unifyRequest) {
                    return GETSUnifySDK::pushToUnify($unifyRequest);
                });

                // Check for success
                $isSuccess = false;
                if ($response !== null) {
                    $isSuccess = $response->isSuccess();

                    // Check submission status
                    if ($response->getData() !== null && $response->getData()->getSubmission() !== null) {
                        $submission = $response->getData()->getSubmission();
                        $isSuccess = $isSuccess || 
                                   $submission->isAccepted() || 
                                   strtolower($submission->getStatus()) === 'accepted';
                    }

                    // Check document status
                    if ($response->getData() !== null && $response->getData()->getDocument() !== null) {
                        $document = $response->getData()->getDocument();
                        $isSuccess = $isSuccess || strtolower($document->getStatus()) === 'success';
                    }
                }

                if ($isSuccess) {
                    $this->logger->info("Queue: SUCCESS - Removing file from queue: {$fileName}");
                    unlink($processingPath);
                    return;
                } else {
                    $status = $response !== null ? $response->getStatus() : 'null';
                    $this->logger->warning("Queue: NON-SUCCESS - Moving to failed directory. Status: '{$status}'");
                    throw new \RuntimeException("API returned non-success status: {$status}");
                }

            } catch (\Exception $e) {
                $this->logger->error("Failed to send queued submission via pushToUnify: " . $e->getMessage());
                
                $failedPath = $this->queueBasePath . DIRECTORY_SEPARATOR . self::FAILED_DIR . DIRECTORY_SEPARATOR . $fileName;
                
                if (file_exists($failedPath)) {
                    unlink($processingPath);
                } else {
                    rename($processingPath, $failedPath);
                }
                
                throw $e;
            }

        } catch (\Exception $e) {
            $this->logger->warning("Failed to process submission: " . basename($filePath) . " - Error: " . $e->getMessage());

            $fileName = basename($filePath);
            $processingPath = $this->queueBasePath . DIRECTORY_SEPARATOR . self::PROCESSING_DIR . DIRECTORY_SEPARATOR . $fileName;
            $failedPath = $this->queueBasePath . DIRECTORY_SEPARATOR . self::FAILED_DIR . DIRECTORY_SEPARATOR . $fileName;

            // Move to failed directory
            if (file_exists($processingPath)) {
                if (file_exists($failedPath)) {
                    unlink($processingPath);
                } else {
                    rename($processingPath, $failedPath);
                }
            } elseif (file_exists($filePath)) {
                if (file_exists($failedPath)) {
                    unlink($filePath);
                } else {
                    rename($filePath, $failedPath);
                }
            }
        }
    }

    /**
     * Get current queue status
     * 
     * @return array Queue status information
     */
    public function getQueueStatus(): array {
        try {
            $pendingCount = count(glob($this->queueBasePath . DIRECTORY_SEPARATOR . self::PENDING_DIR . DIRECTORY_SEPARATOR . '*.json'));
            $processingCount = count(glob($this->queueBasePath . DIRECTORY_SEPARATOR . self::PROCESSING_DIR . DIRECTORY_SEPARATOR . '*.json'));
            $failedCount = count(glob($this->queueBasePath . DIRECTORY_SEPARATOR . self::FAILED_DIR . DIRECTORY_SEPARATOR . '*.json'));
            $successCount = count(glob($this->queueBasePath . DIRECTORY_SEPARATOR . self::SUCCESS_DIR . DIRECTORY_SEPARATOR . '*.json'));

            return [
                'pendingCount' => $pendingCount,
                'processingCount' => $processingCount,
                'failedCount' => $failedCount,
                'successCount' => $successCount,
                'isRunning' => $this->isRunning
            ];

        } catch (\Exception $e) {
            $this->logger->error("Failed to get queue status: " . $e->getMessage());
            return [
                'pendingCount' => 0,
                'processingCount' => 0,
                'failedCount' => 0,
                'successCount' => 0,
                'isRunning' => false
            ];
        }
    }

    /**
     * Retry failed submissions
     */
    public function retryFailedSubmissions(): void {
        try {
            $failedDir = $this->queueBasePath . DIRECTORY_SEPARATOR . self::FAILED_DIR;
            $files = glob($failedDir . DIRECTORY_SEPARATOR . '*.json');

            if (empty($files)) {
                $this->logger->info("No failed submissions to retry");
                return;
            }

            $this->logger->info("Retrying " . count($files) . " failed submissions");

            foreach ($files as $filePath) {
                $fileName = basename($filePath);
                $pendingPath = $this->queueBasePath . DIRECTORY_SEPARATOR . self::PENDING_DIR . DIRECTORY_SEPARATOR . $fileName;
                rename($filePath, $pendingPath);
                $this->logger->debug("Moved failed submission back to pending: {$fileName}");
            }

        } catch (\Exception $e) {
            $this->logger->error("Failed to retry failed submissions: " . $e->getMessage());
        }
    }

    /**
     * Clean up old success files
     * 
     * @param int $daysToKeep Number of days to keep success files
     */
    public function cleanupOldSuccessFiles(int $daysToKeep): void {
        try {
            $successDir = $this->queueBasePath . DIRECTORY_SEPARATOR . self::SUCCESS_DIR;
            $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);

            $files = glob($successDir . DIRECTORY_SEPARATOR . '*.json');
            $oldFiles = array_filter($files, function($file) use ($cutoffTime) {
                return filemtime($file) < $cutoffTime;
            });

            foreach ($oldFiles as $file) {
                unlink($file);
                $this->logger->debug("Cleaned up old success file: " . basename($file));
            }

            if (!empty($oldFiles)) {
                $this->logger->info("Cleaned up " . count($oldFiles) . " old success files");
            }

        } catch (\Exception $e) {
            $this->logger->error("Failed to cleanup old success files: " . $e->getMessage());
        }
    }

    /**
     * Clear all files from the queue (emergency cleanup)
     */
    public function clearAllQueues(): void {
        try {
            $this->logger->info("Clearing all queue directories...");

            $this->clearDirectory(self::PENDING_DIR);
            $this->clearDirectory(self::PROCESSING_DIR);
            $this->clearDirectory(self::FAILED_DIR);
            $this->clearDirectory(self::SUCCESS_DIR);

            $this->logger->info("All queue directories cleared successfully");

        } catch (\Exception $e) {
            $this->logger->error("Error clearing queue directories: " . $e->getMessage());
            throw new \RuntimeException("Failed to clear queues: " . $e->getMessage());
        }
    }

    /**
     * Clear all files from a specific queue directory
     */
    private function clearDirectory(string $dirName): void {
        $dir = $this->queueBasePath . DIRECTORY_SEPARATOR . $dirName;
        if (file_exists($dir)) {
            $files = glob($dir . DIRECTORY_SEPARATOR . '*.json');
            foreach ($files as $file) {
                unlink($file);
                $this->logger->debug("Deleted file: " . basename($file));
            }
            $this->logger->info("Cleared " . count($files) . " files from {$dirName}");
        }
    }

    /**
     * Clean up duplicate files across queue directories
     */
    public function cleanupDuplicateFiles(): void {
        try {
            $this->logger->info("Cleaning up duplicate files across queue directories...");

            $fileMap = [];
            $dirs = [self::PENDING_DIR, self::PROCESSING_DIR, self::FAILED_DIR, self::SUCCESS_DIR];

            foreach ($dirs as $dirName) {
                $dir = $this->queueBasePath . DIRECTORY_SEPARATOR . $dirName;
                if (file_exists($dir)) {
                    $files = glob($dir . DIRECTORY_SEPARATOR . '*.json');
                    foreach ($files as $file) {
                        $fileName = basename($file);
                        if (isset($fileMap[$fileName])) {
                            // File exists in multiple directories, keep the one with latest modification time
                            $existingTime = filemtime($fileMap[$fileName]);
                            $currentTime = filemtime($file);

                            if ($currentTime > $existingTime) {
                                unlink($fileMap[$fileName]);
                                $fileMap[$fileName] = $file;
                                $this->logger->debug("Removed duplicate file (older): " . $fileMap[$fileName]);
                            } else {
                                unlink($file);
                                $this->logger->debug("Removed duplicate file (older): {$file}");
                            }
                        } else {
                            $fileMap[$fileName] = $file;
                        }
                    }
                }
            }

            $this->logger->info("Duplicate file cleanup completed");

        } catch (\Exception $e) {
            $this->logger->error("Error during duplicate file cleanup: " . $e->getMessage());
        }
    }
}
