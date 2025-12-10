<?php

namespace ComplyanceSDK\Models;

use ComplyanceSDK\Enums\Country;
use ComplyanceSDK\Enums\LogicalDocType;
use ComplyanceSDK\Enums\DocumentType;
use ComplyanceSDK\Exceptions\SDKException;
use ComplyanceSDK\Enums\ErrorCode;
use ComplyanceSDK\Models\ErrorDetail;

/**
 * Country policy registry for evaluating document type policies
 */
class CountryPolicyRegistry
{
    private static $policies = [];

    public static function init()
    {
        if (!empty(self::$policies)) {
            return; // Already initialized
        }

        // Initialize policies for all countries and all logical document types
        foreach (Country::getAllCodes() as $countryCode) {
            $country = Country::from($countryCode);
            $countryPolicies = [];
            
            // Add all logical document types for each country
            foreach (LogicalDocType::getAllCodes() as $logicalTypeCode) {
                $logicalType = LogicalDocType::from($logicalTypeCode);
                $policy = self::createPolicyForLogicalType($logicalType);
                $countryPolicies[$logicalTypeCode] = $policy;
            }
            
            self::$policies[$countryCode] = $countryPolicies;
        }
    }

    private static function createConfigMap($isExport, $isSelfBilled, $isThirdParty, $isNominalSupply, 
                                          $isSummary, $isB2B, $isPrepayment, $isAdjusted, $isReceipt)
    {
        $config = [];
        $config["isExport"] = $isExport;
        $config["isSelfBilled"] = $isSelfBilled;
        $config["isThirdParty"] = $isThirdParty;
        $config["isNominal"] = $isNominalSupply; // Changed from isNominalSupply to isNominal
        $config["isSummary"] = $isSummary;
        $config["isB2B"] = $isB2B;
        $config["isPrepayment"] = $isPrepayment;
        $config["isAdjusted"] = $isAdjusted;
        $config["isReceipt"] = $isReceipt;
        return $config;
    }

    private static function getDocumentTypeString($logicalType)
    {
        $typeName = $logicalType->getCode();
        if (strpos($typeName, "CREDIT_NOTE") !== false) {
            return "credit_note";
        } else if (strpos($typeName, "DEBIT_NOTE") !== false) {
            return "debit_note";
        } else {
            return "tax_invoice";
        }
    }

    private static function getMetaConfigDocumentType($logicalType)
    {
        $typeName = $logicalType->getCode();
        if (strpos($typeName, "CREDIT_NOTE") !== false) {
            return "credit_note";
        } else if (strpos($typeName, "DEBIT_NOTE") !== false) {
            return "debit_note";
        } else {
            return "tax_invoice";
        }
    }

    private static function createPolicyForLogicalType($logicalType)
    {
        $documentType = self::getDocumentTypeString($logicalType);
        $typeName = $logicalType->getCode();
        
        // Determine B2B vs B2C based on TAX_INVOICE vs SIMPLIFIED_TAX_INVOICE
        $isB2B = strpos($typeName, "TAX_INVOICE") === 0 || strpos($typeName, "SIMPLIFIED_TAX_INVOICE") === 0;
        if (strpos($typeName, "SIMPLIFIED_TAX_INVOICE") === 0) {
            $isB2B = false; // B2C
        } else if (strpos($typeName, "TAX_INVOICE") === 0) {
            $isB2B = true; // B2B
        }
        
        // Determine other flags based on type name
        $isExport = strpos($typeName, "EXPORT") !== false;
        $isSelfBilled = strpos($typeName, "SELF_BILLED") !== false;
        $isThirdParty = strpos($typeName, "THIRD_PARTY") !== false;
        $isNominalSupply = strpos($typeName, "NOMINAL_SUPPLY") !== false;
        $isSummary = strpos($typeName, "SUMMARY") !== false;
        $isPrepayment = strpos($typeName, "PREPAYMENT") !== false;
        $isAdjusted = strpos($typeName, "ADJUSTED") !== false;
        $isReceipt = strpos($typeName, "RECEIPT") !== false;
        
        // For legacy types, set appropriate defaults
        // if (strpos($typeName, "TAX_INVOICE") !== 0 && strpos($typeName, "SIMPLIFIED_TAX_INVOICE") !== 0) {
        //     // Legacy types - determine B2B based on context
        //     switch ($typeName) {
        //         case "INVOICE":
        //         case "EXPORT_INVOICE":
        //         case "EXPORT_THIRD_PARTY_INVOICE":
        //         case "THIRD_PARTY_INVOICE":
        //         case "SELF_BILLED_INVOICE":
        //         case "NOMINAL_SUPPLY_INVOICE":
        //         case "SUMMARY_INVOICE":
        //             $isB2B = true;
        //             break;
        //         default:
        //             $isB2B = false;
        //             break;
        //     }
        // }
        
        $config = self::createConfigMap($isExport, $isSelfBilled, $isThirdParty, 
                                       $isNominalSupply, $isSummary, $isB2B, $isPrepayment, $isAdjusted, $isReceipt);
        
        // Determine base DocumentType
        $baseType;
        if (strpos($typeName, "CREDIT_NOTE") !== false) {
            $baseType = DocumentType::from(DocumentType::CREDIT_NOTE);
        } else if (strpos($typeName, "DEBIT_NOTE") !== false) {
            $baseType = DocumentType::from(DocumentType::DEBIT_NOTE);
        } else {
            $baseType = DocumentType::from(DocumentType::TAX_INVOICE);
        }
        
        return new PolicyResult($baseType, $config, $documentType);
    }

    public static function evaluate($country, $logical)
    {
        self::init(); // Ensure policies are initialized
        
        $countryCode = $country->getCode();
        $logicalCode = $logical->getCode();
        
        if (!isset(self::$policies[$countryCode])) {
            throw new SDKException(new ErrorDetail(ErrorCode::INVALID_ARGUMENT, 
                "Country not supported", 
                "Country " . $countryCode . " is not supported"));
        }
        
        $byCountry = self::$policies[$countryCode];
        $result = isset($byCountry[$logicalCode]) ? $byCountry[$logicalCode] : null;
        
        if ($result === null) {
            $allowedTypes = array_keys($byCountry);
            throw new SDKException(new ErrorDetail(ErrorCode::INVALID_ARGUMENT, 
                "Document type not allowed for country", 
                "Allowed for " . $countryCode . ": " . implode(", ", $allowedTypes)));
        }
        
        return $result;
    }
}
