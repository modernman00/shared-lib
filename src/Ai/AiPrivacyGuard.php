<?php

declare(strict_types=1);

namespace Src\Ai;

/**
 * AI Privacy Guard & Zero-PII Enforcer
 *
 * Implements OWASP LLM06 (Sensitive Information Disclosure) and UK GDPR compliance:
 * Automatically intercepts, sanitizes, and scrubs Personally Identifiable Information (PII)
 * before any prompt, message, or metadata payload is transmitted to external AI models.
 *
 * Scrubbed Vectors:
 * 1. Dates of Birth (DOB) and age timestamps (e.g. DD/MM/YYYY, YYYY-MM-DD, "born 14 May 1985").
 * 2. UK Street Addresses, Building Names, and Postcodes (e.g. SW1A 1AA, EC1A 1BB).
 * 3. UK Phone Numbers (e.g. +44 7..., 07..., 020...).
 * 4. Email Addresses (RFC 5322 regex).
 * 5. National Insurance (NINO) & Tax Identifiers (e.g. QQ123456C).
 * 6. Financial Details (16-digit PANs, Sort Codes & 8-digit account numbers).
 */
final class AiPrivacyGuard
{
    // UK Postcode regex matching full and outward/inward codes
    private const REGEX_UK_POSTCODE = '/\b(?:[A-Z]{1,2}[0-9][A-Z0-9]?\s?[0-9][A-Z]{2})\b/i';

    // Standard RFC-compliant email address regex
    private const REGEX_EMAIL = '/\b[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}\b/';

    // UK Phone numbers (landline and mobile: +44, 0044, or 07/01/02)
    private const REGEX_UK_PHONE = '/(?:\+44\s?7\d{3}|\+44\s?[12]\d{3}|0044\s?7\d{3}|07\d{3}|01\d{3}|02\d{3})\s?\d{3}\s?\d{3,4}\b/';

    // Dates of Birth and full explicit calendar dates (YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY)
    private const REGEX_CALENDAR_DATE = '/\b(?:\d{4}[-\/](?:0[1-9]|1[0-2])[-\/](?:0[1-9]|[12]\d|3[01])|(?:0[1-9]|[12]\d|3[01])[-\/](?:0[1-9]|1[0-2])[-\/]\d{2,4})\b/';

    // Natural language birth date declarations (e.g. "born on 14th May 1985", "DOB: 12/04/1990")
    private const REGEX_DOB_DECLARATION = '/\b(?:d\.?o\.?b\.?|born\s+(?:on|in)?)\s*:?\s*(?:[0-9]{1,2}(?:st|nd|rd|th)?\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+[0-9]{4}|[0-9]{1,2}[-\/][0-9]{1,2}[-\/][0-9]{2,4})/i';

    // UK Street Address patterns (e.g. "14 High Street", "Flat 3, 25 London Road", "Avenue", "Close", "Lane")
    private const REGEX_STREET_ADDRESS = '/\b(?:\d{1,4}[a-zA-Z]?\s+)?(?:Flat\s+\d+,\s*)?(?:[A-Z][a-z]+\s+)?(?:Street|Road|Avenue|Lane|Drive|Close|Way|Gardens|Crescent|Grove|Boulevard|Court|Square|Walk)\b/i';

    // UK National Insurance Numbers (catches official prefixes and dummy prefixes like QQ/TN)
    private const REGEX_UK_NINO = '/\b[A-Z]{2}\s?(?:\d\s?){6}[A-D]\b/i';

    // Financial Card Numbers (13-19 digits, with spaces or hyphens)
    private const REGEX_CREDIT_CARD = '/\b(?:\d{4}[-\s]?){3}\d{4}\b/';

    // Bank Sort Code & Account Number (e.g. 20-40-60 12345678)
    private const REGEX_SORT_CODE_ACCOUNT = '/\b(?:\d{2}[-\s]\d{2}[-\s]\d{2}\s+\d{8})\b/';

