<?php

namespace ComplyanceSDK\Exceptions;

/**
 * Configuration Exception
 * 
 * @package ComplyanceSDK\Exceptions
 */
class ConfigurationException extends SDKException
{
    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param int $code Exception code
     * @param \Exception|null $previous Previous exception
     */
    public function __construct($message = '', $code = 0, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}