<?php

namespace ComplyanceSDK;

/**
 * UnifyResponseSubmissionResponse class
 * 
 * @package ComplyanceSDK
 */
class UnifyResponseSubmissionResponse
{
    private $submissionId;
    private $country;
    private $authority;
    private $status;
    private $submittedAt;
    private $response;
    private $errors;

    /**
     * Constructor
     * 
     * @param string $status
     * @param string $message
     */
    public function __construct(string $status = '', string $message = '')
    {
        $this->status = $status;
        $this->submittedAt = date('c'); // ISO 8601 format
    }

    /**
     * Get submission ID
     * 
     * @return string|null
     */
    public function getSubmissionId(): ?string
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
     * Get country
     * 
     * @return string|null
     */
    public function getCountry(): ?string
    {
        return $this->country;
    }

    /**
     * Set country
     * 
     * @param string $country
     */
    public function setCountry(string $country): void
    {
        $this->country = $country;
    }

    /**
     * Get authority
     * 
     * @return string|null
     */
    public function getAuthority(): ?string
    {
        return $this->authority;
    }

    /**
     * Set authority
     * 
     * @param string $authority
     */
    public function setAuthority(string $authority): void
    {
        $this->authority = $authority;
    }

    /**
     * Get submitted at
     * 
     * @return string|null
     */
    public function getSubmittedAt(): ?string
    {
        return $this->submittedAt;
    }

    /**
     * Set submitted at
     * 
     * @param string $submittedAt
     */
    public function setSubmittedAt(string $submittedAt): void
    {
        $this->submittedAt = $submittedAt;
    }

    /**
     * Get response
     * 
     * @return array|null
     */
    public function getResponse(): ?array
    {
        return $this->response;
    }

    /**
     * Set response
     * 
     * @param array $response
     */
    public function setResponse(array $response): void
    {
        $this->response = $response;
    }

    /**
     * Get errors
     * 
     * @return array|null
     */
    public function getErrors(): ?array
    {
        return $this->errors;
    }

    /**
     * Set errors
     * 
     * @param array $errors
     */
    public function setErrors(array $errors): void
    {
        $this->errors = $errors;
    }

    /**
     * Check if submission is accepted
     * 
     * @return bool
     */
    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    /**
     * Check if submission is rejected
     * 
     * @return bool
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if submission is failed
     * 
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if submission is submitted
     * 
     * @return bool
     */
    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    /**
     * Convert to array
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'submissionId' => $this->submissionId,
            'country' => $this->country,
            'authority' => $this->authority,
            'status' => $this->status,
            'submittedAt' => $this->submittedAt,
            'response' => $this->response,
            'errors' => $this->errors
        ];
    }
}
