<?php

declare(strict_types=1);

namespace Src\Cinematic;

use RuntimeException;
use Src\Exceptions\ValidationException;
use Src\Media\SovereignVideoJobHandler;

/**
 * ShotRenderer
 *
 * Turns a planned film (ShotGraph + AudioFirstPlanner timeline + IdentityLock) into a real MP4 using
 * only local FFmpeg: a seeded gradient "world" per scene, a camera move per shot, the lock's colour
 * grade, crossfades between shots, and a silent AAC track. Captions are written as an SRT sidecar.
 *
 * Zero SaaS, zero cost. No shell: argv arrays only. Hard watchdog on the whole job and a private
 * temp sandbox that is always removed.
 */
final class ShotRenderer
{
    public const FPS = 30;
    public const MAX_FILM_SECONDS = 300.0;
    public const DEFAULT_DEADLINE_SECONDS = 240.0;
    private const MAX_OVERLAP_SEC = 0.5;

    private ?string $ffmpeg;

    public function __construct(?string $ffmpegPath = null, private readonly float $deadlineSeconds = self::DEFAULT_DEADLINE_SECONDS)
    {
        $this->ffmpeg = $ffmpegPath ?? self::detectFfmpeg();
    }

    public static function detectFfmpeg(): ?string
    {
        foreach (['/opt/homebrew/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/usr/bin/ffmpeg'] as $p) {
            if (is_file($p) && is_executable($p)) {
                return $p;
            }
        }

        return null;
    }

