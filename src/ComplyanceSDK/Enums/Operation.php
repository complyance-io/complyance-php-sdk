<?php

namespace ComplyanceSDK\Enums;

/**
 * Operation enumeration
 * 
 * @package ComplyanceSDK\Enums
 */
class Operation
{
    const SINGLE = 'SINGLE';
    const BATCH = 'BATCH';
    const BULK = 'BULK';

    private $code;

    /**
     * Get operation name
     * 
     * @param string $code Operation code
     * @return string Operation name
     */
    public static function getName($code)
    {
        $names = [
            self::SINGLE => 'Single Document',
            self::BATCH => 'Batch Processing',
            self::BULK => 'Bulk Processing',
        ];

        return isset($names[$code]) ? $names[$code] : 'Unknown';
    }

    /**
     * Check if operation is single
     * 
     * @param string $code Operation code
     * @return bool True if single
     */
    public static function isSingle($code)
    {
        return $code === self::SINGLE;
    }

    /**
     * Check if operation is batch
     * 
     * @param string $code Operation code
     * @return bool True if batch
     */
    public static function isBatch($code)
    {
        return $code === self::BATCH;
    }

    /**
     * Check if operation is bulk
     * 
     * @param string $code Operation code
     * @return bool True if bulk
     */
    public static function isBulk($code)
    {
        return $code === self::BULK;
    }

    /**
     * Get all operation codes
     * 
     * @return array Array of operation codes
     */
    public static function getAllCodes()
    {
        return [self::SINGLE, self::BATCH, self::BULK];
    }

    /**
     * Check if operation code is valid
     * 
     * @param string $code Operation code
     * @return bool True if valid
     */
    public static function isValid($code)
    {
        return in_array($code, self::getAllCodes());
    }

    /**
     * Create Operation instance from string
     * 
     * @param string $code Operation code
     * @return Operation Operation instance
     */
    public static function from($code)
    {
        $instance = new self();
        $instance->code = $code;
        return $instance;
    }

    /**
     * Get the operation code
     * 
     * @return string Operation code
     */
    public function getCode()
    {
        return $this->code;
    }
}