<?php

namespace ComplyanceSDK\Models;

use ComplyanceSDK\Enums\DestinationType;
use ComplyanceSDK\Models\DestinationDetails;

/**
 * Represents a destination where the document should be sent.
 * Examples: tax authorities (ZATCA, LHDN), email, archive, peppol networks
 */
class Destination
{
    private $type;
    private $details;

    public function __construct($type = null, $details = null)
    {
        $this->type = $type;
        $this->details = $details;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getDetails()
    {
        return $this->details;
    }

    public function setDetails($details)
    {
        $this->details = $details;
    }

    /**
     * Helper method to create a tax authority destination
     */
    public static function taxAuthority($country, $authority, $documentType)
    {
        $details = new DestinationDetails();
        $details->setCountry($country);
        $details->setAuthority($authority);
        $details->setDocumentType($documentType);
        return new self(DestinationType::fromString(DestinationType::TAX_AUTHORITY), $details);
    }

    /**
     * Helper method to create an email destination
     */
    public static function email($recipients, $subject, $body)
    {
        $details = new DestinationDetails();
        $details->setRecipients($recipients);
        $details->setSubject($subject);
        $details->setBody($body);
        return new self(DestinationType::fromString(DestinationType::EMAIL), $details);
    }

    /**
     * Helper method to create an archive destination
     */
    public static function archive()
    {
        return new self(DestinationType::fromString(DestinationType::ARCHIVE), new DestinationDetails());
    }

    /**
     * Helper method to create a peppol destination
     */
    public static function peppol($participantId, $processId, $documentType)
    {
        $details = new DestinationDetails();
        $details->setParticipantId($participantId);
        $details->setProcessId($processId);
        $details->setDocumentType($documentType);
        return new self(DestinationType::fromString(DestinationType::PEPPOL), $details);
    }

    public function __toString()
    {
        return "Destination{type=" . $this->type . ", details=" . $this->details . "}";
    }
}
