<?php

namespace ComplyanceSDK\Enums;

/**
 * Source types for the SDK
 */
class SourceType
{
    const FIRST_PARTY = "FIRST_PARTY";
    const MARKETPLACE_APP = "MARKETPLACE_APP";
    const TURNKEY_INTEGRATION = "TURNKEY_INTEGRATION";
    const PORTAL_UPLOAD = "PORTAL_UPLOAD";
    const EMAIL_UPLOAD = "EMAIL_UPLOAD";

    private $value;

    private function __construct($value)
    {
        $this->value = $value;
    }

    public static function fromString($value)
    {
        $value = strtoupper($value);
        switch ($value) {
            case self::FIRST_PARTY:
                return new self(self::FIRST_PARTY);
            case self::MARKETPLACE_APP:
                return new self(self::MARKETPLACE_APP);
            case self::TURNKEY_INTEGRATION:
                return new self(self::TURNKEY_INTEGRATION);
            case self::PORTAL_UPLOAD:
                return new self(self::PORTAL_UPLOAD);
            case self::EMAIL_UPLOAD:
                return new self(self::EMAIL_UPLOAD);
            default:
                throw new \InvalidArgumentException("Unknown source type: " . $value);
        }
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getCode()
    {
        return $this->value;
    }

    public function __toString()
    {
        return $this->value;
    }
}