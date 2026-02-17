<?php

namespace ComplyanceSDK\Enums;

/**
 * Logical document type enumeration
 * 
 * @package ComplyanceSDK\Enums
 */
class LogicalDocType
{
    const TAX_INVOICE = 'TAX_INVOICE';
    const TAX_INVOICE_CREDIT_NOTE = 'TAX_INVOICE_CREDIT_NOTE';
    const TAX_INVOICE_DEBIT_NOTE = 'TAX_INVOICE_DEBIT_NOTE';
    const TAX_INVOICE_PREPAYMENT = 'TAX_INVOICE_PREPAYMENT';
    const TAX_INVOICE_PREPAYMENT_ADJUSTED = 'TAX_INVOICE_PREPAYMENT_ADJUSTED';
    const TAX_INVOICE_EXPORT_INVOICE = 'TAX_INVOICE_EXPORT_INVOICE';
    const TAX_INVOICE_EXPORT_CREDIT_NOTE = 'TAX_INVOICE_EXPORT_CREDIT_NOTE';
    const TAX_INVOICE_EXPORT_DEBIT_NOTE = 'TAX_INVOICE_EXPORT_DEBIT_NOTE';
    const TAX_INVOICE_THIRD_PARTY_INVOICE = 'TAX_INVOICE_THIRD_PARTY_INVOICE';
    const TAX_INVOICE_SELF_BILLED_INVOICE = 'TAX_INVOICE_SELF_BILLED_INVOICE';
    const TAX_INVOICE_NOMINAL_SUPPLY_INVOICE = 'TAX_INVOICE_NOMINAL_SUPPLY_INVOICE';
    const TAX_INVOICE_SUMMARY_INVOICE = 'TAX_INVOICE_SUMMARY_INVOICE';

    const SIMPLIFIED_TAX_INVOICE = 'SIMPLIFIED_TAX_INVOICE';
    const SIMPLIFIED_TAX_INVOICE_CREDIT_NOTE = 'SIMPLIFIED_TAX_INVOICE_CREDIT_NOTE';
    const SIMPLIFIED_TAX_INVOICE_DEBIT_NOTE = 'SIMPLIFIED_TAX_INVOICE_DEBIT_NOTE';
    const SIMPLIFIED_TAX_INVOICE_PREPAYMENT = 'SIMPLIFIED_TAX_INVOICE_PREPAYMENT';
    const SIMPLIFIED_TAX_INVOICE_PREPAYMENT_ADJUSTED = 'SIMPLIFIED_TAX_INVOICE_PREPAYMENT_ADJUSTED';
    const SIMPLIFIED_TAX_INVOICE_EXPORT_INVOICE = 'SIMPLIFIED_TAX_INVOICE_EXPORT_INVOICE';
    const SIMPLIFIED_TAX_INVOICE_EXPORT_CREDIT_NOTE = 'SIMPLIFIED_TAX_INVOICE_EXPORT_CREDIT_NOTE';
    const SIMPLIFIED_TAX_INVOICE_EXPORT_DEBIT_NOTE = 'SIMPLIFIED_TAX_INVOICE_EXPORT_DEBIT_NOTE';
    const SIMPLIFIED_TAX_INVOICE_THIRD_PARTY_INVOICE = 'SIMPLIFIED_TAX_INVOICE_THIRD_PARTY_INVOICE';
    const SIMPLIFIED_TAX_INVOICE_SELF_BILLED_INVOICE = 'SIMPLIFIED_TAX_INVOICE_SELF_BILLED_INVOICE';
    const SIMPLIFIED_TAX_INVOICE_NOMINAL_SUPPLY_INVOICE = 'SIMPLIFIED_TAX_INVOICE_NOMINAL_SUPPLY_INVOICE';
    const SIMPLIFIED_TAX_INVOICE_SUMMARY_INVOICE = 'SIMPLIFIED_TAX_INVOICE_SUMMARY_INVOICE';

