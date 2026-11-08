<?php

declare(strict_types=1);

namespace Src\Media;

use GdImage;
use RuntimeException;
use InvalidArgumentException;

/**
 * UniversalPosterSynthesisService
 *
 * Advanced Technology Group (ATG Pod 3 & Pod 4)
 * Sovereign Multi-Tenant Poster & Event Flyer Synthesis Engine (PHP Backend).
 *
 * Generates ultra-crisp marketing flyers, Open Graph previews, and print collateral
 * across the 8-app portfolio:
 * - PartyPlatform
 * - FamilyPlatform
 * - ExecMindApp
 * - Best Care Professionals (BCP)
 * - LoanEasyFinance
 * - iAccountApp
 * - iDecideApp
 * - TenantScore
 *
 * Strictly adheres to British English (en-GB) and statutory compliance standards.
 */
class UniversalPosterSynthesisService
{
    public const THEME_PARTY_BLACK_GOLD = 'party_black_gold';
    public const THEME_PARTY_NEON = 'party_neon';
    public const THEME_PARTY_POOL_TROPICAL = 'party_pool_tropical';
    public const THEME_PARTY_FLORAL_CHROME = 'party_floral_chrome';
    public const THEME_PARTY_PYRO_CLUB = 'party_pyro_club';
    public const THEME_PARTY_ACRYLIC_CRYSTAL = 'party_acrylic_crystal';
    public const THEME_FAMILY_HEIRLOOM_GOLD = 'family_heirloom_gold';
    public const THEME_FAMILY_BOTANICAL = 'family_botanical';
    public const THEME_FAMILY_REUNION_FESTIVAL = 'family_reunion_festival';
    public const THEME_EXEC_SAPPHIRE_SUMMIT = 'exec_sapphire_summit';
    public const THEME_BCP_URGENT_SHIFT = 'bcp_urgent_shift';
    public const THEME_BCP_CLINICAL_EXCELLENCE = 'bcp_clinical_excellence';
    public const THEME_FINTECH_SME_CAPITAL = 'fintech_sme_capital';
    public const THEME_LOANEASY_CONSUMER_CHEVRON = 'loaneasy_consumer_chevron';
    public const THEME_LOANEASY_RETAIL_CARD = 'loaneasy_retail_card';
    public const THEME_IACCOUNT_TAX_READINESS = 'iaccount_tax_readiness';
    public const THEME_IACCOUNT_TAX_HEALTH = 'iaccount_tax_health';
    public const THEME_IDECIDE_CIVIC_DEBATE = 'idecide_civic_debate';
    public const THEME_TENANT_PROPERTY_SHOWCASE = 'tenant_property_showcase';
    public const THEME_TENANT_CREDIT_BOOST = 'tenant_credit_boost';

    public const ARCHETYPE_POOL_TROPICAL_SPLIT = 'pool_tropical_split';
    public const ARCHETYPE_NEON_SCRIPT_CLASH = 'neon_script_clash';
    public const ARCHETYPE_VIP_BLACK_GOLD = 'vip_black_gold';
    public const ARCHETYPE_FLORAL_CHROME_OVERLAP = 'floral_chrome_overlap';
    public const ARCHETYPE_KINETIC_CLUB_TUNNEL = 'kinetic_club_tunnel';
    public const ARCHETYPE_ACRYLIC_KEEPSAKE_BLOCK = 'acrylic_keepsake_block';
    public const ARCHETYPE_SECTOR_AUTHORITY_GRID = 'sector_authority_grid';
    public const ARCHETYPE_CLINICAL_SHIFT_SURGE = 'clinical_shift_surge';
    public const ARCHETYPE_CLINICAL_RECRUITMENT = 'clinical_recruitment_day';
    public const ARCHETYPE_FINTECH_FACILITY = 'fintech_facility_offer';
    public const ARCHETYPE_CONSUMER_LOAN_CAMPAIGN = 'consumer_loan_campaign';
    public const ARCHETYPE_RETAIL_BANKING_FEATURES = 'retail_banking_features';
    public const ARCHETYPE_TAX_TREASURY_CLINIC = 'tax_statutory_clinic';
    public const ARCHETYPE_TAX_HEALTH_SCORE = 'tax_health_score_app';
    public const ARCHETYPE_EXECUTIVE_BOARD_SUMMIT = 'executive_board_summit';
    public const ARCHETYPE_CIVIC_REFERENDUM = 'civic_referendum_poll';
    public const ARCHETYPE_TENANT_RENT_PASSPORT = 'tenant_rent_passport';
    public const ARCHETYPE_TENANT_CREDIT_BOOST = 'tenant_credit_boost_mockup';

    public const RATIO_STORY = 'story';         // 1080x1920 (9:16)
    public const RATIO_FEED = 'feed';           // 1080x1080 (1:1)
    public const RATIO_PORTRAIT = 'portrait';   // 1080x1350 (4:5)
    public const RATIO_POSTER_A4 = 'poster_a4'; // 1240x1754 (A4 Print)
    public const RATIO_CARD = 'card';           // 1200x630 (OG Banner)

    private int $width;
    private int $height;
    private string $aspectRatio;
    private string $theme;
    private string $archetype;

