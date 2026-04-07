<?php

namespace ComplyanceSDK\Models;

/**
 * Canonical public GETS document type model.
 * Compatibility wrapper over GetsDocumentTypeV2.
 */
class GetsDocumentType
{
    private string $base = GetsDocumentBase::TAX_INVOICE;
    private array $modifiers = [];
    private ?string $variant = null;

    public static function builder(): GetsDocumentTypeBuilder
    {
        return new GetsDocumentTypeBuilder();
    }

    public function setBase(string $base): void
    {
        $this->base = strtolower(trim($base));
    }

    public function setModifiers(array $modifiers): void
    {
        $normalized = [];
        foreach ($modifiers as $modifier) {
            if (is_string($modifier) && trim($modifier) !== '') {
                $normalized[] = strtolower(trim($modifier));
            }
        }
        $this->modifiers = array_values(array_unique($normalized));
    }

    public function setVariant(?string $variant): void
    {
        $this->variant = ($variant === null || trim($variant) === '') ? null : strtolower(trim($variant));
    }

    public function getBase(): string
    {
        return $this->base;
    }

    public function getModifiers(): array
    {
        return $this->modifiers;
    }

    public function getVariant(): ?string
    {
        return $this->variant;
    }

    public function toArray(): array
    {
        $result = ['base' => $this->base];
        if (!empty($this->modifiers)) {
            $result['modifiers'] = $this->modifiers;
        }
        if ($this->variant !== null) {
            $result['variant'] = $this->variant;
        }
        return $result;
    }

    public function toV2(): GetsDocumentTypeV2
    {
        return GetsDocumentTypeV2::builder()
            ->base($this->base)
            ->modifiers($this->modifiers)
            ->variant($this->variant)
            ->build();
    }
}

class GetsDocumentTypeBuilder
{
    private GetsDocumentTypeV2Builder $delegate;

    public function __construct()
    {
        $this->delegate = GetsDocumentTypeV2::builder();
    }

    public function base($base): self
    {
        $this->delegate->base($base);
        return $this;
    }

    public function modifiers(array $modifiers): self
    {
        $this->delegate->modifiers($modifiers);
        return $this;
    }

    public function addModifier($modifier): self
    {
        $this->delegate->addModifier($modifier);
        return $this;
    }

    public function modifier($modifier): self
    {
        $this->delegate->modifier($modifier);
        return $this;
    }

    public function variant($variant): self
    {
        $this->delegate->variant($variant);
        return $this;
    }

    public function build(): GetsDocumentType
    {
        $v2 = $this->delegate->build();
        $type = new GetsDocumentType();
        $type->setBase($v2->getBase());
        $type->setModifiers($v2->getModifiers());
        $type->setVariant($v2->getVariant());
        return $type;
    }
}
