<?php

namespace ComplyanceSDK\Models;

use ComplyanceSDK\Enums\ErrorCode;

/**
 * Detailed error information with context and suggestions
 * 
 * @package ComplyanceSDK\Models
 */
class ErrorDetail
{
    private $code;
    private $message;
    private $suggestion;
    private $documentationUrl;
    private $field;
    private $fieldValue;
    private $context;
    private $validationErrors;
    private $retryable;
    private $retryAfterSeconds;
    private $timestamp;

    /**
     * Constructor
     * 
     * @param string $code Error code
     * @param string $message Error message
     * @param string|null $suggestion Suggestion for fixing the error
     * @param string|null $documentationUrl Documentation URL
     * @param string|null $field Field that caused the error
     * @param mixed $fieldValue Value of the field that caused the error
     * @param array $context Additional context
     * @param array $validationErrors Array of validation errors
     * @param bool $retryable Whether the error is retryable
     * @param int|null $retryAfterSeconds Seconds to wait before retrying
     */
    public function __construct(
        $code,
        $message,
        $suggestion = null,
        $documentationUrl = null,
        $field = null,
        $fieldValue = null,
        $context = [],
        $validationErrors = [],
        $retryable = null,
        $retryAfterSeconds = null
    ) {
        $this->code = $code;
        $this->message = $message;
        $this->suggestion = $suggestion;
        $this->documentationUrl = $documentationUrl;
        $this->field = $field;
        $this->fieldValue = $fieldValue;
        $this->context = $context;
        $this->validationErrors = $validationErrors;
        $this->retryable = $retryable !== null ? $retryable : ErrorCode::isRetryable($code);
        $this->retryAfterSeconds = $retryAfterSeconds;
        $this->timestamp = date('c'); // ISO 8601 format
    }

    /**
     * Factory method for validation errors
     * 
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $message Error message
     * @return self
     */
    public static function validationError($field, $value, $message)
    {
        $error = new self(ErrorCode::VALIDATION_FAILED, $message);
        $error->setField($field);
        $error->setFieldValue($value);
        $error->setSuggestion("Please check the field value and format");
        $error->setDocumentationUrl("https://docs.complyance.io/validation-rules");
        return $error;
    }

    /**
     * Factory method for country-specific errors
     * 
     * @param string $country Country code
     * @param string $code Error code
     * @param string $message Error message
     * @return self
     */
    public static function countrySpecificError($country, $code, $message)
    {
        $error = new self($code, $message);
        $error->addContextValue("country", $country);
        $error->setSuggestion("Check country-specific compliance requirements");
        $error->setDocumentationUrl("https://docs.complyance.io/countries/" . strtolower($country));
        return $error;
    }

    /**
     * Factory method for API errors
     * 
     * @param int $httpStatus HTTP status code
     * @param string $responseBody Response body
     * @return self
     */
    public static function apiError($httpStatus, $responseBody)
    {
        $error = new self(ErrorCode::API_ERROR, "API request failed with HTTP " . $httpStatus);
        $error->addContextValue("httpStatus", $httpStatus);
        $error->addContextValue("responseBody", $responseBody);
        $error->setRetryable($httpStatus >= 500 || $httpStatus == 429);

        if ($httpStatus == 429) {
            $error->setSuggestion("Request rate limit exceeded. Please wait before retrying.");
            $error->setRetryAfterSeconds(60);
        } elseif ($httpStatus >= 500) {
            $error->setSuggestion("Server error occurred. The request can be retried.");
        } else {
            $error->setSuggestion("Check your request parameters and authentication.");
        }

        return $error;
    }

    /**
     * Factory method for template errors
     * 
     * @param string $templateId Template ID
     * @param string $message Error message
     * @return self
     */
    public static function templateError($templateId, $message)
    {
        $error = new self(ErrorCode::TEMPLATE_NOT_FOUND, $message);
        $error->addContextValue("templateId", $templateId);
        $error->setSuggestion("Ensure the template exists and is properly configured for your source and document type");
        $error->setDocumentationUrl("https://docs.complyance.io/templates");
        return $error;
    }

    /**
     * Factory method for service unavailable errors
     * 
     * @param string $message Error message
     * @return self
     */
    public static function serviceUnavailable($message)
    {
        $error = new self(ErrorCode::SERVICE_UNAVAILABLE, $message);
        $error->setSuggestion("Service is temporarily unavailable. Please retry after some time");
        $error->setRetryable(true);
        return $error;
    }

