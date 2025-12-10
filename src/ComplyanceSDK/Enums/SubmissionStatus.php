<?php

namespace ComplyanceSDK\Enums;

/**
 * Submission status values
 */
class SubmissionStatus
{
    const QUEUED = "QUEUED";
    const SUBMITTED = "SUBMITTED";
    const PROCESSING = "PROCESSING";
    const COMPLETED = "COMPLETED";
    const FAILED = "FAILED";
    const VALIDATION_ERROR = "VALIDATION_ERROR";
    const APPROVED = "APPROVED";
    const REJECTED = "REJECTED";

    private $value;

    private function __construct($value)
    {
        $this->value = $value;
    }

    public static function fromString($value)
    {
        $value = strtoupper($value);
        switch ($value) {
            case self::QUEUED:
                return new self(self::QUEUED);
            case self::SUBMITTED:
                return new self(self::SUBMITTED);
            case self::PROCESSING:
                return new self(self::PROCESSING);
            case self::COMPLETED:
                return new self(self::COMPLETED);
            case self::FAILED:
                return new self(self::FAILED);
            case self::VALIDATION_ERROR:
                return new self(self::VALIDATION_ERROR);
            case self::APPROVED:
                return new self(self::APPROVED);
            case self::REJECTED:
                return new self(self::REJECTED);
            default:
                throw new \InvalidArgumentException("Unknown submission status: " . $value);
        }
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getCode()
    {
        return $this->value;
    }

    public function __toString()
    {
        return $this->value;
    }
}
