<?php

namespace ComplyanceSDK\Enums;

/**
 * Document type enumeration
 * 
 * @package ComplyanceSDK\Enums
 */
class DocumentType
{
    const TAX_INVOICE = 'TAX_INVOICE';
    const CREDIT_NOTE = 'CREDIT_NOTE';
    const DEBIT_NOTE = 'DEBIT_NOTE';
    const SIMPLIFIED_TAX_INVOICE = 'SIMPLIFIED_TAX_INVOICE';
    const SIMPLIFIED_CREDIT_NOTE = 'SIMPLIFIED_CREDIT_NOTE';
    const SIMPLIFIED_DEBIT_NOTE = 'SIMPLIFIED_DEBIT_NOTE';
    const RECEIPT = 'RECEIPT';
    const REFUND_RECEIPT = 'REFUND_RECEIPT';
    const SELF_BILLED_INVOICE = 'SELF_BILLED_INVOICE';
    const SUMMARY_DOCUMENT = 'SUMMARY_DOCUMENT';
    const CORRECTION_DOCUMENT = 'CORRECTION_DOCUMENT';
    const PREPAYMENT_INVOICE = 'PREPAYMENT_INVOICE';
    const PREPAYMENT_CREDIT_NOTE = 'PREPAYMENT_CREDIT_NOTE';
    const PREPAYMENT_DEBIT_NOTE = 'PREPAYMENT_DEBIT_NOTE';

    private $code;

    public function __construct($code)
    {
        $this->code = $code;
    }

    public static function from($code)
    {
        return new self($code);
    }

    public function getCode()
    {
        return $this->code;
    }

    /**
     * Get document type name
     * 
     * @param string $code Document type code
     * @return string Document type name
     */
    public static function getName($code)
    {
        $names = [
            self::TAX_INVOICE => 'Tax Invoice',
            self::CREDIT_NOTE => 'Credit Note',
            self::DEBIT_NOTE => 'Debit Note',
            self::SIMPLIFIED_TAX_INVOICE => 'Simplified Tax Invoice',
            self::SIMPLIFIED_CREDIT_NOTE => 'Simplified Credit Note',
            self::SIMPLIFIED_DEBIT_NOTE => 'Simplified Debit Note',
            self::RECEIPT => 'Receipt',
            self::REFUND_RECEIPT => 'Refund Receipt',
            self::SELF_BILLED_INVOICE => 'Self-Billed Invoice',
            self::SUMMARY_DOCUMENT => 'Summary Document',
            self::CORRECTION_DOCUMENT => 'Correction Document',
            self::PREPAYMENT_INVOICE => 'Prepayment Invoice',
            self::PREPAYMENT_CREDIT_NOTE => 'Prepayment Credit Note',
            self::PREPAYMENT_DEBIT_NOTE => 'Prepayment Debit Note',
        ];

        return isset($names[$code]) ? $names[$code] : 'Unknown';
    }

    /**
     * Check if document type is invoice
     * 
     * @param string $code Document type code
     * @return bool True if invoice
     */
    public static function isInvoice($code)
    {
        return in_array($code, [
            self::TAX_INVOICE,
            self::SIMPLIFIED_TAX_INVOICE,
            self::SELF_BILLED_INVOICE,
            self::PREPAYMENT_INVOICE
        ]);
    }

    /**
     * Check if document type is credit note
     * 
     * @param string $code Document type code
     * @return bool True if credit note
     */
    public static function isCreditNote($code)
    {
        return in_array($code, [
            self::CREDIT_NOTE,
            self::SIMPLIFIED_CREDIT_NOTE,
            self::PREPAYMENT_CREDIT_NOTE
        ]);
    }

    /**
     * Check if document type is debit note
     * 
     * @param string $code Document type code
     * @return bool True if debit note
     */
    public static function isDebitNote($code)
    {
        return in_array($code, [
            self::DEBIT_NOTE,
            self::SIMPLIFIED_DEBIT_NOTE,
            self::PREPAYMENT_DEBIT_NOTE
        ]);
    }

    /**
     * Check if document type is simplified
     * 
     * @param string $code Document type code
     * @return bool True if simplified
     */
    public static function isSimplified($code)
    {
        return in_array($code, [
            self::SIMPLIFIED_TAX_INVOICE,
            self::SIMPLIFIED_CREDIT_NOTE,
            self::SIMPLIFIED_DEBIT_NOTE
        ]);
    }

    /**
     * Get all document type codes
     * 
     * @return array Array of document type codes
     */
    public static function getAllCodes()
    {
        return [
            self::TAX_INVOICE, self::CREDIT_NOTE, self::DEBIT_NOTE,
            self::SIMPLIFIED_TAX_INVOICE, self::SIMPLIFIED_CREDIT_NOTE, self::SIMPLIFIED_DEBIT_NOTE,
            self::RECEIPT, self::REFUND_RECEIPT, self::SELF_BILLED_INVOICE,
            self::SUMMARY_DOCUMENT, self::CORRECTION_DOCUMENT,
            self::PREPAYMENT_INVOICE, self::PREPAYMENT_CREDIT_NOTE, self::PREPAYMENT_DEBIT_NOTE
        ];
    }

    /**
     * Check if document type code is valid
     * 
     * @param string $code Document type code
     * @return bool True if valid
     */
    public static function isValid($code)
    {
        return in_array($code, self::getAllCodes());
    }
}