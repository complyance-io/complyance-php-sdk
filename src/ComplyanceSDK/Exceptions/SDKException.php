<?php

namespace ComplyanceSDK\Exceptions;

use Exception;

/**
 * Base SDK Exception
 * 
 * @package ComplyanceSDK\Exceptions
 */
class SDKException extends Exception
{
    private $errorDetail;

    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param Exception|null $previous Previous exception
     * @param ErrorDetail|null $errorDetail Error detail
     */
    public function __construct(
        $message = '',
        $code = 0,
        $previous = null,
        $errorDetail = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorDetail = $errorDetail;
    }

    /**
     * Get error detail
     * 
     * @return ErrorDetail|null Error detail
     */
    public function getErrorDetail()
    {
        return $this->errorDetail;
    }

    /**
     * Set error detail
     * 
     * @param ErrorDetail $errorDetail Error detail
     * @return self
     */
    public function setErrorDetail($errorDetail)
    {
        $this->errorDetail = $errorDetail;
        return $this;
    }

    /**
     * Create from error detail
     * 
     * @param ErrorDetail $errorDetail Error detail
     * @return self
     */
    public static function fromErrorDetail($errorDetail)
    {
        return new self(
            $errorDetail->getMessage(),
            $errorDetail->getCode(),
            null,
            $errorDetail
        );
    }

    /**
     * String representation
     * 
     * @return string String representation
     */
    public function __toString()
    {
        $str = parent::__toString();
        
        if ($this->errorDetail !== null) {
            $str .= "\nError Detail: " . $this->errorDetail->toString();
        }
        
        return $str;
    }
}