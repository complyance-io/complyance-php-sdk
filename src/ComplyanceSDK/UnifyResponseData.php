<?php

namespace ComplyanceSDK;

/**
 * UnifyResponseData class
 * 
 * @package ComplyanceSDK
 */
class UnifyResponseData
{
    private $submission;

    /**
     * Get submission
     * 
     * @return UnifyResponseSubmissionResponse|null
     */
    public function getSubmission(): ?UnifyResponseSubmissionResponse
    {
        return $this->submission;
    }

    /**
     * Set submission
     * 
     * @param UnifyResponseSubmissionResponse|null $submission
     */
    public function setSubmission(?UnifyResponseSubmissionResponse $submission): void
    {
        $this->submission = $submission;
    }

    /**
     * Convert to array
     * 
     * @return array
     */
    public function toArray(): array
    {
        $result = [];
        if ($this->submission !== null) {
            $result['submission'] = $this->submission->toArray();
        }
        return $result;
    }
}
