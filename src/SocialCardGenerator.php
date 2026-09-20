<?php

declare(strict_types=1);

namespace Src;

use RuntimeException;

/**
 * SocialCardGenerator
 * Generates 1200x630 high-resolution Open Graph branded social cards using PHP GD.
 *
 * Part of modernman00/shared-lib
 */
class SocialCardGenerator
{
    private int $width;
    private int $height;
    private string $brandName = 'Platform';
    private ?string $brandBadge = 'INTELLIGENCE';
    private string $title = 'Analysis Overview';
    private ?string $subtitle = null;
    private int $score = 85;
    private int $maxScore = 100;
    private string $verdict = 'STRONG GO';
    private string $palette = 'emerald';
    private string $watermark = 'Generated via Portfolio Platform';

    /**
     * @var array<int, array{label: string, score: int, maxScore: int}>
     */
    private array $metrics = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->width = (int) ($config['width'] ?? 1200);
        $this->height = (int) ($config['height'] ?? 630);
        $this->brandName = (string) ($config['brand_name'] ?? 'Platform');
        $this->brandBadge = isset($config['brand_badge']) ? (string) $config['brand_badge'] : 'INTELLIGENCE';
        $this->title = (string) ($config['title'] ?? 'Analysis Overview');
        $this->subtitle = isset($config['subtitle']) ? (string) $config['subtitle'] : null;
        $this->score = (int) ($config['score'] ?? 85);
        $this->verdict = (string) ($config['verdict'] ?? 'STRONG GO');
        $this->palette = (string) ($config['palette'] ?? 'emerald');
        $this->watermark = (string) ($config['watermark'] ?? 'Generated via Portfolio Platform');
    }

    /**
     * Factory constructor
     *
     * @param array<string, mixed> $config
     */
    public static function create(array $config = []): self
    {
        return new self($config);
    }

    public function setBrand(string $brandName, ?string $badge = null): self
    {
        $this->brandName = $brandName;
        $this->brandBadge = $badge;
        return $this;
    }

    public function setTitle(string $title, ?string $subtitle = null): self
    {
        $this->title = $title;
        $this->subtitle = $subtitle;
        return $this;
    }

    public function setScore(int $score, string $verdict = 'STRONG GO', string $palette = 'emerald'): self
    {
        $this->score = max(0, min(100, $score));
        $this->verdict = $verdict;
        $this->palette = $palette;
        return $this;
    }

    public function addMetric(string $label, int $score, int $maxScore = 10): self
    {
        $this->metrics[] = [
            'label' => $label,
            'score' => $score,
            'maxScore' => max(1, $maxScore),
        ];
        return $this;
    }

    /**
     * Generates and saves the PNG card to the specified file path.
     */
    public function save(string $outputPath): bool
    {
        $img = $this->renderGdImage();
        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $result = imagepng($img, $outputPath, 8);
        return $result;
    }

    /**
     * Returns raw PNG binary string.
     */
    public function toPngString(): string
    {
        $img = $this->renderGdImage();
        ob_start();
        imagepng($img, null, 8);
        $data = ob_get_clean();

        if ($data === false) {
            throw new RuntimeException('Failed to capture GD PNG output buffer.');
        }

        return $data;
    }

    /**
     * Builds the GD Image resource
     *
     * @return \GdImage
     */
    private function renderGdImage(): \GdImage
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException('GD extension is required to generate social cards.');
        }

        $img = imagecreatetruecolor($this->width, $this->height);
        if ($img === false) {
            throw new RuntimeException('Unable to create GD image resource.');
        }

        imageantialias($img, true);

        // Color palette setup
        $cBgTop = (int) imagecolorallocate($img, 9, 13, 22);
        $cBgBottom = (int) imagecolorallocate($img, 5, 8, 17);
        $cCardBg = (int) imagecolorallocate($img, 15, 23, 42);
        $cBorder = (int) imagecolorallocate($img, 30, 41, 59);
        $cTextWhite = (int) imagecolorallocate($img, 255, 255, 255);
        $cTextMuted = (int) imagecolorallocate($img, 148, 163, 184);

        // Accent palette
        [$r, $g, $b] = match ($this->palette) {
            'blue' => [59, 130, 246],
            'purple' => [139, 92, 246],
            'amber' => [245, 158, 11],
            'rose' => [244, 63, 94],
            default => [16, 185, 129], // emerald
        };
        $cAccent = (int) imagecolorallocate($img, $r, $g, $b);
        $cAccentMuted = (int) imagecolorallocate($img, (int) ($r * 0.2), (int) ($g * 0.2), (int) ($b * 0.2));

        // 1. Fill Background
        imagefilledrectangle($img, 0, 0, $this->width, $this->height, $cBgTop);

        // 2. Ambient glow orbs
        for ($i = 0; $i < 40; $i++) {
            $glowAlpha = (int) (127 - ($i * 2));
            $glowCol = imagecolorallocatealpha($img, $r, $g, $b, max(0, min(127, $glowAlpha)));
            if ($glowCol !== false) {
                imagefilledellipse($img, 180, 150, 260 + ($i * 4), 260 + ($i * 4), $glowCol);
            }
        }

        // 3. Inner Card
        $margin = 40;
        $cardW = $this->width - ($margin * 2);
        $cardH = $this->height - ($margin * 2);
        imagefilledrectangle($img, $margin, $margin, $margin + $cardW, $margin + $cardH, $cCardBg);
        imagerectangle($img, $margin, $margin, $margin + $cardW, $margin + $cardH, $cBorder);

        // 4. Header (Brand & Badge)
        $brandStr = strtoupper($this->brandName);
        imagestring($img, 5, $margin + 40, $margin + 35, $brandStr, $cTextWhite);

        if ($this->brandBadge !== null && $this->brandBadge !== '') {
            $badgeX = $margin + 40 + (strlen($brandStr) * 10) + 16;
            $badgeText = strtoupper($this->brandBadge);
            $badgeW = (strlen($badgeText) * 8) + 16;
            imagefilledrectangle($img, $badgeX, $margin + 30, $badgeX + $badgeW, $margin + 52, $cAccentMuted);
            imagerectangle($img, $badgeX, $margin + 30, $badgeX + $badgeW, $margin + 52, $cAccent);
            imagestring($img, 3, $badgeX + 8, $margin + 34, $badgeText, $cAccent);
        }

        // Header Divider
        imageline($img, $margin + 40, $margin + 68, $margin + $cardW - 40, $margin + 68, $cBorder);

        // 5. Left Score Gauge
        $gaugeX = $margin + 170;
        $gaugeY = $margin + 260;
        $gaugeRadius = 180;

        // Background track
        imagearc($img, $gaugeX, $gaugeY, $gaugeRadius, $gaugeRadius, 0, 360, $cBorder);
        imagearc($img, $gaugeX, $gaugeY, $gaugeRadius - 2, $gaugeRadius - 2, 0, 360, $cBorder);

        // Active Arc
        $scoreDegrees = (int) round(($this->score / $this->maxScore) * 360);
        if ($scoreDegrees > 0) {
            for ($thick = 0; $thick < 12; $thick++) {
                imagearc($img, $gaugeX, $gaugeY, $gaugeRadius - $thick, $gaugeRadius - $thick, 270, (270 + $scoreDegrees) % 360, $cAccent);
            }
        }

        // Center Score Text
        $scoreStr = "{$this->score}%";
        imagestring($img, 5, $gaugeX - (int) (strlen($scoreStr) * 4.5), $gaugeY - 12, $scoreStr, $cTextWhite);
        $scoreLbl = "SCORE";
        imagestring($img, 3, $gaugeX - (int) (strlen($scoreLbl) * 4), $gaugeY + 14, $scoreLbl, $cTextMuted);

        // Verdict Pill below gauge
        if ($this->verdict !== '') {
            $verdictStr = strtoupper($this->verdict);
            $pillW = (strlen($verdictStr) * 8) + 24;
            $pillX = $gaugeX - (int) ($pillW / 2);
            $pillY = $gaugeY + 115;
            imagefilledrectangle($img, $pillX, $pillY, $pillX + $pillW, $pillY + 28, $cAccentMuted);
            imagerectangle($img, $pillX, $pillY, $pillX + $pillW, $pillY + 28, $cAccent);
            imagestring($img, 3, $pillX + 12, $pillY + 7, $verdictStr, $cAccent);
        }

        // 6. Right Content (Title, Subtitle & Metrics)
        $rightX = $margin + 340;
        $rightY = $margin + 110;

        // Title
        imagestring($img, 5, $rightX, $rightY, $this->title, $cTextWhite);

        if ($this->subtitle !== null && $this->subtitle !== '') {
            imagestring($img, 4, $rightX, $rightY + 28, $this->subtitle, $cTextMuted);
        }

        // Metrics breakdown
        $mY = $rightY + 70;
        $barMaxW = 400;
        $metricsToShow = array_slice($this->metrics, 0, 4);

        foreach ($metricsToShow as $m) {
            $mLabel = $m['label'];
            $mScore = $m['score'];
            $mMax = $m['maxScore'];
            $mRatio = max(0.0, min(1.0, $mScore / $mMax));

            imagestring($img, 4, $rightX, $mY, $mLabel, $cTextWhite);
            $scoreText = "{$mScore}/{$mMax}";
            imagestring($img, 4, $rightX + $barMaxW - (strlen($scoreText) * 8), $mY, $scoreText, $cAccent);

            // Bar track
            $barTop = $mY + 22;
            imagefilledrectangle($img, $rightX, $barTop, $rightX + $barMaxW, $barTop + 8, $cBorder);

            // Bar fill
            if ($mRatio > 0) {
                $fillW = (int) round($barMaxW * $mRatio);
                imagefilledrectangle($img, $rightX, $barTop, $rightX + $fillW, $barTop + 8, $cAccent);
            }

            $mY += 46;
        }

        // 7. Footer Watermark
        imagestring($img, 3, $margin + 40, $margin + $cardH - 35, $this->watermark, $cTextMuted);

        return $img;
    }
}
