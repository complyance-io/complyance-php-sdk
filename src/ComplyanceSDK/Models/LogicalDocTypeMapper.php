<?php

namespace ComplyanceSDK\Models;

use ComplyanceSDK\Enums\LogicalDocType;

class LogicalDocTypeMapper
{
    public static function toGetsDocumentTypeV2(LogicalDocType $logicalType): GetsDocumentTypeV2
    {
        $name = $logicalType->getCode();

        $base = GetsDocumentBase::TAX_INVOICE;
        if (strpos($name, 'CREDIT_NOTE') !== false) {
            $base = GetsDocumentBase::CREDIT_NOTE;
        } elseif (strpos($name, 'DEBIT_NOTE') !== false) {
            $base = GetsDocumentBase::DEBIT_NOTE;
        } elseif (strpos($name, 'SIMPLIFIED') === 0) {
            $base = GetsDocumentBase::SIMPLIFIED_INVOICE;
        }

        $modifiers = [];
        if (strpos($name, 'SIMPLIFIED') === 0) {
            $modifiers[] = GetsDocumentModifier::B2C;
        }
        if (strpos($name, 'EXPORT') !== false) $modifiers[] = GetsDocumentModifier::EXPORT;
        if (strpos($name, 'SELF_BILLED') !== false) $modifiers[] = GetsDocumentModifier::SELF_BILLED;
        if (strpos($name, 'THIRD_PARTY') !== false) $modifiers[] = GetsDocumentModifier::THIRD_PARTY;
        if (strpos($name, 'NOMINAL_SUPPLY') !== false) $modifiers[] = GetsDocumentModifier::NOMINAL_SUPPLY;
        if (strpos($name, 'SUMMARY') !== false) $modifiers[] = GetsDocumentModifier::SUMMARY;
        if (strpos($name, 'B2G') !== false) $modifiers[] = GetsDocumentModifier::B2G;

        return GetsDocumentTypeV2::builder()
            ->base($base)
            ->modifiers($modifiers)
            ->build();
    }
}