    const CREDIT_NOTE = 'CREDIT_NOTE';
    const SIMPLIFIED_CREDIT_NOTE = 'SIMPLIFIED_CREDIT_NOTE';
    const DEBIT_NOTE = 'DEBIT_NOTE';
    const SIMPLIFIED_DEBIT_NOTE = 'SIMPLIFIED_DEBIT_NOTE';
    const RECEIPT = 'RECEIPT';
    const REFUND_RECEIPT = 'REFUND_RECEIPT';
    const SELF_BILLED_INVOICE = 'SELF_BILLED_INVOICE';
    const SUMMARY_DOCUMENT = 'SUMMARY_DOCUMENT';
    const CORRECTION_DOCUMENT = 'CORRECTION_DOCUMENT';
    const PREPAYMENT_INVOICE = 'PREPAYMENT_INVOICE';
    const PREPAYMENT_CREDIT_NOTE = 'PREPAYMENT_CREDIT_NOTE';
    const PREPAYMENT_DEBIT_NOTE = 'PREPAYMENT_DEBIT_NOTE';
    const PREPAYMENT_ADJUSTED_INVOICE = 'PREPAYMENT_ADJUSTED_INVOICE';
    const SIMPLIFIED_PREPAYMENT_INVOICE = 'SIMPLIFIED_PREPAYMENT_INVOICE';
    const SIMPLIFIED_PREPAYMENT_ADJUSTED_INVOICE = 'SIMPLIFIED_PREPAYMENT_ADJUSTED_INVOICE';
    const EXPORT_INVOICE = 'EXPORT_INVOICE';
    const EXPORT_CREDIT_NOTE = 'EXPORT_CREDIT_NOTE';
    const EXPORT_THIRD_PARTY_INVOICE = 'EXPORT_THIRD_PARTY_INVOICE';
    const THIRD_PARTY_INVOICE = 'THIRD_PARTY_INVOICE';
    const SUMMARY_INVOICE = 'SUMMARY_INVOICE';
    const NOMINAL_SUPPLY_INVOICE = 'NOMINAL_SUPPLY_INVOICE';

    private $code;

    /**
     * Get logical document type name
     * 
     * @param string $code Logical document type code
     * @return string Logical document type name
     */
    public static function getName($code)
    {
        $names = [
            self::TAX_INVOICE => 'Tax Invoice',
            self::TAX_INVOICE_CREDIT_NOTE => 'Tax Invoice Credit Note',
            self::TAX_INVOICE_DEBIT_NOTE => 'Tax Invoice Debit Note',
            self::TAX_INVOICE_PREPAYMENT => 'Tax Invoice Prepayment',
            self::TAX_INVOICE_PREPAYMENT_ADJUSTED => 'Tax Invoice Prepayment Adjusted',
            self::TAX_INVOICE_EXPORT_INVOICE => 'Tax Invoice Export Invoice',
            self::TAX_INVOICE_EXPORT_CREDIT_NOTE => 'Tax Invoice Export Credit Note',
            self::TAX_INVOICE_EXPORT_DEBIT_NOTE => 'Tax Invoice Export Debit Note',
            self::TAX_INVOICE_THIRD_PARTY_INVOICE => 'Tax Invoice Third Party Invoice',
            self::TAX_INVOICE_SELF_BILLED_INVOICE => 'Tax Invoice Self-Billed Invoice',
            self::TAX_INVOICE_NOMINAL_SUPPLY_INVOICE => 'Tax Invoice Nominal Supply Invoice',
            self::TAX_INVOICE_SUMMARY_INVOICE => 'Tax Invoice Summary Invoice',
            self::SIMPLIFIED_TAX_INVOICE => 'Simplified Tax Invoice',
            self::SIMPLIFIED_TAX_INVOICE_CREDIT_NOTE => 'Simplified Tax Invoice Credit Note',
            self::SIMPLIFIED_TAX_INVOICE_DEBIT_NOTE => 'Simplified Tax Invoice Debit Note',
            self::SIMPLIFIED_TAX_INVOICE_PREPAYMENT => 'Simplified Tax Invoice Prepayment',
            self::SIMPLIFIED_TAX_INVOICE_PREPAYMENT_ADJUSTED => 'Simplified Tax Invoice Prepayment Adjusted',
            self::SIMPLIFIED_TAX_INVOICE_EXPORT_INVOICE => 'Simplified Export Invoice',
            self::SIMPLIFIED_TAX_INVOICE_EXPORT_CREDIT_NOTE => 'Simplified Export Credit Note',
            self::SIMPLIFIED_TAX_INVOICE_EXPORT_DEBIT_NOTE => 'Simplified Export Debit Note',
            self::SIMPLIFIED_TAX_INVOICE_THIRD_PARTY_INVOICE => 'Simplified Third Party Invoice',
            self::SIMPLIFIED_TAX_INVOICE_SELF_BILLED_INVOICE => 'Simplified Self-Billed Invoice',
            self::SIMPLIFIED_TAX_INVOICE_NOMINAL_SUPPLY_INVOICE => 'Simplified Nominal Supply Invoice',
            self::SIMPLIFIED_TAX_INVOICE_SUMMARY_INVOICE => 'Simplified Summary Invoice',
            self::CREDIT_NOTE => 'Credit Note',
            self::SIMPLIFIED_CREDIT_NOTE => 'Simplified Credit Note',
            self::DEBIT_NOTE => 'Debit Note',
            self::SIMPLIFIED_DEBIT_NOTE => 'Simplified Debit Note',
            self::RECEIPT => 'Receipt',
            self::REFUND_RECEIPT => 'Refund Receipt',
            self::SELF_BILLED_INVOICE => 'Self-Billed Invoice',
            self::SUMMARY_DOCUMENT => 'Summary Document',
            self::CORRECTION_DOCUMENT => 'Correction Document',
            self::PREPAYMENT_INVOICE => 'Prepayment Invoice',
            self::PREPAYMENT_CREDIT_NOTE => 'Prepayment Credit Note',
            self::PREPAYMENT_DEBIT_NOTE => 'Prepayment Debit Note',
            self::PREPAYMENT_ADJUSTED_INVOICE => 'Prepayment Adjusted Invoice',
            self::SIMPLIFIED_PREPAYMENT_INVOICE => 'Simplified Prepayment Invoice',
            self::SIMPLIFIED_PREPAYMENT_ADJUSTED_INVOICE => 'Simplified Prepayment Adjusted Invoice',
            self::EXPORT_INVOICE => 'Export Invoice',
            self::EXPORT_CREDIT_NOTE => 'Export Credit Note',
            self::EXPORT_THIRD_PARTY_INVOICE => 'Export Third Party Invoice',
            self::THIRD_PARTY_INVOICE => 'Third Party Invoice',
            self::SUMMARY_INVOICE => 'Summary Invoice',
            self::NOMINAL_SUPPLY_INVOICE => 'Nominal Supply Invoice',
        ];

        return isset($names[$code]) ? $names[$code] : 'Unknown';
    }

