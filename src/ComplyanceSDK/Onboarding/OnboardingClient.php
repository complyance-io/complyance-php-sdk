<?php

namespace ComplyanceSDK\Onboarding;

class OnboardingClient
{
    private const PATH = '/unified-onboarding/api/onboard';

    private $apiKey;
    private $baseUrl;
    private $timeout;

    public function __construct($apiKey, $baseUrl, $timeout = 30)
    {
        $this->apiKey = trim($apiKey);
        $this->baseUrl = rtrim(trim($baseUrl), '/');
        $this->timeout = $timeout > 0 ? $timeout : 30;

        if ($this->apiKey === '') {
            throw new \InvalidArgumentException('apiKey is required');
        }

        if ($this->baseUrl === '') {
            throw new \InvalidArgumentException('baseUrl is required');
        }
    }

    public function create(array $payload, $idempotencyKey = null)
    {
        $handle = curl_init($this->serviceUrl());
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        if ($idempotencyKey !== null && trim($idempotencyKey) !== '') {
            $headers[] = 'Idempotency-Key: ' . trim($idempotencyKey);
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min($this->timeout, 10),
        ]);

        $body = curl_exec($handle);
        $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($body === false || $error !== '') {
            throw new \RuntimeException('Onboarding request failed: ' . $error);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Onboarding response was not valid JSON');
        }

        $decoded['httpStatus'] = $httpCode;
        if (($httpCode >= 200 && $httpCode < 300) || $httpCode === 422) {
            return $decoded;
        }

        throw new \RuntimeException('Onboarding request failed with status ' . $httpCode . ': ' . $body);
    }

    private function serviceUrl()
    {
        $normalizedBase = substr($this->baseUrl, -6) === '/unify'
            ? substr($this->baseUrl, 0, -6)
            : $this->baseUrl;

        return $normalizedBase . self::PATH;
    }
}