    /**
     * @param array<int, array{shotId: string, startSec: float, endSec: float, durationSec: float, snapped: bool}> $timeline
     * @return array{status: string, videoPath: ?string, srtPath: string, durationSec: float, clips: int, costGbp: float, elapsedMs: float, reason?: string}
     * @throws ValidationException
     */
    public function renderFilm(
        ShotGraph $graph,
        array $timeline,
        IdentityLock $lock,
        string $outputDir,
        string $baseName = 'film',
        int $width = 1280,
        int $height = 720
    ): array {
        $t0 = hrtime(true);
        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $baseName) !== 1) {
            throw new ValidationException('Invalid output name.');
        }
        $dir = realpath($outputDir);
        if ($dir === false || !is_dir($dir) || !is_writable($dir) || str_contains($outputDir, "\0")) {
            throw new ValidationException('Output directory is not available.');
        }
        $dims = SovereignVideoJobHandler::validateDimensions($width, $height);

        $shots = $graph->getShots();
        if (count($timeline) !== count($shots)) {
            throw new ValidationException('Timeline does not match the shot list.');
        }
        $total = 0.0;
        foreach ($timeline as $row) {
            $total += $row['durationSec'];
        }
        if ($total <= 0.0 || $total > self::MAX_FILM_SECONDS) {
            throw new ValidationException('Film length must be between 0 and ' . (int) self::MAX_FILM_SECONDS . ' seconds.');
        }

        $srtPath = $dir . DIRECTORY_SEPARATOR . $baseName . '.srt';
        file_put_contents($srtPath, self::buildSrt($shots, $timeline));

        $ffmpeg = $this->ffmpeg;
        if ($ffmpeg === null || $ffmpeg === '' || !is_file($ffmpeg) || !is_executable($ffmpeg)) {
            return ['status' => 'fallback', 'videoPath' => null, 'srtPath' => $srtPath, 'durationSec' => round($total, 3),
                'clips' => 0, 'costGbp' => 0.0, 'elapsedMs' => $this->elapsed($t0), 'reason' => 'ffmpeg_missing'];
        }

        $n = count($shots);
        $minDur = min(array_column($timeline, 'durationSec'));
        $overlap = $n > 1 ? max(0.1, min(self::MAX_OVERLAP_SEC, $minDur * 0.4)) : 0.0;
        $deadline = microtime(true) + $this->deadlineSeconds;
        $tmp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sce_' . bin2hex(random_bytes(6));
        if (!mkdir($tmp, 0700, true) && !is_dir($tmp)) {
            throw new RuntimeException('Could not allocate a render sandbox.');
        }

        try {
            $clipPaths = [];
            $clipDurations = [];
            foreach ($shots as $i => $shot) {
                // Every clip except the last is padded by the overlap so cuts land on the planned timeline.
                $dur = $timeline[$i]['durationSec'] + ($i < $n - 1 ? $overlap : 0.0);
                $clip = $tmp . DIRECTORY_SEPARATOR . sprintf('clip_%03d.mp4', $i);
                $this->run(self::buildClipArgs($ffmpeg, $shot, $lock->forShot($shot['continuityKey']), $dur, $dims['width'], $dims['height'], $clip), $deadline);
                $clipPaths[] = $clip;
                $clipDurations[] = $dur;
            }

            $videoPath = $dir . DIRECTORY_SEPARATOR . $baseName . '.mp4';
            $this->run(self::buildFinalArgs($ffmpeg, $clipPaths, $clipDurations, $overlap, $total, $videoPath), $deadline);

            return ['status' => 'success', 'videoPath' => $videoPath, 'srtPath' => $srtPath, 'durationSec' => round($total, 3),
                'clips' => $n, 'costGbp' => 0.0, 'elapsedMs' => $this->elapsed($t0)];
        } finally {
            self::removeDir($tmp);
        }
    }

    /**
     * @param array{id: string, durationSec: float, camera: string, refs: array<int, string>, audioIntent: string, continuityKey: string, prompt: string} $shot
     * @param array<string, mixed> $identity
     * @return array<int, string>
     */
    public static function buildClipArgs(string $ffmpeg, array $shot, array $identity, float $durationSec, int $width, int $height, string $outPath): array
    {
        $seed = (int) ($identity['seed'] ?? 1);
        [$c0, $c1] = self::palette($seed);
        $lutKey = (string) ($identity['lutPreset'] ?? 'heritage_gold');
        $lut = SovereignVideoJobHandler::LUT_PRESETS[$lutKey] ?? SovereignVideoJobHandler::LUT_PRESETS['heritage_gold'];
        $frames = max(1, (int) round($durationSec * self::FPS));
        $vignette = max(0.0, min(1.0, (float) ($identity['vignette'] ?? 0.18)));

        $source = sprintf('gradients=s=%dx%d:r=%d:d=%.3f:c0=0x%s:c1=0x%s:seed=%d:speed=0.02', $width, $height, self::FPS, $durationSec, $c0, $c1, $seed);
        $filter = sprintf(
            '%s,%s,vignette=angle=%.3f,format=yuv420p',
            self::cameraFilter($shot['camera'], $frames, $width, $height),
            $lut,
            0.2 + $vignette * 1.2
        );

        return [$ffmpeg, '-y', '-loglevel', 'error', '-f', 'lavfi', '-i', $source, '-vf', $filter,
            '-t', sprintf('%.3f', $durationSec), '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-an', $outPath];
    }

    /**
     * @param array<int, string> $clipPaths
     * @param array<int, float> $clipDurations
     * @return array<int, string>
     */
    public static function buildFinalArgs(string $ffmpeg, array $clipPaths, array $clipDurations, float $overlap, float $totalSec, string $outPath): array
    {
        $args = [$ffmpeg, '-y', '-loglevel', 'error'];
        foreach ($clipPaths as $p) {
            $args[] = '-i';
            $args[] = $p;
        }
        $args[] = '-f';
        $args[] = 'lavfi';
        $args[] = '-i';
        $args[] = 'anullsrc=r=44100:cl=stereo';
        $graph = ShotChainer::buildCrossfadeGraph($clipDurations, 'fade', $overlap > 0.0 ? $overlap : 0.5);

        return array_merge($args, ['-filter_complex', $graph, '-map', '[vout]', '-map', (string) count($clipPaths) . ':a',
            '-t', sprintf('%.3f', $totalSec), '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p',
            '-c:a', 'aac', '-b:a', '96k', '-movflags', '+faststart', $outPath]);
    }

    public static function cameraFilter(string $camera, int $frames, int $width, int $height): string
    {
        $cx = "x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)'";
        $tail = sprintf(':d=1:s=%dx%d:fps=%d', $width, $height, self::FPS);
        $n = max(1, $frames);

        return match ($camera) {
            'zoom_in'   => "zoompan=z='min(1+0.0012*on,1.3)':{$cx}{$tail}",
            'zoom_out'  => "zoompan=z='max(1.3-0.0012*on,1.0)':{$cx}{$tail}",
            'dolly'     => "zoompan=z='min(1+0.0006*on,1.2)':{$cx}{$tail}",
            'pan_left'  => "zoompan=z=1.25:x='(iw-iw/zoom)*(1-on/{$n})':y='ih/2-(ih/zoom/2)'{$tail}",
            'pan_right' => "zoompan=z=1.25:x='(iw-iw/zoom)*(on/{$n})':y='ih/2-(ih/zoom/2)'{$tail}",
            'whip_pan'  => "zoompan=z=1.4:x='(iw-iw/zoom)*min(1,on/({$n}*0.35))':y='ih/2-(ih/zoom/2)'{$tail}",
            default     => "zoompan=z=1:{$cx}{$tail}",
        };
    }

    /**
     * Deterministic two-colour palette (hex, no #) from a seed.
     *
     * @return array{0: string, 1: string}
     */
    public static function palette(int $seed): array
    {
        $hue = $seed % 360;

        return [self::hslToHex($hue, 0.55, 0.18), self::hslToHex(($hue + 40) % 360, 0.60, 0.34)];
    }

    private static function hslToHex(int $h, float $s, float $l): string
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match (intdiv($h, 60) % 6) {
            0 => [$c, $x, 0.0], 1 => [$x, $c, 0.0], 2 => [0.0, $c, $x],
            3 => [0.0, $x, $c], 4 => [$x, 0.0, $c], default => [$c, 0.0, $x],
        };

        return sprintf('%02x%02x%02x', (int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255));
    }

    /**
     * @param array<int, array{id: string, prompt: string}> $shots
     * @param array<int, array{startSec: float, endSec: float}> $timeline
     */
    public static function buildSrt(array $shots, array $timeline): string
    {
        $out = '';
        $idx = 1;
        foreach ($shots as $i => $shot) {
            $text = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', str_replace('-->', '->', $shot['prompt'])));
            if ($text === '') {
                continue;
            }
            $out .= $idx++ . "\n" . self::ts($timeline[$i]['startSec']) . ' --> ' . self::ts($timeline[$i]['endSec']) . "\n" . $text . "\n\n";
        }

        return $out;
    }

    private static function ts(float $sec): string
    {
        $ms = (int) round($sec * 1000);

        return sprintf('%02d:%02d:%02d,%03d', intdiv($ms, 3600000), intdiv($ms % 3600000, 60000), intdiv($ms % 60000, 1000), $ms % 1000);
    }

    /**
     * @param array<int, string> $argv
     */
    private function run(array $argv, float $deadline): void
    {
        $remaining = $deadline - microtime(true);
        if ($remaining <= 0.0) {
            throw new RuntimeException('Render deadline exceeded.');
        }
        $proc = proc_open($argv, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            throw new RuntimeException('Could not start the renderer.');
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stderr = '';
        $exit = -1;
        while (true) {
            $stderr .= (string) stream_get_contents($pipes[2]);
            stream_get_contents($pipes[1]);
            $st = proc_get_status($proc);
            if (!$st['running']) {
                $exit = (int) $st['exitcode'];
                break;
            }
            if (microtime(true) >= $deadline) {
                proc_terminate($proc, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proc);
                throw new RuntimeException('Render deadline exceeded.');
            }
            usleep(20000);
        }
        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        if ($exit !== 0) {
            throw new RuntimeException('Renderer failed: ' . substr(trim($stderr), -300));
        }
    }

    private function elapsed(int $t0): float
    {
        return round((hrtime(true) - $t0) / 1_000_000, 3);
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f !== '.' && $f !== '..') {
                @unlink($dir . DIRECTORY_SEPARATOR . $f);
            }
        }
        @rmdir($dir);
    }
}
