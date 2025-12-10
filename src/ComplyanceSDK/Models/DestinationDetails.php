<?php

namespace ComplyanceSDK\Models;

/**
 * Details specific to different destination types
 */
class DestinationDetails
{
    // For tax_authority destinations
    private $country;
    private $authority;
    private $documentType;

    // For email destinations
    private $recipients;
    private $subject;
    private $body;

    // For peppol destinations
    private $participantId;
    private $processId;

    public function __construct()
    {
    }

    // Tax authority getters/setters
    public function getCountry()
    {
        return $this->country;
    }

    public function setCountry($country)
    {
        $this->country = $country;
    }

    public function getAuthority()
    {
        return $this->authority;
    }

    public function setAuthority($authority)
    {
        $this->authority = $authority;
    }

    public function getDocumentType()
    {
        return $this->documentType;
    }

    public function setDocumentType($documentType)
    {
        $this->documentType = $documentType;
    }

    // Email getters/setters
    public function getRecipients()
    {
        return $this->recipients;
    }

    public function setRecipients($recipients)
    {
        $this->recipients = $recipients;
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function setSubject($subject)
    {
        $this->subject = $subject;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function setBody($body)
    {
        $this->body = $body;
    }

    // Peppol getters/setters
    public function getParticipantId()
    {
        return $this->participantId;
    }

    public function setParticipantId($participantId)
    {
        $this->participantId = $participantId;
    }

    public function getProcessId()
    {
        return $this->processId;
    }

    public function setProcessId($processId)
    {
        $this->processId = $processId;
    }

    public function __toString()
    {
        $recipientsStr = $this->recipients ? implode(',', $this->recipients) : 'null';
        return "DestinationDetails{country='" . $this->country . "', authority='" . $this->authority . 
               "', documentType='" . $this->documentType . "', recipients=" . $recipientsStr . 
               ", subject='" . $this->subject . "', body='" . $this->body . 
               "', participantId='" . $this->participantId . "', processId='" . $this->processId . "'}";
    }
}
