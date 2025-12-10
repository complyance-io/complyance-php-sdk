<?php

namespace ComplyanceSDK;

/**
 * SubmissionResponse class for legacy compatibility
 * 
 * @package ComplyanceSDK
 */
class SubmissionResponse
{
    private $status;
    private $message;
    private $submissionId;

    /**
     * Constructor
     * 
     * @param string $status
     * @param string $message
     */
    public function __construct(string $status = '', string $message = '')
    {
        $this->status = $status;
        $this->message = $message;
    }

    /**
     * Get status
     * 
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Set status
     * 
     * @param string $status
     */
    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    /**
     * Get message
     * 
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Set message
     * 
     * @param string $message
     */
    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    /**
     * Get submission ID
     * 
     * @return string
     */
    public function getSubmissionId(): string
    {
        return $this->submissionId;
    }

    /**
     * Set submission ID
     * 
     * @param string $submissionId
     */
    public function setSubmissionId(string $submissionId): void
    {
        $this->submissionId = $submissionId;
    }
}