    /**
     * Create LogicalDocType instance from string
     * 
     * @param string $code Logical document type code
     * @return LogicalDocType LogicalDocType instance
     */
    public static function from($code)
    {
        $instance = new self();
        $instance->code = $code;
        return $instance;
    }

    /**
     * Get the logical document type code
     * 
     * @return string Logical document type code
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Get the base document type for this logical type
     * 
     * @return DocumentType
     */
    public function getBaseDocumentType(): DocumentType
    {
        $taxInvoiceCodes = [
            self::TAX_INVOICE,
            self::TAX_INVOICE_PREPAYMENT,
            self::TAX_INVOICE_PREPAYMENT_ADJUSTED,
            self::TAX_INVOICE_EXPORT_INVOICE,
            self::TAX_INVOICE_THIRD_PARTY_INVOICE,
            self::TAX_INVOICE_NOMINAL_SUPPLY_INVOICE,
            self::TAX_INVOICE_SUMMARY_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_PREPAYMENT,
            self::SIMPLIFIED_TAX_INVOICE_PREPAYMENT_ADJUSTED,
            self::SIMPLIFIED_TAX_INVOICE_EXPORT_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_THIRD_PARTY_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_NOMINAL_SUPPLY_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_SUMMARY_INVOICE,
            self::EXPORT_INVOICE,
            self::THIRD_PARTY_INVOICE,
            self::SUMMARY_INVOICE,
            self::NOMINAL_SUPPLY_INVOICE
        ];

        if (in_array($this->code, $taxInvoiceCodes, true)) {
            return DocumentType::from(DocumentType::TAX_INVOICE);
        }

        $simplifiedInvoiceCodes = [
            self::SIMPLIFIED_TAX_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_PREPAYMENT,
            self::SIMPLIFIED_TAX_INVOICE_PREPAYMENT_ADJUSTED,
            self::SIMPLIFIED_TAX_INVOICE_EXPORT_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_THIRD_PARTY_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_NOMINAL_SUPPLY_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_SUMMARY_INVOICE
        ];

        if (in_array($this->code, $simplifiedInvoiceCodes, true)) {
            return DocumentType::from(DocumentType::SIMPLIFIED_TAX_INVOICE);
        }

        $creditNoteCodes = [
            self::TAX_INVOICE_CREDIT_NOTE,
            self::TAX_INVOICE_EXPORT_CREDIT_NOTE,
            self::SIMPLIFIED_TAX_INVOICE_CREDIT_NOTE,
            self::SIMPLIFIED_TAX_INVOICE_EXPORT_CREDIT_NOTE,
            self::CREDIT_NOTE,
            self::SIMPLIFIED_CREDIT_NOTE,
            self::PREPAYMENT_CREDIT_NOTE,
            self::EXPORT_CREDIT_NOTE
        ];

        if (in_array($this->code, $creditNoteCodes, true)) {
            return DocumentType::from(DocumentType::CREDIT_NOTE);
        }

        $debitNoteCodes = [
            self::TAX_INVOICE_DEBIT_NOTE,
            self::TAX_INVOICE_EXPORT_DEBIT_NOTE,
            self::SIMPLIFIED_TAX_INVOICE_DEBIT_NOTE,
            self::SIMPLIFIED_TAX_INVOICE_EXPORT_DEBIT_NOTE,
            self::DEBIT_NOTE,
            self::SIMPLIFIED_DEBIT_NOTE,
            self::PREPAYMENT_DEBIT_NOTE
        ];

        if (in_array($this->code, $debitNoteCodes, true)) {
            return DocumentType::from(DocumentType::DEBIT_NOTE);
        }

        $prepaymentCodes = [
            self::PREPAYMENT_INVOICE,
            self::PREPAYMENT_ADJUSTED_INVOICE,
            self::SIMPLIFIED_PREPAYMENT_INVOICE,
            self::SIMPLIFIED_PREPAYMENT_ADJUSTED_INVOICE
        ];

        if (in_array($this->code, $prepaymentCodes, true)) {
            return DocumentType::from(DocumentType::PREPAYMENT_INVOICE);
        }

        if ($this->code === self::SELF_BILLED_INVOICE || $this->code === self::TAX_INVOICE_SELF_BILLED_INVOICE || $this->code === self::SIMPLIFIED_TAX_INVOICE_SELF_BILLED_INVOICE) {
            return DocumentType::from(DocumentType::SELF_BILLED_INVOICE);
        }

        return DocumentType::from(DocumentType::TAX_INVOICE);
    }

