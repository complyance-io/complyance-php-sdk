<?php

namespace ComplyanceSDK\Models;

use ComplyanceSDK\Enums\SourceType;

/**
 * Source model representing a data source
 * 
 * @package ComplyanceSDK\Models
 */
class Source
{
    private $name;
    private $version;
    private $type;

    /**
     * Constructor
     * 
     * @param string $name Source name
     * @param string $version Source version
     * @param string|null $type Source type
     */
    public function __construct($name = '', $version = '', $type = null)
    {
        $this->name = $name ? trim($name) : '';
        $this->version = $version ? trim($version) : '';
        $this->type = $type;
    }

    /**
     * Get source name
     * 
     * @return string Source name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get source version
     * 
     * @return string Source version
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Get source type
     * 
     * @return string|null Source type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Get source type as string
     * 
     * @return string Source type string
     */
    public function getTypeString()
    {
        if ($this->type !== null) {
            if (is_object($this->type) && method_exists($this->type, 'getCode')) {
                return $this->type->getCode();
            }
            return (string) $this->type;
        }
        return '';
    }

    /**
     * Get source ID (computed from name and version)
     * 
     * @return string Source ID
     */
    public function getId()
    {
        // Match Java SDK format: just name:version (not including source type)
        return $this->name . ':' . $this->version;
    }

    /**
     * Get source identity
     * 
     * @return string Source identity
     */
    public function getIdentity()
    {
        return $this->name . ':' . $this->version;
    }

    /**
     * Set source name
     * 
     * @param string $name Source name
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name ? trim($name) : '';
        return $this;
    }

    /**
     * Set source version
     * 
     * @param string $version Source version
     * @return self
     */
    public function setVersion($version)
    {
        $this->version = $version ? trim($version) : '';
        return $this;
    }

    /**
     * Set source type
     * 
     * @param mixed $type Source type
     * @return self
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Set source ID (for compatibility - splits into name and version)
     * 
     * @param string $id Source ID in format "name:version"
     * @return self
     */
    public function setId($id)
    {
        if ($id && strpos($id, ':') !== false) {
            $parts = explode(':', $id, 2);
            $this->name = trim($parts[0]);
            $this->version = trim($parts[1]);
        }
        return $this;
    }

    /**
     * Set source identity (alias for setId)
     * 
     * @param string $identity Source identity in format "name:version"
     * @return self
     */
    public function setIdentity($identity)
    {
        return $this->setId($identity);
    }

    /**
     * Convert to array
     * 
     * @return array Array representation
     */
    public function toArray()
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'type' => $this->getTypeString(),
            'id' => $this->name . ':' . $this->version,
            'identity' => $this->name . ':' . $this->version
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
        $type = null;
        if (isset($data['type']) && !empty($data['type'])) {
            $type = $data['type'];
        }

        return new self(
            $data['name'] ?? '',
            $data['version'] ?? '',
            $type
        );
    }

    /**
     * Check equality with another source
     * 
     * @param Source $other Other source
     * @return bool True if equal
     */
    public function equals(Source $other)
    {
        return $this->name === $other->name && $this->version === $other->version;
    }

    /**
     * String representation
     * 
     * @return string String representation
     */
    public function __toString()
    {
        return sprintf('Source{name="%s", version="%s", type=%s}', 
            $this->name, 
            $this->version, 
            $this->type !== null ? $this->type : 'null'
        );
    }
}