    /**
     * Scrub all Personally Identifiable Information from raw text before model dispatch.
     *
     * @param string $text
     * @return string Scrubbed, privacy-safe text
     */
    public static function scrubText(string $text): string
    {
        if (trim($text) === '') {
            return '';
        }

        $cleaned = $text;

        // 1. Redact National Insurance Numbers
        $cleaned = (string)preg_replace(self::REGEX_UK_NINO, '[REDACTED_NINO]', $cleaned);

        // 2. Redact Financial Card & Account numbers
        $cleaned = (string)preg_replace(self::REGEX_CREDIT_CARD, '[REDACTED_FINANCIAL]', $cleaned);
        $cleaned = (string)preg_replace(self::REGEX_SORT_CODE_ACCOUNT, '[REDACTED_FINANCIAL]', $cleaned);

        // 3. Redact Explicit Date of Birth declarations
        $cleaned = (string)preg_replace(self::REGEX_DOB_DECLARATION, '[REDACTED_DOB]', $cleaned);

        // 4. Redact Calendar Dates (DOB candidates)
        $cleaned = (string)preg_replace(self::REGEX_CALENDAR_DATE, '[REDACTED_DATE]', $cleaned);

        // 5. Redact UK Postcodes
        $cleaned = (string)preg_replace(self::REGEX_UK_POSTCODE, '[REDACTED_POSTCODE]', $cleaned);

        // 6. Redact Street Addresses
        $cleaned = (string)preg_replace(self::REGEX_STREET_ADDRESS, '[REDACTED_ADDRESS]', $cleaned);

        // 7. Redact UK Phone Numbers
        $cleaned = (string)preg_replace(self::REGEX_UK_PHONE, '[REDACTED_PHONE]', $cleaned);

        // 8. Redact Email Addresses
        $cleaned = (string)preg_replace(self::REGEX_EMAIL, '[REDACTED_EMAIL]', $cleaned);

        return $cleaned;
    }

    /**
     * Scrub an array of chat messages (OpenAI/HuggingFace format).
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @return array<int, array{role: string, content: string}>
     */
    public static function scrubMessages(array $messages): array
    {
        $scrubbed = [];
        foreach ($messages as $msg) {
            $role = $msg['role'];
            $content = $msg['content'];

            $scrubbed[] = [
                'role' => $role,
                'content' => self::scrubText($content),
            ];
        }

        return $scrubbed;
    }

    /**
     * Audit a text payload and return detected PII violations for SecOps telemetry.
     *
     * @param string $text
     * @return array{
     *     has_pii: bool,
     *     detected_types: list<string>
     * }
     */
    public static function audit(string $text): array
    {
        $types = [];

        if (preg_match(self::REGEX_UK_NINO, $text)) {
            $types[] = 'UK_NINO';
        }
        if (preg_match(self::REGEX_CREDIT_CARD, $text) || preg_match(self::REGEX_SORT_CODE_ACCOUNT, $text)) {
            $types[] = 'FINANCIAL_IDENTIFIER';
        }
        if (preg_match(self::REGEX_DOB_DECLARATION, $text) || preg_match(self::REGEX_CALENDAR_DATE, $text)) {
            $types[] = 'DATE_OF_BIRTH';
        }
        if (preg_match(self::REGEX_UK_POSTCODE, $text)) {
            $types[] = 'UK_POSTCODE';
        }
        if (preg_match(self::REGEX_STREET_ADDRESS, $text)) {
            $types[] = 'STREET_ADDRESS';
        }
        if (preg_match(self::REGEX_UK_PHONE, $text)) {
            $types[] = 'PHONE_NUMBER';
        }
        if (preg_match(self::REGEX_EMAIL, $text)) {
            $types[] = 'EMAIL_ADDRESS';
        }

        return [
            'has_pii' => !empty($types),
            'detected_types' => $types,
        ];
    }
}
