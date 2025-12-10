<?php

namespace ComplyanceSDK;

use ComplyanceSDK\Models\ErrorDetail;

/**
 * UnifyResponse class for API responses
 * 
 * @package ComplyanceSDK
 */
class UnifyResponse
{
    private $status;
    private $message;
    private $data;
    private $error;

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
     * Get data
     * 
     * @return UnifyResponseData|null
     */
    public function getData(): ?UnifyResponseData
    {
        return $this->data;
    }

    /**
     * Set data
     * 
     * @param UnifyResponseData|null $data
     */
    public function setData(?UnifyResponseData $data): void
    {
        $this->data = $data;
    }

    /**
     * Get error
     * 
     * @return ErrorDetail|null
     */
    public function getError(): ?ErrorDetail
    {
        return $this->error;
    }

    /**
     * Set error
     * 
     * @param ErrorDetail|null $error
     */
    public function setError(?ErrorDetail $error): void
    {
        $this->error = $error;
    }

    /**
     * Convert to array for JSON serialization
     * 
     * @return array
     */
    public function toArray(): array
    {
        $result = [
            'status' => $this->status,
            'message' => $this->message
        ];

        if ($this->data !== null) {
            $result['data'] = $this->data->toArray();
        }

        if ($this->error !== null) {
            $result['error'] = $this->error->toArray();
        }

        return $result;
    }

}