    /**
     * Factory method for operation cancelled errors
     * 
     * @param string $message Error message
     * @return self
     */
    public static function operationCancelled($message)
    {
        $error = new self(ErrorCode::PROCESSING_ERROR, $message);
        $error->setSuggestion("Operation was cancelled or interrupted");
        $error->setRetryable(false);
        return $error;
    }

    /**
     * Factory method for max retries exceeded errors
     * 
     * @param int $maxAttempts Maximum attempts
     * @return self
     */
    public static function maxRetriesExceeded($maxAttempts)
    {
        $error = new self(ErrorCode::MAX_RETRIES_EXCEEDED, "Operation failed after " . $maxAttempts . " retry attempts");
        $error->setSuggestion("Maximum retry attempts exceeded. Check your network connection and try again later");
        $error->setRetryable(false);
        $error->addContextValue("maxAttempts", $maxAttempts);
        return $error;
    }

    /**
     * Add context value
     * 
     * @param string $key Context key
     * @param mixed $value Context value
     * @return self
     */
    public function addContextValue($key, $value)
    {
        if ($this->context === null) {
            $this->context = [];
        }
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Add validation error
     * 
     * @param string $field Field name
     * @param string $message Error message
     * @param string $code Error code
     * @return self
     */
    public function addValidationError($field, $message, $code)
    {
        if ($this->validationErrors === null) {
            $this->validationErrors = [];
        }
        $this->validationErrors[] = new ValidationDetail($field, $message, $code);
        return $this;
    }

    /**
     * Get context value
     * 
     * @param string $key Context key
     * @return mixed Context value
     */
    public function getContextValue($key)
    {
        if ($this->context === null) {
            return null;
        }
        return isset($this->context[$key]) ? $this->context[$key] : null;
    }

    /**
     * Get error code
     * 
     * @return string Error code
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Get error message
     * 
     * @return string Error message
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Get suggestion
     * 
     * @return string|null Suggestion
     */
    public function getSuggestion()
    {
        return $this->suggestion;
    }

    /**
     * Get documentation URL
     * 
     * @return string|null Documentation URL
     */
    public function getDocumentationUrl()
    {
        return $this->documentationUrl;
    }

    /**
     * Get field
     * 
     * @return string|null Field
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * Get field value
     * 
     * @return string|null Field value
     */
    public function getFieldValue()
    {
        return $this->fieldValue;
    }

    /**
     * Get validation errors
     * 
     * @return array Array of validation errors
     */
    public function getValidationErrors()
    {
        return $this->validationErrors;
    }

    /**
     * Check if error is retryable
     * 
     * @return bool True if retryable
     */
    public function isRetryable()
    {
        return $this->retryable === true;
    }

    /**
     * Get retry after seconds
     * 
     * @return int|null Retry after seconds
     */
    public function getRetryAfterSeconds()
    {
        return $this->retryAfterSeconds;
    }

    /**
     * Get context
     * 
     * @return array Context
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Set error code
     * 
     * @param int $code Error code
     * @return self
     */
    public function setCode($code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * Set error message
     * 
     * @param string $message Error message
     * @return self
     */
    public function setMessage($message)
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Set suggestion
     * 
     * @param string|null $suggestion Suggestion
     * @return self
     */
    public function setSuggestion($suggestion)
    {
        $this->suggestion = $suggestion;
        return $this;
    }

    /**
     * Set documentation URL
     * 
     * @param string|null $documentationUrl Documentation URL
     * @return self
     */
    public function setDocumentationUrl($documentationUrl)
    {
        $this->documentationUrl = $documentationUrl;
        return $this;
    }

    /**
     * Set field
     * 
     * @param string|null $field Field
     * @return self
     */
    public function setField($field)
    {
        $this->field = $field;
        return $this;
    }

    /**
     * Set field value
     * 
     * @param string|null $fieldValue Field value
     * @return self
     */
    public function setFieldValue($fieldValue)
    {
        $this->fieldValue = $fieldValue;
        return $this;
    }

    /**
     * Set validation errors
     * 
     * @param array $validationErrors Array of validation errors
     * @return self
     */
    public function setValidationErrors($validationErrors)
    {
        $this->validationErrors = $validationErrors;
        return $this;
    }

    /**
     * Set retryable
     * 
     * @param bool $retryable Whether retryable
     * @return self
     */
    public function setRetryable($retryable)
    {
        $this->retryable = $retryable;
        return $this;
    }

    /**
     * Set retry after seconds
     * 
     * @param int|null $retryAfterSeconds Retry after seconds
     * @return self
     */
    public function setRetryAfterSeconds($retryAfterSeconds)
    {
        $this->retryAfterSeconds = $retryAfterSeconds;
        return $this;
    }

    /**
     * Set context
     * 
     * @param array $context Context
     * @return self
     */
    public function setContext($context)
    {
        $this->context = $context;
        return $this;
    }


    /**
     * Add context item
     * 
     * @param string $key Context key
     * @param mixed $value Context value
     * @return self
     */
    public function addContext($key, $value)
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Convert to array
     * 
     * @return array Array representation
     */
    public function toArray()
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'suggestion' => $this->suggestion,
            'documentationUrl' => $this->documentationUrl,
            'field' => $this->field,
            'fieldValue' => $this->fieldValue,
            'validationErrors' => $this->validationErrors,
            'retryable' => $this->retryable,
            'retryAfterSeconds' => $this->retryAfterSeconds,
            'context' => $this->context
        ];
    }