    /**
     * Check if logical document type is invoice
     * 
     * @param string $code Logical document type code
     * @return bool True if invoice
     */
    public static function isInvoice($code)
    {
        return in_array($code, [
            self::TAX_INVOICE,
            self::TAX_INVOICE_PREPAYMENT,
            self::TAX_INVOICE_PREPAYMENT_ADJUSTED,
            self::TAX_INVOICE_EXPORT_INVOICE,
            self::TAX_INVOICE_THIRD_PARTY_INVOICE,
            self::TAX_INVOICE_NOMINAL_SUPPLY_INVOICE,
            self::TAX_INVOICE_SUMMARY_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_PREPAYMENT,
            self::SIMPLIFIED_TAX_INVOICE_PREPAYMENT_ADJUSTED,
            self::SIMPLIFIED_TAX_INVOICE_EXPORT_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_THIRD_PARTY_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_NOMINAL_SUPPLY_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_SUMMARY_INVOICE,
            self::PREPAYMENT_INVOICE,
            self::PREPAYMENT_ADJUSTED_INVOICE,
            self::SIMPLIFIED_PREPAYMENT_INVOICE,
            self::SIMPLIFIED_PREPAYMENT_ADJUSTED_INVOICE,
            self::SELF_BILLED_INVOICE,
            self::TAX_INVOICE_SELF_BILLED_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_SELF_BILLED_INVOICE
        ]);
    }

    /**
     * Check if logical document type is credit note
     * 
     * @param string $code Logical document type code
     * @return bool True if credit note
     */
    public static function isCreditNote($code)
    {
        return in_array($code, [
            self::CREDIT_NOTE,
            self::SIMPLIFIED_CREDIT_NOTE,
            self::PREPAYMENT_CREDIT_NOTE,
            self::TAX_INVOICE_CREDIT_NOTE,
            self::TAX_INVOICE_EXPORT_CREDIT_NOTE,
            self::SIMPLIFIED_TAX_INVOICE_CREDIT_NOTE,
            self::SIMPLIFIED_TAX_INVOICE_EXPORT_CREDIT_NOTE,
            self::EXPORT_CREDIT_NOTE
        ]);
    }