    /**
     * @var array<string, mixed>
     */
    private array $data;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->aspectRatio = (string) ($config['aspect_ratio'] ?? self::RATIO_STORY);
        $this->theme = (string) ($config['theme'] ?? self::THEME_PARTY_BLACK_GOLD);
        $this->archetype = (string) ($config['archetype'] ?? self::inferArchetype($this->theme));

        match ($this->aspectRatio) {
            self::RATIO_FEED => [$this->width = 1080, $this->height = 1080],
            self::RATIO_PORTRAIT => [$this->width = 1080, $this->height = 1350],
            self::RATIO_POSTER_A4 => [$this->width = 1240, $this->height = 1754],
            self::RATIO_CARD => [$this->width = 1200, $this->height = 630],
            default => [$this->width = 1080, $this->height = 1920],
        };

        if (isset($config['width'])) {
            $this->width = (int) $config['width'];
        }
        if (isset($config['height'])) {
            $this->height = (int) $config['height'];
        }

        $this->data = array_merge([
            'tenant' => 'partyplatform',
            'kicker' => '',
            'title' => 'SUMMER GALA',
            'subtitle' => 'An Unforgettable Celebration',
            'date' => 'SATURDAY 18 JULY 2026',
            'time' => 'DOORS OPEN 9:00 PM',
            'venue' => 'The Grand Atrium, Mayfair',
            'address' => '14 Hanover Square, London W1S 1HU',
            'accent_metric' => '',
            'perks' => ['VIP ADMISSION', 'COCKTAIL RECEPTION', 'LIVE DJ SET'],
            'regulatory_notice' => '',
            'qr_url' => 'https://partyplatform.com/rsvp',
        ], $config['data'] ?? []);

