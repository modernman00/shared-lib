<?php

declare(strict_types=1);

namespace Src\Cinematic;

use Src\Exceptions\ValidationException;
use Src\Media\SovereignVideoJobHandler;

/**
 * IdentityLock
 *
 * Additive wrapper over the existing colour continuity lock: it adds character, world and
 * brand-kit identity plus a per-shot deterministic seed, so every shot with the same
 * continuity key renders from the same identity. Biometric references (face or voice)
 * require explicit GDPR Article 9 consent.
 */
final class IdentityLock
{
    /**
     * @param array{enabled: bool, lutPreset: string, aspectRatio: string, vignette: float, styleSeed: int} $colourLock
     */
    private function __construct(
        private readonly array $colourLock,
        private readonly string $characterId,
        private readonly string $worldId,
        private readonly string $brandKitId,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @throws ValidationException
     */
    public static function fromArray(array $payload): self
    {
        $ids = [];
        foreach (['characterId', 'worldId', 'brandKitId'] as $key) {
            $value = (string) ($payload[$key] ?? '');
            if ($value !== '' && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $value) !== 1) {
                throw new ValidationException('Invalid identity field: ' . $key);
            }
            $ids[$key] = $value;
        }

        // Biometric references need Article 9 consent before any identity is locked.
        if ((bool) ($payload['usesBiometricReference'] ?? false)) {
            SovereignVideoJobHandler::validateBiometricVoiceConsent(
                (bool) ($payload['gdprArticle9Consent'] ?? false),
                (string) ($payload['subjectUserId'] ?? '')
            );
        }

        return new self(
            SovereignVideoJobHandler::validateContinuityLockPayload($payload),
            $ids['characterId'],
            $ids['worldId'],
            $ids['brandKitId']
        );
    }

    /**
     * Deterministic per-shot seed (same continuity key gives the same seed). O(len).
     */
    public function seedFor(string $continuityKey): int
    {
        return 1 + (crc32($this->colourLock['styleSeed'] . '|' . $continuityKey) % 999999);
    }

    /**
     * @return array{characterId: string, worldId: string, brandKitId: string, seed: int, lutPreset: string, aspectRatio: string, vignette: float, enabled: bool}
     */
    public function forShot(string $continuityKey): array
    {
        return [
            'characterId' => $this->characterId,
            'worldId'     => $this->worldId,
            'brandKitId'  => $this->brandKitId,
            'seed'        => $this->seedFor($continuityKey),
            'lutPreset'   => $this->colourLock['lutPreset'],
            'aspectRatio' => $this->colourLock['aspectRatio'],
            'vignette'    => $this->colourLock['vignette'],
            'enabled'     => $this->colourLock['enabled'],
        ];
    }
}
