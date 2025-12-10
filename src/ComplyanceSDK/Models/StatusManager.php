<?php

namespace ComplyanceSDK\Models;

use ComplyanceSDK\Enums\SubmissionStatus;

/**
 * Status manager for submission status checks
 */
class StatusManager
{
    public static function isQueued($status)
    {
        return $status === SubmissionStatus::fromString(SubmissionStatus::QUEUED);
    }

    public static function isSubmitted($status)
    {
        return $status === SubmissionStatus::fromString(SubmissionStatus::SUBMITTED);
    }

    public static function isProcessing($status)
    {
        return $status === SubmissionStatus::fromString(SubmissionStatus::PROCESSING);
    }

    public static function isCompleted($status)
    {
        return $status === SubmissionStatus::fromString(SubmissionStatus::COMPLETED);
    }

    public static function hasFailed($status)
    {
        return $status === SubmissionStatus::fromString(SubmissionStatus::FAILED) ||
               $status === SubmissionStatus::fromString(SubmissionStatus::VALIDATION_ERROR) ||
               $status === SubmissionStatus::fromString(SubmissionStatus::REJECTED);
    }

    public static function isApproved($status)
    {
        return $status === SubmissionStatus::fromString(SubmissionStatus::APPROVED);
    }

    public static function isRejected($status)
    {
        return $status === SubmissionStatus::fromString(SubmissionStatus::REJECTED);
    }
}