    /**
     * Convert to JSON
     * 
     * @return string JSON representation
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }

    /**
     * Create from array
     * 
     * @param array $data Array data
     * @return self
     */
    public static function fromArray($data)
    {
        return new self(
            $data['code'] ?? ErrorCode::UNKNOWN_ERROR,
            $data['message'] ?? '',
            $data['suggestion'] ?? null,
            $data['documentationUrl'] ?? null,
            $data['field'] ?? null,
            $data['fieldValue'] ?? null,
            $data['validationErrors'] ?? [],
            $data['retryable'] ?? false,
            $data['retryAfterSeconds'] ?? null,
            $data['context'] ?? []
        );
    }

    /**
     * String representation
     * 
     * @return string String representation
     */
    public function toString()
    {
        $str = sprintf('[%d] %s: %s', $this->code, ErrorCode::getName($this->code), $this->message);
        
        if ($this->suggestion !== null) {
            $str .= "\nSuggestion: " . $this->suggestion;
        }
        
        if ($this->field !== null) {
            $str .= "\nField: " . $this->field;
            if ($this->fieldValue !== null) {
                $str .= " (Value: " . $this->fieldValue . ")";
            }
        }
        
        if (!empty($this->validationErrors)) {
            $str .= "\nValidation Errors:";
            foreach ($this->validationErrors as $error) {
                $str .= "\n  - " . $error['field'] . ": " . $error['message'];
            }
        }
        
        if ($this->retryable) {
            $str .= "\nRetryable: Yes";
            if ($this->retryAfterSeconds !== null) {
                $str .= " (Retry after: " . $this->retryAfterSeconds . " seconds)";
            }
        }
        
        if (!empty($this->context)) {
            $str .= "\nContext:";
            foreach ($this->context as $key => $value) {
                $str .= "\n  " . $key . ": " . (is_array($value) ? json_encode($value) : $value);
            }
        }
        
        return $str;
    }

    /**
     * String representation
     * 
     * @return string String representation
     */
    public function __toString()
    {
        $result = "ErrorDetail{code=" . $this->code . ", message='" . $this->message . "'";
        if ($this->field !== null) {
            $result .= ", field='" . $this->field . "'";
        }
        if ($this->suggestion !== null) {
            $result .= ", suggestion='" . $this->suggestion . "'";
        }
        $result .= ", retryable=" . ($this->retryable ? 'true' : 'false') . "}";
        return $result;
    }
}

/**
 * Validation error detail for field-level errors
 */
class ValidationDetail
{
    private $field;
    private $message;
    private $code;

    /**
     * Constructor
     * 
     * @param string $field Field name
     * @param string $message Error message
     * @param string $code Error code
     */
    public function __construct($field, $message, $code)
    {
        $this->field = $field;
        $this->message = $message;
        $this->code = $code;
    }

    /**
     * Get field name
     * 
     * @return string Field name
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * Set field name
     * 
     * @param string $field Field name
     * @return self
     */
    public function setField($field)
    {
        $this->field = $field;
        return $this;
    }

    /**
     * Get error message
     * 
     * @return string Error message
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Set error message
     * 
     * @param string $message Error message
     * @return self
     */
    public function setMessage($message)
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Get error code
     * 
     * @return string Error code
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set error code
     * 
     * @param string $code Error code
     * @return self
     */
    public function setCode($code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * String representation
     * 
     * @return string String representation
     */
    public function __toString()
    {
        return "ValidationDetail{field='" . $this->field . "', message='" . $this->message . "', code='" . $this->code . "'}";
    }
}