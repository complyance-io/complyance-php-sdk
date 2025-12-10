<?php

namespace ComplyanceSDK\Exceptions;

/**
 * API Exception
 * 
 * @package ComplyanceSDK\Exceptions
 */
class APIException extends SDKException
{
    private $httpStatusCode;
    private $responseBody;

    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param int $httpStatusCode HTTP status code
     * @param string|null $responseBody Response body
     * @param int $code Exception code
     * @param \Exception|null $previous Previous exception
     */
    public function __construct(
        $message = '',
        $httpStatusCode = 0,
        $responseBody = null,
        $code = 0,
        $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->httpStatusCode = $httpStatusCode;
        $this->responseBody = $responseBody;
    }

    /**
     * Get HTTP status code
     * 
     * @return int HTTP status code
     */
    public function getHttpStatusCode()
    {
        return $this->httpStatusCode;
    }

    /**
     * Get response body
     * 
     * @return string|null Response body
     */
    public function getResponseBody()
    {
        return $this->responseBody;
    }

    /**
     * Check if error is retryable based on HTTP status code
     * 
     * @return bool True if retryable
     */
    public function isRetryable()
    {
        return in_array($this->httpStatusCode, [408, 429, 500, 502, 503, 504]);
    }
}