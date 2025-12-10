<?php

namespace ComplyanceSDK\Enums;

/**
 * Types of destinations where documents can be sent
 */
class DestinationType
{
    /** Tax authority (e.g., ZATCA for Saudi Arabia, LHDN for Malaysia) */
    const TAX_AUTHORITY = "tax_authority";

    /** Archive/storage destination */
    const ARCHIVE = "archive";

    /** Email destination */
    const EMAIL = "email";

    /** Peppol network destination */
    const PEPPOL = "peppol";

    private $value;

    private function __construct($value)
    {
        $this->value = $value;
    }

    public static function fromString($value)
    {
        $value = strtolower($value);
        switch ($value) {
            case self::TAX_AUTHORITY:
                return new self(self::TAX_AUTHORITY);
            case self::ARCHIVE:
                return new self(self::ARCHIVE);
            case self::EMAIL:
                return new self(self::EMAIL);
            case self::PEPPOL:
                return new self(self::PEPPOL);
            default:
                throw new \InvalidArgumentException("Unknown destination type: " . $value);
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
