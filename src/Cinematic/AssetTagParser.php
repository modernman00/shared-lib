<?php

declare(strict_types=1);

namespace Src\Cinematic;

use Src\Exceptions\ValidationException;

/**
 * AssetTagParser
 *
 * Resolves `@tag` references in a prompt against an explicit allow-list of asset IDs.
 * Unknown tags are rejected (never silently passed through), so prompts cannot reach
 * arbitrary paths or inject instructions. Single linear scan: O(n).
 */
final class AssetTagParser
{
    public const MAX_PROMPT_LENGTH = 2000;
    public const MAX_TAGS = 12;

    /**
     * @param array<int, string> $allowedAssetIds
     * @return array{prompt: string, refs: array<int, string>}
     * @throws ValidationException
     */
    public static function parse(string $prompt, array $allowedAssetIds): array
    {
        if (strlen($prompt) > self::MAX_PROMPT_LENGTH) {
            throw new ValidationException('Prompt exceeds the maximum length.');
        }

        $allowed = [];
        foreach ($allowedAssetIds as $id) {
            $allowed[(string) $id] = true;
        }

        $refs = [];
        $matches = [];
        $found = (int) preg_match_all('/@([A-Za-z0-9_-]{1,64})(?![A-Za-z0-9_-])/', $prompt, $matches);
        // Every '@' must introduce a well-formed tag; malformed markers (e.g. "@../x") are rejected.
        if (substr_count($prompt, '@') !== $found) {
            throw new ValidationException('Malformed asset reference.');
        }
        foreach ($matches[1] as $tag) {
            $tag = (string) $tag;
            if (!isset($allowed[$tag])) {
                throw new ValidationException(sprintf('Unknown asset reference: @%s', $tag));
            }
            $refs[$tag] = $tag;
        }

        if (count($refs) > self::MAX_TAGS) {
            throw new ValidationException('Too many asset references in one shot.');
        }

        // Strip control characters and the tag markers; the clean prompt is safe to pass on.
        $clean = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $prompt);
        $clean = str_replace('@', '', $clean);

        return ['prompt' => trim($clean), 'refs' => array_values($refs)];
    }
}