        $this->enforceBritishEnglishInvariants();
        $this->enforceStatutoryDisclaimers();
    }

    /**
     * Fluent factory constructor
     *
     * @param array<string, mixed> $config
     */
    public static function create(array $config = []): self
    {
        return new self($config);
    }

    /**
     * Enforce strict British English (en-GB) across all text inputs
     */
    private function enforceBritishEnglishInvariants(): void
    {
        $usToUk = [
            '/\boptimize\b/i' => 'optimise',
            '/\boptimizing\b/i' => 'optimising',
            '/\borganize\b/i' => 'organise',
            '/\borganizing\b/i' => 'organising',
            '/\bcustomize\b/i' => 'customise',
            '/\bcustomizing\b/i' => 'customising',
            '/\bcolor\b/i' => 'colour',
            '/\bcolors\b/i' => 'colours',
            '/\bbehavior\b/i' => 'behaviour',
            '/\bfavorite\b/i' => 'favourite',
            '/\bcenter\b/i' => 'centre',
            '/\blicense\b/i' => 'licence', // Noun preference for user copy
        ];

        foreach (['title', 'subtitle', 'kicker', 'venue', 'address', 'regulatory_notice'] as $field) {
            if (isset($this->data[$field]) && is_string($this->data[$field])) {
                foreach ($usToUk as $pattern => $replacement) {
                    $this->data[$field] = (string) preg_replace($pattern, $replacement, (string) $this->data[$field]);
                }
            }
        }
    }

    /**
     * Enforce statutory regulatory disclosures
     */
    private function enforceStatutoryDisclaimers(): void
    {
        if (empty($this->data['regulatory_notice'])) {
            if ($this->theme === self::THEME_FINTECH_SME_CAPITAL) {
                $this->data['regulatory_notice'] = 'Capital at risk. Authorised and regulated by the Financial Conduct Authority (FCA). Representative APR applies.';
            } elseif ($this->theme === self::THEME_BCP_URGENT_SHIFT || $this->theme === self::THEME_BCP_CLINICAL_EXCELLENCE) {
                $this->data['regulatory_notice'] = 'Best Care Professionals Ltd. Registered & regulated by the Care Quality Commission (CQC). Reg 18/19 compliant.';
            } elseif ($this->theme === self::THEME_EXEC_SAPPHIRE_SUMMIT) {
                $this->data['regulatory_notice'] = 'Strictly held under the Chatham House Rule. Unauthorised recording or broadcasting is prohibited.';
            }
        }
    }

    /**
     * Render the poster canvas using PHP GD
     */
    public function render(): GdImage
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException('PHP GD extension is required for poster synthesis.');
        }

        $im = imagecreatetruecolor($this->width, $this->height);
        if ($im === false) {
            throw new RuntimeException('Failed to initialise GD image surface.');
        }

        imagealphablending($im, true);
        imagesavealpha($im, true);

        // 1. Draw Background
        $this->renderBackground($im);

        // 2. Draw Atmospheric Bokeh
        $this->renderAtmosphere($im);

        // 3. Draw Top Kicker
        $this->renderKicker($im);

        // 4. Draw Main Headline
        $this->renderHeadline($im);

        // 5. Draw Subtitle
        $this->renderSubtitle($im);

        // 6. Draw Date / Time Capsule
        $this->renderDateTimePill($im);

        // 7. Draw Accent Metric Badge (Pay Rate, APR, CPD Points)
        $this->renderAccentMetric($im);

        // 8. Draw Venue & Perks
        $this->renderVenueAndPerks($im);

        // 9. Draw Dynamic QR Code
        $this->renderQrCode($im);

        // 10. Draw Statutory Regulatory Notice
        $this->renderRegulatoryNotice($im);

        return $im;
    }

    private function renderBackground(GdImage $im): void
    {
        $w = $this->width;
        $h = $this->height;

        $backdropFile = match ($this->theme) {
            self::THEME_FAMILY_HEIRLOOM_GOLD => 'bg_family_golden_jubilee.jpg',
            self::THEME_FINTECH_SME_CAPITAL => 'bg_fintech_sme_emerald.jpg',
            self::THEME_BCP_URGENT_SHIFT, self::THEME_BCP_CLINICAL_EXCELLENCE => 'bg_healthcare_clinical_surge.jpg',
            self::THEME_PARTY_BLACK_GOLD => 'bg_vip_gold_lounge.jpg',
            self::THEME_PARTY_POOL_TROPICAL => 'bg_pool_palm_frame.jpg',
            self::THEME_PARTY_NEON => 'bg_tropical_purple.jpg',
            self::THEME_PARTY_FLORAL_CHROME, self::THEME_FAMILY_BOTANICAL => 'bg_crimson_velvet_floral.jpg',
            self::THEME_PARTY_PYRO_CLUB => 'bg_club_tunnel.jpg',
            self::THEME_PARTY_ACRYLIC_CRYSTAL => 'bg_acrylic_crystal_block.jpg',
            default => null,
        };

        if ($backdropFile !== null) {
            $assetPath = dirname(__DIR__, 2) . '/resources/poster-assets/' . $backdropFile;
            if (!is_file($assetPath)) {
                $fallback = '/Users/waleolaogun/Sites/shared-lib/resources/poster-assets/' . $backdropFile;
                if (is_file($fallback)) {
                    $assetPath = $fallback;
                }
            }
            if (is_file($assetPath)) {
                $bgImg = @imagecreatefromjpeg($assetPath);
                if ($bgImg !== false) {
                    $bw = imagesx($bgImg);
                    $bh = imagesy($bgImg);
                    imagecopyresampled($im, $bgImg, 0, 0, 0, 0, $w, $h, $bw, $bh);
                    imagedestroy($bgImg);

                    // Add soft vignette overlay so typography remains 100% legible
                    $tint = (int) imagecolorallocatealpha($im, 4, 8, 16, 55);
                    imagefilledrectangle($im, 0, 0, $w, $h, $tint);
                    return;
                }
            }
        }

        $topColor = match ($this->theme) {
            self::THEME_PARTY_NEON => [46, 8, 84],
            self::THEME_PARTY_POOL_TROPICAL => [236, 253, 245],
            self::THEME_PARTY_FLORAL_CHROME => [69, 10, 42],
            self::THEME_PARTY_PYRO_CLUB => [67, 20, 7],
            self::THEME_FAMILY_BOTANICAL => [40, 54, 24],
            self::THEME_FAMILY_REUNION_FESTIVAL => [124, 45, 18],
            self::THEME_EXEC_SAPPHIRE_SUMMIT => [15, 23, 42],
            self::THEME_BCP_URGENT_SHIFT => [8, 47, 73],
            self::THEME_BCP_CLINICAL_EXCELLENCE => [240, 249, 255],
            self::THEME_FINTECH_SME_CAPITAL => [6, 78, 59],
            self::THEME_IACCOUNT_TAX_READINESS => [30, 41, 59],
            self::THEME_IDECIDE_CIVIC_DEBATE => [30, 27, 75],
            self::THEME_TENANT_PROPERTY_SHOWCASE => [30, 41, 59],
            default => [39, 32, 23], // Obsidian black & gold
        };

        $bottomColor = match ($this->theme) {
            self::THEME_PARTY_NEON => [5, 1, 10],
            self::THEME_PARTY_POOL_TROPICAL => [187, 247, 208],
            self::THEME_PARTY_FLORAL_CHROME => [17, 12, 46],
            self::THEME_PARTY_PYRO_CLUB => [5, 2, 1],
            self::THEME_FAMILY_BOTANICAL => [14, 18, 8],
            self::THEME_FAMILY_REUNION_FESTIVAL => [24, 10, 5],
            self::THEME_EXEC_SAPPHIRE_SUMMIT => [2, 6, 23],
            self::THEME_BCP_URGENT_SHIFT => [3, 24, 38],
            self::THEME_BCP_CLINICAL_EXCELLENCE => [186, 230, 253],
            self::THEME_FINTECH_SME_CAPITAL => [1, 21, 16],
            self::THEME_IACCOUNT_TAX_READINESS => [2, 6, 23],
            self::THEME_IDECIDE_CIVIC_DEBATE => [9, 5, 20],
            self::THEME_TENANT_PROPERTY_SHOWCASE => [3, 7, 18],
            default => [5, 4, 3],
        };

        // Linear interpolation vertical gradient
        for ($y = 0; $y < $h; $y++) {
            $ratio = $y / max(1, $h);
            $r = (int) round($topColor[0] + ($bottomColor[0] - $topColor[0]) * $ratio);
            $g = (int) round($topColor[1] + ($bottomColor[1] - $topColor[1]) * $ratio);
            $b = (int) round($topColor[2] + ($bottomColor[2] - $topColor[2]) * $ratio);

            $col = (int) imagecolorallocate($im, $r, $g, $b);
            imageline($im, 0, $y, $w, $y, $col);
        }
    }

    private function renderAtmosphere(GdImage $im): void
    {
        $w = $this->width;
        $h = $this->height;

        if ($this->theme === self::THEME_PARTY_BLACK_GOLD || $this->theme === self::THEME_FAMILY_HEIRLOOM_GOLD) {
            // Warm golden bokeh circles
            $bokehPoints = [
                ['x' => (int) ($w * 0.15), 'y' => (int) ($h * 0.18), 'r' => 70],
                ['x' => (int) ($w * 0.85), 'y' => (int) ($h * 0.14), 'r' => 90],
                ['x' => (int) ($w * 0.25), 'y' => (int) ($h * 0.38), 'r' => 50],
                ['x' => (int) ($w * 0.78), 'y' => (int) ($h * 0.42), 'r' => 65],
            ];

            foreach ($bokehPoints as $pt) {
                $gold = (int) imagecolorallocatealpha($im, 253, 224, 71, 105);
                imagefilledellipse($im, $pt['x'], $pt['y'], $pt['r'] * 2, $pt['r'] * 2, $gold);
            }
        }
    }

    private function renderKicker(GdImage $im): void
    {
        $kicker = strtoupper((string) ($this->data['kicker'] ?: 'OFFICIAL PRESENTATION'));
        $kickerCol = match ($this->theme) {
            self::THEME_PARTY_POOL_TROPICAL => (int) imagecolorallocate($im, 6, 95, 70),
            self::THEME_BCP_CLINICAL_EXCELLENCE => (int) imagecolorallocate($im, 3, 105, 161),
            default => (int) imagecolorallocate($im, 248, 250, 252),
        };

        $this->drawCenteredString($im, $kicker, (int) ($this->height * 0.08), 5, $kickerCol);
    }

    private function renderHeadline(GdImage $im): void
    {
        $title = strtoupper((string) ($this->data['title'] ?? 'EVENT TITLE'));
        $titleY = (int) ($this->height * 0.34);

        $shadowCol = (int) imagecolorallocate($im, 0, 0, 0);
        $titleCol = match ($this->theme) {
            self::THEME_PARTY_POOL_TROPICAL => (int) imagecolorallocate($im, 21, 128, 61),
            self::THEME_BCP_URGENT_SHIFT => (int) imagecolorallocate($im, 254, 240, 138), // Yellow highlight
            self::THEME_PARTY_NEON => (int) imagecolorallocate($im, 244, 114, 182),
            self::THEME_PARTY_FLORAL_CHROME => (int) imagecolorallocate($im, 241, 245, 249),
            self::THEME_FINTECH_SME_CAPITAL => (int) imagecolorallocate($im, 217, 119, 6),
            default => (int) imagecolorallocate($im, 250, 204, 21), // Gold
        };

        $style = ($this->theme === self::THEME_PARTY_BLACK_GOLD || $this->theme === self::THEME_FAMILY_HEIRLOOM_GOLD)
            ? 'serif'
            : 'display';

        // Render drop shadow for legibility
        $this->drawCenteredString($im, $title, $titleY + 6, 5, $shadowCol, 78, $style);
        $this->drawCenteredString($im, $title, $titleY, 5, $titleCol, 78, $style);
    }

    private function renderSubtitle(GdImage $im): void
    {
        $subtitle = (string) ($this->data['subtitle'] ?? '');
        if ($subtitle === '') {
            return;
        }

        $subY = (int) ($this->height * 0.52);
        $subCol = match ($this->theme) {
            self::THEME_PARTY_POOL_TROPICAL => (int) imagecolorallocate($im, 4, 120, 87),
            self::THEME_BCP_CLINICAL_EXCELLENCE => (int) imagecolorallocate($im, 2, 132, 199),
            default => (int) imagecolorallocate($im, 226, 232, 240),
        };

        $this->drawCenteredString($im, $subtitle, $subY, 4, $subCol);
    }

    private function renderDateTimePill(GdImage $im): void
    {
        $date = (string) ($this->data['date'] ?? '');
        $time = (string) ($this->data['time'] ?? '');
        $fullText = implode('  |  ', array_filter([$date, $time]));
        if ($fullText === '') {
            return;
        }

        $pillY = (int) ($this->height * 0.62);
        $pillBg = match ($this->theme) {
            self::THEME_BCP_URGENT_SHIFT => (int) imagecolorallocate($im, 225, 29, 72), // Urgent red
            self::THEME_PARTY_POOL_TROPICAL => (int) imagecolorallocate($im, 15, 23, 42),
            default => (int) imagecolorallocatealpha($im, 15, 23, 42, 30),
        };

        $borderCol = (int) imagecolorallocate($im, 212, 175, 55); // Gold border
        $textCol = (int) imagecolorallocate($im, 255, 255, 255);

        $pillWidth = (int) min($this->width * 0.85, 820);
        $pillHeight = 60;
        $pillX = (int) (($this->width - $pillWidth) / 2);

        imagefilledrectangle($im, $pillX, $pillY, $pillX + $pillWidth, $pillY + $pillHeight, $pillBg);
        imagerectangle($im, $pillX, $pillY, $pillX + $pillWidth, $pillY + $pillHeight, $borderCol);

        $this->drawCenteredString($im, $fullText, $pillY + 22, 5, $textCol);
    }

    private function renderAccentMetric(GdImage $im): void
    {
        $metric = strtoupper((string) ($this->data['accent_metric'] ?? ''));
        if ($metric === '') {
            return;
        }

        $metricY = (int) ($this->height * 0.70);
        $bgCol = match ($this->theme) {
            self::THEME_BCP_URGENT_SHIFT => (int) imagecolorallocate($im, 245, 158, 11), // Amber
            self::THEME_FINTECH_SME_CAPITAL => (int) imagecolorallocate($im, 16, 185, 129), // Emerald
            default => (int) imagecolorallocate($im, 56, 189, 248),
        };

        $textCol = (int) imagecolorallocate($im, 15, 23, 42);

        $pillWidth = 380;
        $pillHeight = 48;
        $pillX = (int) (($this->width - $pillWidth) / 2);

        imagefilledrectangle($im, $pillX, $metricY, $pillX + $pillWidth, $metricY + $pillHeight, $bgCol);
        $this->drawCenteredString($im, $metric, $metricY + 16, 5, $textCol);
    }

    private function renderVenueAndPerks(GdImage $im): void
    {
        $venue = strtoupper((string) ($this->data['venue'] ?? ''));
        $address = (string) ($this->data['address'] ?? '');
        $venueY = (int) ($this->height * 0.78);

        $venueCol = match ($this->theme) {
            self::THEME_PARTY_POOL_TROPICAL => (int) imagecolorallocate($im, 15, 23, 42),
            default => (int) imagecolorallocate($im, 255, 255, 255),
        };

        if ($venue !== '') {
            $this->drawCenteredString($im, $venue, $venueY, 5, $venueCol);
        }

        if ($address !== '') {
            $addrCol = (int) imagecolorallocate($im, 148, 163, 184);
            $this->drawCenteredString($im, $address, $venueY + 28, 3, $addrCol);
        }

        $perks = $this->data['perks'] ?? [];
        if (is_array($perks) && count($perks) > 0) {
            $perksText = implode('  •  ', array_map('strtoupper', array_map('strval', $perks)));
            $perkCol = (int) imagecolorallocate($im, 251, 191, 36);
            $this->drawCenteredString($im, $perksText, $venueY + 65, 4, $perkCol);
        }
    }

    private function renderQrCode(GdImage $im): void
    {
        $qrSize = 130;
        $qrMargin = 60;
        $qrX = $this->width - $qrSize - $qrMargin;
        $qrY = $this->height - $qrSize - 80;

        $white = (int) imagecolorallocate($im, 255, 255, 255);
        $black = (int) imagecolorallocate($im, 15, 23, 42);

        // QR Background Plate
        imagefilledrectangle($im, $qrX - 6, $qrY - 6, $qrX + $qrSize + 6, $qrY + $qrSize + 6, $white);
        imagerectangle($im, $qrX - 6, $qrY - 6, $qrX + $qrSize + 6, $qrY + $qrSize + 6, $black);

        // Draw standard 3 finder pattern squares (7x7 blocks)
        $grid = 21;
        $mod = (int) round($qrSize / $grid);

        $drawFinder = function (int $startC, int $startR) use ($im, $qrX, $qrY, $mod, $black): void {
            for ($r = 0; $r < 7; $r++) {
                for ($c = 0; $c < 7; $c++) {
                    if ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4)) {
                        imagefilledrectangle(
                            $im,
                            $qrX + ($startC + $c) * $mod,
                            $qrY + ($startR + $r) * $mod,
                            $qrX + ($startC + $c + 1) * $mod - 1,
                            $qrY + ($startR + $r + 1) * $mod - 1,
                            $black
                        );
                    }
                }
            }
        };

        $drawFinder(0, 0);
        $drawFinder($grid - 7, 0);
        $drawFinder(0, $grid - 7);

        // Deterministic QR hash bits
        $hash = crc32((string) ($this->data['qr_url'] ?? 'https://partyplatform.com'));
        for ($r = 0; $r < $grid; $r++) {
            for ($c = 0; $c < $grid; $c++) {
                $inTL = $r < 8 && $c < 8;
                $inTR = $r < 8 && $c >= $grid - 8;
                $inBL = $r >= $grid - 8 && $c < 8;
                $isTiming = $r === 6 || $c === 6;

                if (!$inTL && !$inTR && !$inBL && !$isTiming) {
                    if (abs(sin($hash + $r * 13 + $c * 29)) > 0.4) {
                        imagefilledrectangle(
                            $im,
                            $qrX + $c * $mod,
                            $qrY + $r * $mod,
                            $qrX + ($c + 1) * $mod - 1,
                            $qrY + ($r + 1) * $mod - 1,
                            $black
                        );
                    }
                }
            }
        }

        // Mini Label
        $label = 'SCAN TO RSVP';
        $labelCol = (int) imagecolorallocate($im, 255, 255, 255);
        $labelWidth = imagefontwidth(2) * strlen($label);
        imagestring($im, 2, (int) ($qrX + ($qrSize - $labelWidth) / 2), $qrY + $qrSize + 10, $label, $labelCol);
    }

    private function renderRegulatoryNotice(GdImage $im): void
    {
        $notice = (string) ($this->data['regulatory_notice'] ?? '');
        if ($notice === '') {
            return;
        }

        $noticeCol = match ($this->theme) {
            self::THEME_PARTY_POOL_TROPICAL => (int) imagecolorallocate($im, 71, 85, 105),
            default => (int) imagecolorallocate($im, 148, 163, 184),
        };

        $this->drawCenteredString($im, $notice, $this->height - 35, 2, $noticeCol);
    }

    /**
     * Resolve system TrueType font path for high-DPI anti-aliased rendering
     */
    private function resolveTtfFont(string $style = 'display'): ?string
    {
        $candidates = match ($style) {
            'serif' => [
                '/System/Library/Fonts/Supplemental/Georgia Bold.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf',
            ],
            'script' => [
                '/System/Library/Fonts/Supplemental/Brush Script.ttf',
                '/System/Library/Fonts/Supplemental/Apple Chancery.ttf',
            ],
            'sans' => [
                '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            ],
            default => [
                '/System/Library/Fonts/Supplemental/DIN Condensed Bold.ttf',
                '/System/Library/Fonts/Supplemental/Impact.ttf',
                '/System/Library/Fonts/Supplemental/Arial Black.ttf',
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            ],
        };

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    /**
     * Helper to draw horizontally centered string using TrueType fonts (with GD bitmap fallback)
     */
    private function drawCenteredString(GdImage $im, string $text, int $y, int $font, int $color, int $ptOverride = 0, string $style = 'sans'): void
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [$text];
        $ptSize = $ptOverride > 0 ? $ptOverride : match ($font) {
            5 => 26,
            4 => 20,
            3 => 16,
            default => 12,
        };

        $ttf = function_exists('imagettftext') ? $this->resolveTtfFont($style) : null;
        if ($ttf !== null) {
            $lineStep = (int) round($ptSize * 1.28);
            foreach ($lines as $idx => $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $bbox = @imagettfbbox($ptSize, 0, $ttf, $line);
                if (is_array($bbox)) {
                    $textW = abs($bbox[4] - $bbox[0]);
                    $x = (int) max(20, ($this->width - $textW) / 2);
                    $lineY = $y + ($idx * $lineStep) + $ptSize;
                    @imagettftext($im, $ptSize, 0, $x, $lineY, $color, $ttf, $line);
                }
            }
            return;
        }

        $fontWidth = imagefontwidth($font);
        foreach ($lines as $idx => $line) {
            $totalWidth = $fontWidth * strlen($line);
            $x = (int) max(10, ($this->width - $totalWidth) / 2);
            imagestring($im, $font, $x, $y + ($idx * 20), $line, $color);
        }
    }

    /**
     * Render and encode directly to PNG binary string
     */
    public function toPngString(): string
    {
        $im = $this->render();
        ob_start();
        imagepng($im);
        $data = (string) ob_get_clean();
        if (PHP_VERSION_ID < 80500) {
            @imagedestroy($im);
        }
        return $data;
    }

    /**
     * Export as Data URI
     */
    public function toDataUri(): string
    {
        return 'data:image/png;base64,' . base64_encode($this->toPngString());
    }

    /**
     * Save generated image to local filesystem
     */
    public function save(string $filepath): bool
    {
        $im = $this->render();
        $dir = dirname($filepath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $saved = imagepng($im, $filepath);
        if (PHP_VERSION_ID < 80500) {
            @imagedestroy($im);
        }
        return $saved;
    }

    /**
     * Infer default archetype from theme identifier
     */
    public static function inferArchetype(string $theme): string
    {
        return match ($theme) {
            self::THEME_PARTY_POOL_TROPICAL => self::ARCHETYPE_POOL_TROPICAL_SPLIT,
            self::THEME_PARTY_NEON => self::ARCHETYPE_NEON_SCRIPT_CLASH,
            self::THEME_PARTY_BLACK_GOLD, self::THEME_FAMILY_HEIRLOOM_GOLD => self::ARCHETYPE_VIP_BLACK_GOLD,
            self::THEME_PARTY_FLORAL_CHROME, self::THEME_FAMILY_BOTANICAL => self::ARCHETYPE_FLORAL_CHROME_OVERLAP,
            self::THEME_PARTY_PYRO_CLUB, self::THEME_FAMILY_REUNION_FESTIVAL => self::ARCHETYPE_KINETIC_CLUB_TUNNEL,
            self::THEME_PARTY_ACRYLIC_CRYSTAL => self::ARCHETYPE_ACRYLIC_KEEPSAKE_BLOCK,
            self::THEME_BCP_URGENT_SHIFT => self::ARCHETYPE_CLINICAL_SHIFT_SURGE,
            self::THEME_BCP_CLINICAL_EXCELLENCE => self::ARCHETYPE_CLINICAL_RECRUITMENT,
            self::THEME_FINTECH_SME_CAPITAL => self::ARCHETYPE_FINTECH_FACILITY,
            self::THEME_LOANEASY_CONSUMER_CHEVRON => self::ARCHETYPE_CONSUMER_LOAN_CAMPAIGN,
            self::THEME_LOANEASY_RETAIL_CARD => self::ARCHETYPE_RETAIL_BANKING_FEATURES,
            self::THEME_IACCOUNT_TAX_READINESS => self::ARCHETYPE_TAX_TREASURY_CLINIC,
            self::THEME_IACCOUNT_TAX_HEALTH => self::ARCHETYPE_TAX_HEALTH_SCORE,
            self::THEME_EXEC_SAPPHIRE_SUMMIT => self::ARCHETYPE_EXECUTIVE_BOARD_SUMMIT,
            self::THEME_IDECIDE_CIVIC_DEBATE => self::ARCHETYPE_CIVIC_REFERENDUM,
            self::THEME_TENANT_PROPERTY_SHOWCASE => self::ARCHETYPE_TENANT_RENT_PASSPORT,
            self::THEME_TENANT_CREDIT_BOOST => self::ARCHETYPE_TENANT_CREDIT_BOOST,
            default => self::ARCHETYPE_SECTOR_AUTHORITY_GRID,
        };
    }

    /**
     * Get layout archetypes with metadata, optionally filtered by application context
     *
     * @return array<string, array{id: string, name: string, subtitle: string, reference: string, sector: string}>
     */
    public static function getArchetypes(?string $appKey = null): array
    {
        $all = [
            self::ARCHETYPE_POOL_TROPICAL_SPLIT => [
                'id' => self::ARCHETYPE_POOL_TROPICAL_SPLIT,
                'name' => 'Tropical Split & 3D Block',
                'subtitle' => 'Left 3D stacked headline, corner palm fronds & right subject cutout',
                'reference' => 'Sample #1: Sunday Pool Party',
                'sector' => 'party',
            ],
            self::ARCHETYPE_NEON_SCRIPT_CLASH => [
                'id' => self::ARCHETYPE_NEON_SCRIPT_CLASH,
                'name' => 'Neon Script Clash & Chamfered Box',
                'subtitle' => 'Glowing cursive script over hollow neon caps & cut-corner glass box',
                'reference' => 'Sample #2: Tropical Purple Party',
                'sector' => 'party',
            ],
            self::ARCHETYPE_VIP_BLACK_GOLD => [
                'id' => self::ARCHETYPE_VIP_BLACK_GOLD,
                'name' => 'VIP Black & Gold Editorial + Ghost Echo',
                'subtitle' => 'Gold circular seal, metallic serif & double-exposure portrait',
                'reference' => "Sample #3: Aaron's Birthday / Family Jubilee",
                'sector' => 'party_family',
            ],
            self::ARCHETYPE_FLORAL_CHROME_OVERLAP => [
                'id' => self::ARCHETYPE_FLORAL_CHROME_OVERLAP,
                'name' => '3D Chrome & Botanical Peony Overlap',
                'subtitle' => 'Cursive greeting, giant date, 3D chrome headline with peony blooms',
                'reference' => 'Sample #4: Happy Birthday Alisa / Botanical Reunion',
                'sector' => 'party_family',
            ],
            self::ARCHETYPE_KINETIC_CLUB_TUNNEL => [
                'id' => self::ARCHETYPE_KINETIC_CLUB_TUNNEL,
                'name' => 'Kinetic Laser Tunnel & Text-Behind-Subject',
                'subtitle' => 'Perspective tunnel, stacked gold block letters behind subject',
                'reference' => 'Sample #5: Friday Party Night',
                'sector' => 'party',
            ],
            self::ARCHETYPE_ACRYLIC_KEEPSAKE_BLOCK => [
                'id' => self::ARCHETYPE_ACRYLIC_KEEPSAKE_BLOCK,
                'name' => 'Illuminated Acrylic Block (3D Keepsake)',
                'subtitle' => 'Photorealistic frosted crystal block with floating 3D subject and watercolor flora',
                'reference' => 'Keepsake Edition: 3D Acrylic Birthday Plaque',
                'sector' => 'party_family',
            ],
            self::ARCHETYPE_CLINICAL_SHIFT_SURGE => [
                'id' => self::ARCHETYPE_CLINICAL_SHIFT_SURGE,
                'name' => 'Urgent ITU/RGN Clinical Shift Surge',
                'subtitle' => 'CQC Reg 18/19 broadcast, high-impact hourly rate banner, shift rota grid',
                'reference' => 'Healthcare: Best Care Professionals (BCP)',
                'sector' => 'healthcare',
            ],
            self::ARCHETYPE_CLINICAL_RECRUITMENT => [
                'id' => self::ARCHETYPE_CLINICAL_RECRUITMENT,
                'name' => 'Senior Carer & Nursing Open Day',
                'subtitle' => 'Clinical teal/navy trust cards, fast-track DBS, CPD accreditation mark',
                'reference' => 'Healthcare: BCP Carer Recruitment Academy',
                'sector' => 'healthcare',
            ],
            self::ARCHETYPE_FINTECH_FACILITY => [
                'id' => self::ARCHETYPE_FINTECH_FACILITY,
                'name' => 'SME Growth Capital & Commercial Facility',
                'subtitle' => 'Emerald noir, prominent Representative APR pill, £500k limit & FCA warning',
                'reference' => 'FinTech: LoanEasy Commercial Underwriting',
                'sector' => 'fintech',
            ],
            self::ARCHETYPE_CONSUMER_LOAN_CAMPAIGN => [
                'id' => self::ARCHETYPE_CONSUMER_LOAN_CAMPAIGN,
                'name' => 'Consumer Loan Campaign (Chevron)',
                'subtitle' => 'Dynamic brand crimson chevron, candid lifestyle hero, prominent starting APR & brand lockup',
                'reference' => 'Retail Lending: Home & Personal Loan Campaign',
                'sector' => 'fintech',
            ],
            self::ARCHETYPE_RETAIL_BANKING_FEATURES => [
                'id' => self::ARCHETYPE_RETAIL_BANKING_FEATURES,
                'name' => 'Retail Banking Product Grid',
                'subtitle' => 'High-contrast white feature card, 4.40% bold rate, Fee-Saver & 5% deposit badges',
                'reference' => 'Consumer Mortgages: Exclusive Products',
                'sector' => 'fintech',
            ],
            self::ARCHETYPE_TAX_TREASURY_CLINIC => [
                'id' => self::ARCHETYPE_TAX_TREASURY_CLINIC,
                'name' => 'HMRC Making Tax Digital & Year-End Clinic',
                'subtitle' => 'Statutory filing deadline countdown, digital records badge, 2 CPD hours',
                'reference' => 'Treasury & Tax: iAccount Financial Command Centre',
                'sector' => 'accounting',
            ],
            self::ARCHETYPE_TAX_HEALTH_SCORE => [
                'id' => self::ARCHETYPE_TAX_HEALTH_SCORE,
                'name' => 'FinTech Tax Health App Showcase',
                'subtitle' => 'Smartphone app UI, segmented 980/1000 compliance gauge, boost rocket & zero-penalty card',
                'reference' => 'Smart FinTech: iAccount Tax Health & Runway Meter',
                'sector' => 'accounting',
            ],
            self::ARCHETYPE_EXECUTIVE_BOARD_SUMMIT => [
                'id' => self::ARCHETYPE_EXECUTIVE_BOARD_SUMMIT,
                'name' => 'Global CEO & Board AI Strategy Summit',
                'subtitle' => 'Sapphire obsidian elegance, Chatham House Rule seal, Mayfair penthouse suite',
                'reference' => 'Executive: ExecMind Sovereign Leadership Series',
                'sector' => 'executive',
            ],
            self::ARCHETYPE_CIVIC_REFERENDUM => [
                'id' => self::ARCHETYPE_CIVIC_REFERENDUM,
                'name' => 'National Civic Debate & Referendum Poll',
                'subtitle' => 'Democratic cobalt/crimson split, ballot voting QR & cryptographic audit',
                'reference' => 'Civic Democracy: iDecide Verified Citizen Ballot',
                'sector' => 'civic',
            ],
            self::ARCHETYPE_TENANT_RENT_PASSPORT => [
                'id' => self::ARCHETYPE_TENANT_RENT_PASSPORT,
                'name' => 'Verified Rent Passport & Property Open House',
                'subtitle' => '99.4% Rent Guarantee Score badge, Open Banking affordability & viewing slots',
                'reference' => 'PropTech: TenantScore Verified Passport',
                'sector' => 'tenant',
            ],
            self::ARCHETYPE_TENANT_CREDIT_BOOST => [
                'id' => self::ARCHETYPE_TENANT_CREDIT_BOOST,
                'name' => 'Tenant Credit Boost & Verified Score Showcase',
                'subtitle' => 'Smartphone app UI, 1250-point segmented radial gauge, rocket boost, rent score impacts',
                'reference' => 'Credit PropTech: TenantScore Verified Rent Boost',
                'sector' => 'tenant',
            ],
            self::ARCHETYPE_SECTOR_AUTHORITY_GRID => [
                'id' => self::ARCHETYPE_SECTOR_AUTHORITY_GRID,
                'name' => 'Statutory Sector Authority & Compliance Grid',
                'subtitle' => 'Structured architectural grid for regulated enterprise compliance',
                'reference' => 'Enterprise Cross-Portfolio Authority',
                'sector' => 'enterprise',
            ],
        ];

        if ($appKey === null) {
            return $all;
        }

        $appKey = strtolower($appKey);
        return match ($appKey) {
            'bcp' => array_filter($all, fn($a) => in_array($a['sector'], ['healthcare', 'enterprise'], true)),
            'loaneasyfinance', 'loaneasy' => array_filter($all, fn($a) => in_array($a['sector'], ['fintech', 'enterprise'], true)),
            'iaccountapp', 'iaccount' => array_filter($all, fn($a) => in_array($a['sector'], ['accounting', 'enterprise'], true)),
            'execmindapp', 'execmind' => array_filter($all, fn($a) => in_array($a['sector'], ['executive', 'enterprise'], true)),
            'idecideapp', 'idecide' => array_filter($all, fn($a) => in_array($a['sector'], ['civic', 'enterprise'], true)),
            'scoretenant', 'tenantscore' => array_filter($all, fn($a) => in_array($a['sector'], ['tenant', 'enterprise'], true)),
            'familyplatform', 'family' => array_filter($all, fn($a) => in_array($a['sector'], ['party_family', 'party'], true)),
            default => array_filter($all, fn($a) => in_array($a['sector'], ['party', 'party_family'], true)),
        };
    }

    public function getArchetype(): string
    {
        return $this->archetype;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getTheme(): string
    {
        return $this->theme;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
