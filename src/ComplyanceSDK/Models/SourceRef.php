<?php

namespace ComplyanceSDK\Models;

/**
 * Source reference with name and version
 */
class SourceRef
{
    private $name;
    private $version;

    public function __construct($name, $version)
    {
        // Allow empty strings for optional source references (e.g., MAPPING purpose)
        $this->name = ($name === null) ? "" : trim($name);
        $this->version = ($version === null) ? "" : trim($version);
    }

    public function getName()
    {
        return $this->name;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function getIdentity()
    {
        return $this->name . ":" . $this->version;
    }

    public function equals($obj)
    {
        if ($this === $obj) return true;
        if ($obj === null || get_class($obj) !== get_class($this)) return false;
        return $this->name === $obj->name && $this->version === $obj->version;
    }

    public function __toString()
    {
        return "SourceRef{name='" . $this->name . "', version='" . $this->version . "'}";
    }
}
