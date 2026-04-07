<?php

namespace ComplyanceSDK\Models;

class GetsDocumentBase
{
    public const TAX_INVOICE = 'tax_invoice';
    public const SIMPLIFIED_INVOICE = 'simplified_invoice';
    public const CREDIT_NOTE = 'credit_note';
    public const DEBIT_NOTE = 'debit_note';
}

class GetsDocumentModifier
{
    public const B2B = 'b2b';
    public const B2C = 'b2c';
    public const B2G = 'b2g';
    public const EXPORT = 'export';
    public const SELF_BILLED = 'self_billed';
    public const THIRD_PARTY = 'third_party';
    public const NOMINAL = 'nominal';
    public const NOMINAL_SUPPLY = 'nominal_supply';
    public const SUMMARY = 'summary';
    public const PREPAYMENT = 'prepayment';
    public const ADJUSTED = 'adjusted';
    public const RECEIPT = 'receipt';
    public const ZERO_RATED = 'zero_rated';
    public const REVERSE_CHARGE = 'reverse_charge';
    public const CONTINUOUS_SUPPLY = 'continuous_supply';
    public const FREE_TRADE_ZONE = 'free_trade_zone';
    public const INTRA_COMMUNITY_SUPPLY = 'intra_community_supply';
    public const CONSOLIDATED = 'consolidated';
}

class GetsDocumentVariant
{
    public const STANDARD = 'standard';
    public const PARTIAL = 'partial';
    public const PARTIAL_CONSTRUCTION = 'partial_construction';
    public const PARTIAL_FINAL_CONSTRUCTION = 'partial_final_construction';
    public const FINAL_CONSTRUCTION = 'final_construction';
}

class BaseValue
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = strtolower(trim($value));
    }

    public function getValue(): string
    {
        return $this->value;
    }
}

class ModifierValue
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = strtolower(trim($value));
    }

    public function getValue(): string
    {
        return $this->value;
    }
}

class VariantValue
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = strtolower(trim($value));
    }

    public function getValue(): string
    {
        return $this->value;
    }
}

class GetsDocumentTypeV2
{
    private string $base = GetsDocumentBase::TAX_INVOICE;
    private array $modifiers = [];
    private ?string $variant = null;

    public static function builder(): GetsDocumentTypeV2Builder
    {
        return new GetsDocumentTypeV2Builder();
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
}

class GetsDocumentTypeV2Builder
{
    private GetsDocumentTypeV2 $value;

    public function __construct()
    {
        $this->value = new GetsDocumentTypeV2();
    }

    public function base($base): self
    {
        if ($base instanceof BaseValue) {
            $this->value->setBase($base->getValue());
            return $this;
        }

        $this->value->setBase((string)$base);
        return $this;
    }

    public function modifiers(array $modifiers): self
    {
        $normalized = [];
        foreach ($modifiers as $modifier) {
            if ($modifier instanceof ModifierValue) {
                $normalized[] = $modifier->getValue();
            } else {
                $normalized[] = (string)$modifier;
            }
        }
        $this->value->setModifiers($normalized);
        return $this;
    }

    public function addModifier($modifier): self
    {
        if ($modifier instanceof ModifierValue) {
            $modifier = $modifier->getValue();
        }

        if (!is_string($modifier) || trim($modifier) === '') {
            return $this;
        }

        $existing = $this->value->getModifiers();
        $existing[] = $modifier;
        $this->value->setModifiers($existing);
        return $this;
    }

    public function modifier($modifier): self
    {
        return $this->addModifier($modifier);
    }

    public function variant($variant): self
    {
        if ($variant instanceof VariantValue) {
            $this->value->setVariant($variant->getValue());
            return $this;
        }

        $this->value->setVariant($variant === null ? null : (string)$variant);
        return $this;
    }

    public function build(): GetsDocumentTypeV2
    {
        return $this->value;
    }
}
