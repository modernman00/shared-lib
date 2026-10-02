<?php

declare(strict_types=1);

namespace Src;

use Src\Exceptions\BadRequestException;

/**
 * Class BotGuard
 *
 * Enterprise Zero-Friction Bot and Spam Protection Service.
 * Protects form submissions and registration endpoints across all portfolio applications.
 */
class BotGuard
{
    /**
     * Standard honeypot field names used to detect automated headless bots.
     * @var array<string>
     */
    public const HONEYPOT_FIELDS = [
        'website_hp',
        'hp_username',
        'hp_email',
        'fax_number',
        'phone_number_hp',
        'company_website_trap',
    ];

    /**
     * Comprehensive hash map of known disposable, temporary, and bot email domains.
     * $O(1)$ lookup time.
     * @var array<string, bool>
     */
    private const DISPOSABLE_DOMAINS = [
        // Spam / automated test domains
        'formtests.info' => true,
        'belettersmail.com' => true,
        'mailinator.com' => true,
        'guerrillamail.com' => true,
        'guerrillamailblock.com' => true,
        'guerrillamail.net' => true,
        'guerrillamail.org' => true,
        'sharklasers.com' => true,
        'grr.la' => true,
        'pokemail.net' => true,
        'spam4.me' => true,
        'tempmail.com' => true,
        'temp-mail.org' => true,
        'temp-mail.io' => true,
        '10minutemail.com' => true,
        '10minutemail.net' => true,
        '20minutemail.com' => true,
        'trashmail.com' => true,
        'trashmail.net' => true,
        'trashmail.me' => true,
        'yopmail.com' => true,
        'yopmail.fr' => true,
        'yopmail.net' => true,
        'cool.fr.nf' => true,
        'jetable.fr.nf' => true,
        'courriel.fr.nf' => true,
        'moncourrier.fr.nf' => true,
        'monemail.fr.nf' => true,
        'monmail.fr.nf' => true,
        'dispostable.com' => true,
        'getairmail.com' => true,
        'throwawaymail.com' => true,
        'fakemailgenerator.com' => true,
        'crazymailing.com' => true,
        'mytemp.email' => true,
        'tempail.com' => true,
        'generator.email' => true,
        'maildrop.cc' => true,
        'inboxkitten.com' => true,
        'dropmail.me' => true,
        'mohmal.com' => true,
        'fakeinbox.com' => true,
        'emailondeck.com' => true,
        'nada.ltd' => true,
        'getnada.com' => true,
        'abyssmail.com' => true,
        'burnermail.io' => true,
        'disposablemail.com' => true,
        'tmpmail.net' => true,
        'tmpmail.org' => true,
        'tempinbox.com' => true,
        'instantemailaddress.com' => true,
        'luxusmail.org' => true,
        'crazymail.com' => true,
        'harakirimail.com' => true,
        'mytempmail.com' => true,
        'mytempemail.com' => true,
        'trash-mail.com' => true,
        'wegwerfmail.de' => true,
        'wegwerfmail.net' => true,
        'wegwerfmail.org' => true,
        'fastmail.fm' => true,
        'spambog.com' => true,
        'spambog.de' => true,
        'spambog.ru' => true,
        'mailcatch.com' => true,
        'binkmail.com' => true,
        'bobmail.info' => true,
        'chammy.info' => true,
        'devnullmail.com' => true,
        'letthemeatspam.com' => true,
        'mailinater.com' => true,
        'mailinator2.com' => true,
        'notmailinator.com' => true,
        'reallymymail.com' => true,
        'reconmail.com' => true,
        'safetymail.info' => true,
        'sendspamhere.com' => true,
        'sogetthis.com' => true,
        'suremail.info' => true,
        'thisisnotmyrealemail.com' => true,
        'tradermail.info' => true,
        'veryrealemail.com' => true,
        'zippymail.info' => true,
        'armyspy.com' => true,
        'cuvox.de' => true,
        'dayrep.com' => true,
        'einrot.com' => true,
        'fleckens.hu' => true,
        'gustr.com' => true,
        'jourrapide.com' => true,
        'rhyta.com' => true,
        'superrito.com' => true,
        'teleworm.us' => true,
    ];

    /**
     * Verify if any honeypot trap fields were populated by a bot.
     *
     * @param array<string, mixed> $input
     * @param array<string>|null $customHoneypotFields
     * @return bool True if a bot was detected, false otherwise.
     */
    public static function isBotHoneypotHit(array $input, ?array $customHoneypotFields = null): bool
    {
        $fieldsToCheck = $customHoneypotFields ?? self::HONEYPOT_FIELDS;

        foreach ($fieldsToCheck as $field) {
            if (isset($input[$field]) && trim((string)$input[$field]) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an email address belongs to a known disposable, temporary, or bot domain.
     *
     * @param string $email
     * @param bool $checkDns Whether to perform DNS MX record validation.
     * @return bool True if disposable/invalid, false if valid.
     */
    public static function isDisposableEmail(string $email, bool $checkDns = true): bool
    {
        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return true;
        }

        $domain = strtolower(trim($parts[1]));

        // 1. Direct O(1) Blocklist Lookup
        if (isset(self::DISPOSABLE_DOMAINS[$domain])) {
            return true;
        }

        // 2. Check for subdomains of blocked disposable domains (e.g. sub.mailinator.com)
        foreach (array_keys(self::DISPOSABLE_DOMAINS) as $blockedDomain) {
            if (str_ends_with($domain, '.' . $blockedDomain)) {
                return true;
            }
        }

        // 3. Optional DNS MX Validation for unknown domains (skipped in testing/CLI)
        if ($checkDns && !isTestEnv() && function_exists('checkdnsrr')) {
            // If domain has neither MX nor A records, it cannot receive email
            if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Full gatekeeper check on inbound registration or sensitive form submissions.
     * Throws a BadRequestException if a bot is detected or email is disposable.
     *
     * @param array<string, mixed> $input
     * @throws BadRequestException
     */
    public static function enforce(array $input): void
    {
        // 1. Check honeypot
        if (self::isBotHoneypotHit($input)) {
            // Log security incident
            \Src\AuditLogger::log('bot_honeypot_triggered', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ]);

            throw new BadRequestException('Security validation failed. Please try again.');
        }

        // 2. Check email if present in submission
        if (isset($input['email']) && is_string($input['email']) && trim($input['email']) !== '') {
            if (self::isDisposableEmail($input['email'])) {
                \Src\AuditLogger::log('disposable_email_blocked', [
                    'email' => $input['email'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);

                throw new BadRequestException('Please provide a valid permanent email address from an established provider.');
            }
        }
    }
}
