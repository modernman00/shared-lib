<?php

declare(strict_types=1);

/**
 * Sovereign Cinematic Engine certification (ATG Phase 4).
 * Checks: zero cost, determinism, 50-job stability, memory growth, complexity scaling, hostile input.
 * Usage: php scripts/verify_cinematic_engine_certification.php   (exit 0 = certified)
 */

require __DIR__ . '/../vendor/autoload.php';

use Src\Cinematic\DirectorApi;
use Src\Cinematic\RenderRouter;
use Src\Exceptions\ValidationException;

$offline = new RenderRouter(static fn (string $a, array $p): ?array => null);
$failures = [];
$check = static function (string $name, bool $ok, string $detail = '') use (&$failures): void {
    echo ($ok ? '  ✅ ' : '  ❌ ') . $name . ($detail !== '' ? " ({$detail})" : '') . PHP_EOL;
    if (!$ok) {
        $failures[] = $name;
    }
};

/** @return array<string, mixed> */
$payload = static function (int $shots): array {
    $list = [];
    $beats = [];
    for ($i = 0; $i < $shots; $i++) {
        $list[] = ['id' => 's' . $i, 'durationSec' => 2.0 + ($i % 5) * 0.5, 'camera' => 'zoom_in', 'audioIntent' => 'dialogue', 'continuityKey' => 'k' . ($i % 4), 'prompt' => '@hero scene ' . $i];
        $beats[] = $i * 2.4;
    }
    return ['assets' => ['hero'], 'style' => 'storytelling', 'beats' => $beats, 'lock' => ['enabled' => true, 'characterId' => 'hero', 'styleSeed' => 11], 'shots' => $list];
};

echo "🎬 SOVEREIGN CINEMATIC ENGINE: CERTIFICATION" . PHP_EOL;

// 1. Zero cost and determinism
$a = DirectorApi::plan($payload(20), $offline);
$b = DirectorApi::plan($payload(20), $offline);
$check('Recurring cost is £0', $a['costGbp'] === 0.0);
$check('Deterministic output', $a === $b);

// 2. 50-job stability and memory growth
gc_collect_cycles();
$memStart = memory_get_usage();
$t0 = hrtime(true);
$ok = 0;
for ($i = 0; $i < 50; $i++) {
    $r = DirectorApi::plan($payload(40), $offline);
    if ($r['ok'] && count($r['renders']) === 40) {
        $ok++;
    }
}
$elapsedMs = (hrtime(true) - $t0) / 1e6;
gc_collect_cycles();
$growthKb = (memory_get_usage() - $memStart) / 1024;
$check('50/50 jobs completed', $ok === 50, "{$ok}/50 in " . round($elapsedMs) . ' ms');
$check('Memory growth under 512 KB after 50 jobs', $growthKb < 512, round($growthKb, 1) . ' KB');
$check('Mean job under 50 ms', $elapsedMs / 50 < 50.0, round($elapsedMs / 50, 2) . ' ms');

// 3. Complexity scaling: 4x shots must cost far less than 16x time (sub-quadratic)
$time = static function (int $n) use ($payload, $offline): float {
    $best = PHP_FLOAT_MAX;
    for ($k = 0; $k < 5; $k++) {
        $s = hrtime(true);
        DirectorApi::plan($payload($n), $offline);
        $best = min($best, (hrtime(true) - $s) / 1e6);
    }
    return $best;
};
$t15 = $time(15);
$t60 = $time(60);
$ratio = $t60 / max($t15, 0.001);
$check('Sub-quadratic scaling (60 vs 15 shots)', $ratio < 12.0, 'ratio ' . round($ratio, 1) . 'x');

// 4. Fallback resilience
$throwing = new RenderRouter(static function (string $a, array $p): ?array {
    throw new RuntimeException('gpu busy');
});
$f = DirectorApi::plan($payload(10), $throwing);
$check('Throwing sidecar falls back for every shot', $f['fallbackCount'] === 10);

// 5. Hostile input must be rejected with ValidationException (never a fatal)
$hostile = [
    'traversal tag'   => static fn (array $p): array => array_replace_recursive($p, ['shots' => [0 => ['prompt' => '@../../etc/passwd']]]),
    'unknown asset'   => static fn (array $p): array => array_replace_recursive($p, ['shots' => [0 => ['prompt' => '@root']]]),
    'huge duration'   => static fn (array $p): array => array_replace_recursive($p, ['shots' => [0 => ['durationSec' => 9999]]]),
    'bad camera'      => static fn (array $p): array => array_replace_recursive($p, ['shots' => [0 => ['camera' => '$(rm -rf /)']]]),
    'bad style'       => static fn (array $p): array => array_replace_recursive($p, ['style' => "x';DROP TABLE"]),
    'shot flood'      => static fn (array $p): array => $payload(61),
    'beat flood'      => static fn (array $p): array => array_replace($p, ['beats' => array_fill(0, 5001, 1.0)]),
    'bad identity id' => static fn (array $p): array => array_replace($p, ['lock' => ['characterId' => '../x']]),
    'biometric no consent' => static fn (array $p): array => array_replace($p, ['lock' => ['usesBiometricReference' => true, 'subjectUserId' => 'u1']]),
];
foreach ($hostile as $name => $mutate) {
    try {
        DirectorApi::plan($mutate($payload(3)), $offline);
        $check("Rejects {$name}", false, 'accepted');
    } catch (ValidationException) {
        $check("Rejects {$name}", true);
    } catch (Throwable $e) {
        $check("Rejects {$name}", false, get_class($e));
    }
}

echo PHP_EOL . ($failures === [] ? '🏆 CERTIFIED (exit 0)' : '⛔ NOT CERTIFIED: ' . implode(', ', $failures)) . PHP_EOL;
exit($failures === [] ? 0 : 1);
