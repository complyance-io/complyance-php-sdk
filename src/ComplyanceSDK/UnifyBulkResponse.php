<?php

namespace ComplyanceSDK;

/**
 * Parsed response from the revamped multi-invoice Unify API.
 */
final class UnifyBulkResponse
{
    private $total;
    private $succeeded;
    private $failed;
    private $results;

    /**
     * @param UnifyBulkResult[] $results
     */
    private function __construct(int $total, int $succeeded, int $failed, array $results)
    {
        $this->total = $total;
        $this->succeeded = $succeeded;
        $this->failed = $failed;
        $this->results = $results;
    }

    public static function fromJson(string $responseBody): self
    {
        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            throw new \UnexpectedValueException('Bulk Unify response must be a JSON object.');
        }
        if (self::isList($decoded)) {
            throw new \UnexpectedValueException('Bulk Unify response must be a JSON object.');
        }

        return self::fromArray($decoded);
    }

    public static function fromArray(array $response): self
    {
        if (!array_key_exists('summary', $response)) {
            throw new \UnexpectedValueException('Bulk Unify response must contain a summary object.');
        }
        if (!is_array($response['summary'])) {
            throw new \UnexpectedValueException('Bulk Unify response must contain a summary object.');
        }
        if (!array_key_exists('results', $response)) {
            throw new \UnexpectedValueException('Bulk Unify response must contain a results array.');
        }
        if (!is_array($response['results'])) {
            throw new \UnexpectedValueException('Bulk Unify response must contain a results array.');
        }

        $total = self::requireNonNegativeInteger($response['summary'], 'total');
        $succeeded = self::requireNonNegativeInteger($response['summary'], 'succeeded');
        $failed = self::requireNonNegativeInteger($response['summary'], 'failed');
        if ($succeeded + $failed !== $total) {
            throw new \UnexpectedValueException('Bulk Unify summary counts are inconsistent.');
        }
        if (count($response['results']) !== $total) {
            throw new \UnexpectedValueException('Bulk Unify results count does not match summary.total.');
        }

        $results = [];
        $actualSucceeded = 0;
        $actualFailed = 0;
        foreach ($response['results'] as $position => $item) {
            if (!is_array($item)) {
                throw new \UnexpectedValueException('Each bulk Unify result must be an object.');
            }
            $result = UnifyBulkResult::fromArray($item);
            if ($result->getIndex() !== $position) {
                throw new \UnexpectedValueException(
                    'Bulk Unify results must remain in request order with zero-based indexes.'
                );
            }
            if ($result->isSuccess()) {
                $actualSucceeded++;
            } else {
                $actualFailed++;
            }
            $results[] = $result;
        }
        if ($actualSucceeded !== $succeeded) {
            throw new \UnexpectedValueException(
                'Bulk Unify summary counts do not match the item outcomes.'
            );
        }
        if ($actualFailed !== $failed) {
            throw new \UnexpectedValueException(
                'Bulk Unify summary counts do not match the item outcomes.'
            );
        }

        return new self($total, $succeeded, $failed, $results);
    }

    private static function requireNonNegativeInteger(array $summary, string $field): int
    {
        if (!array_key_exists($field, $summary)) {
            throw new \UnexpectedValueException("Bulk Unify summary.{$field} must be a non-negative integer.");
        }
        if (!is_int($summary[$field])) {
            throw new \UnexpectedValueException("Bulk Unify summary.{$field} must be a non-negative integer.");
        }
        if ($summary[$field] < 0) {
            throw new \UnexpectedValueException("Bulk Unify summary.{$field} must be a non-negative integer.");
        }

        return $summary[$field];
    }

    private static function isList(array $value): bool
    {
        $expectedKey = 0;
        foreach ($value as $key => $unused) {
            if ($key !== $expectedKey) {
                return false;
            }
            $expectedKey++;
        }

        return true;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getSucceeded(): int
    {
        return $this->succeeded;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }

    /**
     * @return UnifyBulkResult[]
     */
    public function getResults(): array
    {
        return $this->results;
    }
}
