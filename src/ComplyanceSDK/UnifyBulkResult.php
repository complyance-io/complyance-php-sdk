<?php

namespace ComplyanceSDK;

/**
 * One ordered result from the revamped Unify bulk API.
 */
final class UnifyBulkResult
{
    private $index;
    private $success;
    private $result;
    private $error;

    private function __construct(int $index, bool $success, ?array $result, ?array $error)
    {
        $this->index = $index;
        $this->success = $success;
        $this->result = $result;
        $this->error = $error;
    }

    public static function fromArray(array $item): self
    {
        if (!array_key_exists('index', $item)) {
            throw new \UnexpectedValueException('Bulk result index must be an integer.');
        }
        if (!is_int($item['index'])) {
            throw new \UnexpectedValueException('Bulk result index must be an integer.');
        }
        if (!array_key_exists('success', $item)) {
            throw new \UnexpectedValueException('Bulk result success must be a boolean.');
        }
        if (!is_bool($item['success'])) {
            throw new \UnexpectedValueException('Bulk result success must be a boolean.');
        }

        $index = $item['index'];
        $success = $item['success'];
        $hasResult = array_key_exists('result', $item);
        $hasError = array_key_exists('error', $item);

        if ($success) {
            if (!$hasResult) {
                throw new \UnexpectedValueException(
                    'A successful bulk item must contain result and must not contain error.'
                );
            }
            if (!is_array($item['result'])) {
                throw new \UnexpectedValueException(
                    'A successful bulk item must contain result and must not contain error.'
                );
            }
            if ($hasError) {
                throw new \UnexpectedValueException(
                    'A successful bulk item must contain result and must not contain error.'
                );
            }

            return new self($index, true, $item['result'], null);
        }

        if ($hasResult === $hasError) {
            throw new \UnexpectedValueException(
                'A failed bulk item must contain exactly one of result or error.'
            );
        }

        if ($hasResult) {
            if (!is_array($item['result'])) {
                throw new \UnexpectedValueException('Bulk validation failure result must be an object.');
            }
            if (!array_key_exists('errors', $item['result'])) {
                throw new \UnexpectedValueException(
                    'Bulk validation failure result must contain an errors array.'
                );
            }
            if (!is_array($item['result']['errors'])) {
                throw new \UnexpectedValueException(
                    'Bulk validation failure result must contain an errors array.'
                );
            }

            return new self($index, false, $item['result'], null);
        }

        if (!is_array($item['error'])) {
            throw new \UnexpectedValueException('Bulk processing failure error must be an object.');
        }
        self::requireNonEmptyString($item['error'], 'code', 'Bulk processing failure');
        self::requireNonEmptyString($item['error'], 'message', 'Bulk processing failure');

        return new self($index, false, null, $item['error']);
    }

    private static function requireNonEmptyString(array $value, string $field, string $context): void
    {
        if (!array_key_exists($field, $value)) {
            throw new \UnexpectedValueException("{$context} {$field} must be a non-empty string.");
        }
        if (!is_string($value[$field])) {
            throw new \UnexpectedValueException("{$context} {$field} must be a non-empty string.");
        }
        if (trim($value[$field]) === '') {
            throw new \UnexpectedValueException("{$context} {$field} must be a non-empty string.");
        }
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isValidationFailure(): bool
    {
        return !$this->success && $this->result !== null;
    }

    public function isProcessingFailure(): bool
    {
        return !$this->success && $this->error !== null;
    }

    public function getResult(): ?array
    {
        return $this->result;
    }

    public function getError(): ?array
    {
        return $this->error;
    }
}