    /**
     * Check if logical document type is debit note
     * 
     * @param string $code Logical document type code
     * @return bool True if debit note
     */
    public static function isDebitNote($code)
    {
        return in_array($code, [
            self::DEBIT_NOTE,
            self::SIMPLIFIED_DEBIT_NOTE,
            self::PREPAYMENT_DEBIT_NOTE,
            self::TAX_INVOICE_DEBIT_NOTE,
            self::TAX_INVOICE_EXPORT_DEBIT_NOTE,
            self::SIMPLIFIED_TAX_INVOICE_DEBIT_NOTE,
            self::SIMPLIFIED_TAX_INVOICE_EXPORT_DEBIT_NOTE
        ]);
    }

    /**
     * Check if logical document type is simplified
     * 
     * @param string $code Logical document type code
     * @return bool True if simplified
     */
    public static function isSimplified($code)
    {
        return in_array($code, [
            self::SIMPLIFIED_TAX_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_PREPAYMENT,
            self::SIMPLIFIED_TAX_INVOICE_PREPAYMENT_ADJUSTED,
            self::SIMPLIFIED_TAX_INVOICE_EXPORT_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_EXPORT_CREDIT_NOTE,
            self::SIMPLIFIED_TAX_INVOICE_EXPORT_DEBIT_NOTE,
            self::SIMPLIFIED_TAX_INVOICE_THIRD_PARTY_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_SELF_BILLED_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_NOMINAL_SUPPLY_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_SUMMARY_INVOICE,
            self::SIMPLIFIED_CREDIT_NOTE,
            self::SIMPLIFIED_DEBIT_NOTE,
            self::SIMPLIFIED_PREPAYMENT_INVOICE,
            self::SIMPLIFIED_PREPAYMENT_ADJUSTED_INVOICE
        ]);
    }


    /**
     * Get all logical document type codes
     * 
     * @return array Array of logical document type codes
     */
    public static function getAllCodes()
    {
        return [
            self::TAX_INVOICE,
            self::TAX_INVOICE_CREDIT_NOTE,
            self::TAX_INVOICE_DEBIT_NOTE,
            self::TAX_INVOICE_PREPAYMENT,
            self::TAX_INVOICE_PREPAYMENT_ADJUSTED,
            self::TAX_INVOICE_EXPORT_INVOICE,
            self::TAX_INVOICE_EXPORT_CREDIT_NOTE,
            self::TAX_INVOICE_EXPORT_DEBIT_NOTE,
            self::TAX_INVOICE_THIRD_PARTY_INVOICE,
            self::TAX_INVOICE_SELF_BILLED_INVOICE,
            self::TAX_INVOICE_NOMINAL_SUPPLY_INVOICE,
            self::TAX_INVOICE_SUMMARY_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_CREDIT_NOTE,
            self::SIMPLIFIED_TAX_INVOICE_DEBIT_NOTE,
            self::SIMPLIFIED_TAX_INVOICE_PREPAYMENT,
            self::SIMPLIFIED_TAX_INVOICE_PREPAYMENT_ADJUSTED,
            self::SIMPLIFIED_TAX_INVOICE_EXPORT_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_EXPORT_CREDIT_NOTE,
            self::SIMPLIFIED_TAX_INVOICE_EXPORT_DEBIT_NOTE,
            self::SIMPLIFIED_TAX_INVOICE_THIRD_PARTY_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_SELF_BILLED_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_NOMINAL_SUPPLY_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE_SUMMARY_INVOICE,
            self::CREDIT_NOTE,
            self::SIMPLIFIED_CREDIT_NOTE,
            self::DEBIT_NOTE,
            self::SIMPLIFIED_DEBIT_NOTE,
            self::PREPAYMENT_INVOICE,
            self::PREPAYMENT_ADJUSTED_INVOICE,
            self::SIMPLIFIED_PREPAYMENT_INVOICE,
            self::SIMPLIFIED_PREPAYMENT_ADJUSTED_INVOICE,
            self::PREPAYMENT_CREDIT_NOTE,
            self::PREPAYMENT_DEBIT_NOTE,
            self::EXPORT_INVOICE,
            self::EXPORT_CREDIT_NOTE,
            self::EXPORT_THIRD_PARTY_INVOICE,
            self::THIRD_PARTY_INVOICE,
            self::SELF_BILLED_INVOICE,
            self::SUMMARY_INVOICE,
            self::NOMINAL_SUPPLY_INVOICE,
            self::RECEIPT,
            self::REFUND_RECEIPT,
            self::SUMMARY_DOCUMENT,
            self::CORRECTION_DOCUMENT
        ];
    }

    /**
     * Check if logical document type code is valid
     * 
     * @param string $code Logical document type code
     * @return bool True if valid
     */
    public static function isValid($code)
    {
        return in_array($code, self::getAllCodes());
    }
}