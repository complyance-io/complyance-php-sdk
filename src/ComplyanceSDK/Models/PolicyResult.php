<?php

namespace ComplyanceSDK\Models;

use ComplyanceSDK\Enums\DocumentType;

/**
 * Policy result containing base type, meta config flags, and document type
 */
class PolicyResult
{
    private $baseType;
    private $metaConfigFlags;
    private $documentType;

    public function __construct($baseType, $metaConfigFlags, $documentType)
    {
        $this->baseType = $baseType;
        $this->metaConfigFlags = $metaConfigFlags ?: [];
        $this->documentType = $documentType;
    }

    public function getBaseType()
    {
        return $this->baseType;
    }

    public function getMetaConfigFlags()
    {
        return $this->metaConfigFlags;
    }

    public function getDocumentType()
    {
        return $this->documentType;
    }
}
