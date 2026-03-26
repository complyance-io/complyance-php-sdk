<?php

namespace Complyance\ERP;

class ERPClient
{
    private string $apiKey;
    private string $baseUrl;
    private string $environment;
    private int $timeout;
    private const BASE_PATH = '/documents/erp-exports';
    private const DEFAULT_URL = 'https://api.complyance.io';

    public function __construct(
        string $apiKey,
        string $environment = 'production',
        ?string $baseUrl = null,
        int $timeout = 30000
    ) {
        $this->apiKey = $apiKey;
        $this->environment = $environment ?? 'production';
        $this->baseUrl = rtrim($baseUrl ?? self::DEFAULT_URL, '/');
        $this->timeout = $timeout > 0 ? $timeout : 30000;
    }

    public static function fromEnv(): self
    {
        $apiKey = getenv('COMPLYANCE_API_KEY') ?: ($_ENV['COMPLYANCE_API_KEY'] ?? null);
        if (!$apiKey) {
            throw new \InvalidArgumentException('COMPLYANCE_API_KEY environment variable is required');
        }

        $environment = getenv('COMPLYANCE_ENVIRONMENT') ?: ($_ENV['COMPLYANCE_ENVIRONMENT'] ?? 'production');
        $baseUrl = getenv('COMPLYANCE_BASE_URL') ?: ($_ENV['COMPLYANCE_BASE_URL'] ?? null);

        return new self($apiKey, $environment, $baseUrl);
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $url = $this->baseUrl . self::BASE_PATH . $path;
        
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('cURL error: ' . $error);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException("Request failed with status {$httpCode}: {$response}");
        }

        return json_decode($response, true) ?? [];
    }

    public function listJobs(?int $limit = null): array
    {
        $path = '/jobs?environment=' . $this->environment;
        if ($limit !== null) {
            $path .= '&limit=' . $limit;
        }
        $response = $this->request('GET', $path);
        return $response['jobs'] ?? [];
    }

    public function getJob(string $jobId): array
    {
        $response = $this->request('GET', "/jobs/{$jobId}?environment={$this->environment}");
        return $response['job'] ?? [];
    }

    public function getJobPayload(string $jobId): array
    {
        $response = $this->request('GET', "/jobs/{$jobId}/payload?environment={$this->environment}");
        return $response['payload'] ?? [];
    }

    public function acknowledge(string $jobId, string $status = 'success', ?string $error = null): array
    {
        $body = [
            'status' => $status,
            'environment' => $this->environment,
        ];
        if ($error !== null) {
            $body['error'] = $error;
        }
        return $this->request('POST', "/jobs/{$jobId}/ack", $body);
    }

    public function triggerManual(string $documentId): array
    {
        return $this->request('POST', '/jobs/trigger-manual', [
            'documentId' => $documentId,
            'environment' => $this->environment,
        ]);
    }

    public function getConfig(): array
    {
        return $this->request('GET', "/config?environment={$this->environment}");
    }

    public function testConnection(string $configId): array
    {
        return $this->request('POST', "/config/{$configId}/test", [
            'environment' => $this->environment,
        ]);
    }
}
