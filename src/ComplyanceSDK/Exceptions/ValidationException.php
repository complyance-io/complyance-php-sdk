<?php

namespace ComplyanceSDK\Exceptions;

use ComplyanceSDK\Models\ErrorDetail;
use ComplyanceSDK\Enums\ErrorCode;

/**
 * Validation Exception
 * 
 * @package ComplyanceSDK\Exceptions
 */
class ValidationException extends SDKException
{
    /**
     * Constructor
     * 
     * @param string $message Exception message
     * @param string $suggestion Suggestion for fixing the validation error
     * @param int $code Exception code
     * @param \Exception|null $previous Previous exception
     */
    public function __construct(
        $message = '',
        $suggestion = '',
        $code = 0,
        $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        
        if (!empty($suggestion)) {
            $this->setErrorDetail(new ErrorDetail(
                ErrorCode::VALIDATION_FAILED,
                $message,
                $suggestion
            ));
        }
    }
}