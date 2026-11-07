<?php

declare(strict_types=1);

namespace Src\Explainer;

use InvalidArgumentException;
use RuntimeException;

/**
 * ExplainerSecurityValidator
 *
 * DevSecOps security validator engineered by Marcus ("Ghost" Reinholt) and Sid Rao.
 * Enforces rigid input sanitisation, prevents command injection in headless renderers,
 * and neutralises SSRF/XSS vectors in programmatic SVG/DOM storyboards.
 */
class ExplainerSecurityValidator
{
    /** @var array<string> Blacklisted shell execution tokens */
    private const DANGEROUS_COMMAND_TOKENS = [
        ';', '`', '$(', '${', '--eval', '--exec', 'rm -rf', '&&', '||'
    ];

    /** @var array<string> Forbidden network and protocol schemes */
    private const FORBIDDEN_SCHEMES = [
        'file', 'phar', 'gopher', 'php', 'data', 'expect', 'glob', 'ssh', 'ftp'
    ];

    /** @var array<string> Disallowed private and cloud metadata IP ranges */
    private const FORBIDDEN_HOSTS = [
        '127.0.0.1', 'localhost', '::1', '0.0.0.0',
        '169.254.169.254', 'metadata.google.internal', 'instance-data'
    ];

    /**
     * Sanitises and validates human-readable storyboard text (titles, captions, narrations).
     *
     * @param string $input
     * @param int $maxLength
     * @return string
     * @throws InvalidArgumentException
     */
    public static function validateSafeText(string $input, int $maxLength = 1000): string
    {
        $trimmed = trim($input);
        if (mb_strlen($trimmed) > $maxLength) {
            throw new InvalidArgumentException(sprintf('Text exceeds maximum permissible length of %d characters.', $maxLength));
        }

        // Check for shell command execution injection tokens
        foreach (self::DANGEROUS_COMMAND_TOKENS as $token) {
            if (str_contains($trimmed, $token)) {
                throw new InvalidArgumentException(sprintf('Security policy violation: Prohibited token "%s" detected in input.', $token));
            }
        }

        // Neutralise control characters
        $cleaned = (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $trimmed);

        return $cleaned;
    }

    /**
     * Asserts that a URL is safe from SSRF vectors and does not target internal infrastructure.
     *
     * @param string $url
     * @return string
     * @throws InvalidArgumentException
     */
    public static function validateSafeUrl(string $url): string
    {
        $trimmed = trim($url);
        if (empty($trimmed)) {
            throw new InvalidArgumentException('URL cannot be empty.');
        }

        $parsed = parse_url($trimmed);
        if ($parsed === false || !isset($parsed['scheme'])) {
            throw new InvalidArgumentException('Invalid URL format.');
        }

        $scheme = strtolower($parsed['scheme']);
        if (in_array($scheme, self::FORBIDDEN_SCHEMES, true)) {
            throw new InvalidArgumentException(sprintf('Forbidden scheme "%s".', $scheme));
        }

        if (!in_array($scheme, ['https', 'http'], true)) {
            throw new InvalidArgumentException(sprintf('Forbidden scheme "%s". Only HTTP/HTTPS permitted.', $scheme));
        }

        if (!isset($parsed['host'])) {
            throw new InvalidArgumentException('Invalid URL host.');
        }

        $host = strtolower($parsed['host']);
        foreach (self::FORBIDDEN_HOSTS as $forbidden) {
            if ($host === $forbidden || str_ends_with($host, '.' . $forbidden)) {
                throw new InvalidArgumentException(sprintf('SSRF Protection: Access to host "%s" is strictly prohibited.', $host));
            }
        }

        // Check private IP ranges
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $isPrivate = !filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
            if ($isPrivate) {
                throw new InvalidArgumentException(sprintf('SSRF Protection: Access to private IP address "%s" is blocked.', $host));
            }
        }

        return $trimmed;
    }

    /**
     * Inspects SVG/HTML markup to eliminate malicious script tags, foreignObject SSRF, and event handlers.
     *
     * @param string $svgMarkup
     * @return string
     * @throws InvalidArgumentException
     */
    public static function sanitizeSvgMarkup(string $svgMarkup): string
    {
        $trimmed = trim($svgMarkup);
        if (empty($trimmed)) {
            return '';
        }

        $lower = strtolower($trimmed);

        $forbiddenTags = ['<script', '<iframe', '<object', '<embed', '<foreignobject', 'onload=', 'onerror=', 'onclick=', 'javascript:'];
        foreach ($forbiddenTags as $tag) {
            if (str_contains($lower, $tag)) {
                throw new InvalidArgumentException(sprintf('Security policy violation: Malicious token "%s" detected in visual markup.', $tag));
            }
        }

        return $trimmed;
    }

    /**
     * Validates an asset or export path against directory traversal attempts.
     *
     * @param string $filePath
     * @param string $baseDirectory
     * @return string
     * @throws InvalidArgumentException
     */
    public static function validateSafePath(string $filePath, string $baseDirectory): string
    {
        $realBase = realpath($baseDirectory);
        if ($realBase === false) {
            throw new RuntimeException(sprintf('Base directory "%s" does not exist.', $baseDirectory));
        }

        if (str_contains($filePath, "\0") || str_contains($filePath, '..')) {
            throw new InvalidArgumentException('Directory traversal attempt detected.');
        }

        $candidate = $realBase . DIRECTORY_SEPARATOR . ltrim($filePath, DIRECTORY_SEPARATOR);

        if (!str_starts_with($candidate, $realBase)) {
            throw new InvalidArgumentException('Directory traversal attempt detected.');
        }

        return $candidate;
    }
}
