<?php

declare(strict_types=1);

namespace Src;

/**
 * Class EcosystemAdManager
 *
 * Centralized Omnipresent Dynamic Advert & Campaign Management Engine.
 * Shared across all 8 portfolio applications via modernman00/shared-lib.
 *
 * Capabilities:
 * 1. Single-source storage: Reads and writes to a central atomic JSON store.
 * 2. Instant cross-app synchronization: Updates in one app are live everywhere immediately.
 * 3. Fallback resilience: If dynamic storage is empty/offline, sister-apps rotation runs unaffected.
 * 4. Self-contained Admin UI: Embeddable in any portfolio application's admin portal.
 * 5. Security armor: Strict URL validation, XSS sanitization, and CSRF protection.
 */
class EcosystemAdManager
{
    /**
     * Default storage path for shared ecosystem adverts.
     */
    private const DEFAULT_PRODUCTION_PATH = '/home/bestiias/shared_storage/ecosystem_adverts.json';

    /**
     * In-memory static cache for the duration of the request.
     *
     * @var array<string, array<string, mixed>>|null
     */
    private static ?array $memoryCache = null;

    /**
     * Clears in-memory static cache.
     */
    public static function clearMemoryCache(): void
    {
        self::$memoryCache = null;
    }

    /**
     * Resolves the active storage file path across environments.
     */
    public static function getStoragePath(): string
    {
        if (!empty($_ENV['ECOSYSTEM_ADVERTS_PATH'])) {
            return (string) $_ENV['ECOSYSTEM_ADVERTS_PATH'];
        }

        // Production environment on cPanel host
        if (is_dir('/home/bestiias')) {
            $sharedDir = '/home/bestiias/shared_storage';
            if (!is_dir($sharedDir)) {
                @mkdir($sharedDir, 0755, true);
            }
            return self::DEFAULT_PRODUCTION_PATH;
        }

        // Local development environment
        $localDir = dirname(__DIR__) . '/storage';
        if (!is_dir($localDir)) {
            @mkdir($localDir, 0755, true);
        }
        return $localDir . '/ecosystem_adverts.json';
    }

    /**
     * Retrieves all configured adverts from central storage.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getAllAdverts(): array
    {
        if (self::$memoryCache !== null) {
            return self::$memoryCache;
        }

        $path = self::getStoragePath();
        if (!file_exists($path)) {
            self::$memoryCache = [];
            return [];
        }

        $content = @file_get_contents($path);
        if ($content === false || trim($content) === '') {
            self::$memoryCache = [];
            return [];
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                self::$memoryCache = [];
                return [];
            }
            /** @var array<string, array<string, mixed>> $data */
            self::$memoryCache = $data;
            return self::$memoryCache;
        } catch (\Throwable) {
            self::$memoryCache = [];
            return [];
        }
    }

    /**
     * Retrieves the best-matching active custom advert for a specific application context.
     *
     * @param string $appKey Current application key (e.g. 'partyplatform')
     * @param string $placement Location identifier (e.g. 'dashboard')
     * @param array<string, mixed> $context Context attributes
     * @return array<string, mixed>|null Formatted advert banner array or null
     */
    public static function getAdvertForApp(string $appKey, string $placement = '', array $context = []): ?array
    {
        $all = self::getAllAdverts();
        if (empty($all)) {
            return null;
        }

        $appKey = strtolower(trim($appKey));
        $now = time();
        $candidates = [];

        foreach ($all as $ad) {
            // 1. Must be active
            if (empty($ad['active'])) {
                continue;
            }

            // 2. Schedule window check
            if (!empty($ad['start_at']) && strtotime((string) $ad['start_at']) > $now) {
                continue;
            }
            if (!empty($ad['end_at']) && strtotime((string) $ad['end_at']) < $now) {
                continue;
            }

            // 3. Target application match (either 'all' or explicit app key)
            $targetApps = (array) ($ad['target_apps'] ?? ['all']);
            $isTargeted = in_array('all', $targetApps, true) || in_array($appKey, $targetApps, true);
            if (!$isTargeted) {
                continue;
            }

            // 4. Do not advertise an app to itself if target_app matches current app
            if (!empty($ad['target_app_key']) && strtolower((string) $ad['target_app_key']) === $appKey) {
                continue;
            }

            $candidates[] = $ad;
        }

        if (empty($candidates)) {
            return null;
        }

        // Select candidate deterministically or by highest weight
        // If multiple candidates exist, rotate using placement and day-of-year
        $count = count($candidates);
        $dayOfYear = (int) date('z');
        $placementSalt = abs(crc32($appKey . ':' . $placement)) % $count;
        $idx = ($dayOfYear + $placementSalt) % $count;

        $chosen = $candidates[$idx] ?? $candidates[0];

        // If candidate mode is 'blended', roll a 50/50 dice with session/day to share impressions
        $mode = (string) ($chosen['mode'] ?? 'exclusive');
        if ($mode === 'blended') {
            // In blended mode, display dynamic ad 50% of the time, and sister apps the other 50%
            $step = 0;
            if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION)) {
                $sessionKey = '_eco_custom_blend_' . $appKey;
                $step = (int) ($_SESSION[$sessionKey] ?? 0);
                $_SESSION[$sessionKey] = ($step + 1) % 2;
            } else {
                $step = (int) date('s') % 2;
            }

            if ($step === 1) {
                // Yield this impression to sister apps catalog
                return null;
            }
        }

        return self::formatCustomBanner($chosen, $appKey, $placement);
    }

    /**
     * Formats raw custom advert record into the standard Ecosystem Promo Banner structure.
     *
     * @param array<string, mixed> $ad
     * @param string $sourceApp
     * @param string $placement
     * @return array<string, mixed>
     */
    public static function formatCustomBanner(array $ad, string $sourceApp, string $placement): array
    {
        $id = (string) ($ad['id'] ?? 'custom_' . substr(md5((string) ($ad['title'] ?? '')), 0, 8));
        $targetUrl = (string) ($ad['target_url'] ?? '#');

        // Append tracking parameters if valid URL
        if (filter_var($targetUrl, FILTER_VALIDATE_URL) && !str_contains($targetUrl, 'utm_source=')) {
            $separator = str_contains($targetUrl, '?') ? '&' : '?';
            $params = [
                'utm_source' => $sourceApp,
                'utm_medium' => 'ecosystem_custom_ad',
                'utm_campaign' => $ad['campaign_slug'] ?? $placement,
            ];
            $targetUrl .= $separator . http_build_query($params);
        }

        $accentColor = (string) ($ad['accent_color'] ?? '#6366f1');
        $gradient = (string) ($ad['gradient'] ?? "linear-gradient(135deg, {$accentColor} 0%, #a855f7 100%)");
        $badge = (string) ($ad['badge'] ?? 'FEATURED PROMO');
        $badge = strtoupper(trim($badge));

        $svgIcon = !empty($ad['svg_icon'])
            ? (string) $ad['svg_icon']
            : '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pp-brand-logo-svg"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';

        return [
            'id' => 'custom_' . $id,
            'type' => 'custom_campaign',
            'target_app' => (string) ($ad['target_app_key'] ?? 'external'),
            'brand_name' => (string) ($ad['brand_name'] ?? $ad['title'] ?? 'Special Announcement'),
            'badge' => $badge,
            'title' => (string) ($ad['headline'] ?? $ad['title'] ?? ''),
            'tagline' => (string) ($ad['tagline'] ?? 'Curated Spotlight'),
            'description' => (string) ($ad['description'] ?? ''),
            'cta_text' => (string) ($ad['cta_text'] ?? 'Learn More'),
            'target_url' => $targetUrl,
            'accent_color' => $accentColor,
            'gradient' => $gradient,
            'icon' => (string) ($ad['icon'] ?? 'bi-stars'),
            'svg_icon' => $svgIcon,
            'disclosure_type' => (string) ($ad['disclosure_type'] ?? 'promotion'),
            'disclosure_text' => (string) ($ad['disclosure_text'] ?? 'Verified Partner Promotion — Curated across our Ecosystem Studio.'),
            'dismissible' => true,
        ];
    }

    /**
     * Saves or updates an advert in the central atomic JSON store.
     *
     * @param array<string, mixed> $input
     * @return array{success: bool, error: ?string, id: ?string}
     */
    public static function saveAdvert(array $input): array
    {
        // 1. Validation & Sanitization
        $headline = trim(strip_tags((string) ($input['headline'] ?? $input['title'] ?? '')));
        if ($headline === '') {
            return ['success' => false, 'error' => 'Headline/Title is required.', 'id' => null];
        }

        $targetUrl = trim((string) ($input['target_url'] ?? ''));
        if (!filter_var($targetUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $targetUrl)) {
            return ['success' => false, 'error' => 'A valid destination URL starting with http:// or https:// is required.', 'id' => null];
        }

        $all = self::getAllAdverts();

        $id = !empty($input['id']) ? trim((string) $input['id']) : 'ad_' . bin2hex(random_bytes(6));
        $brandName = trim(strip_tags((string) ($input['brand_name'] ?? '')));
        $tagline = trim(strip_tags((string) ($input['tagline'] ?? '')));
        $description = trim(strip_tags((string) ($input['description'] ?? '')));
        $ctaText = trim(strip_tags((string) ($input['cta_text'] ?? 'Explore')));
        $badge = trim(strip_tags((string) ($input['badge'] ?? 'FEATURED')));
        $accentColor = trim((string) ($input['accent_color'] ?? '#6366f1'));
        if (!preg_match('/^#[a-f0-9]{3,6}$/i', $accentColor)) {
            $accentColor = '#6366f1';
        }

        // App targets
        $targetApps = [];
        if (!empty($input['target_apps']) && is_array($input['target_apps'])) {
            foreach ($input['target_apps'] as $app) {
                if (is_string($app)) {
                    $targetApps[] = strtolower(trim($app));
                }
            }
        }
        if (empty($targetApps) || in_array('all', $targetApps, true)) {
            $targetApps = ['all'];
        }

        $active = !empty($input['active']);
        $mode = in_array($input['mode'] ?? '', ['exclusive', 'blended'], true) ? (string) $input['mode'] : 'exclusive';

        $campaignSlug = preg_replace('/[^a-z0-9_-]/', '', strtolower(str_replace(' ', '-', $headline)));
        if (empty($campaignSlug)) {
            $campaignSlug = 'campaign-' . substr($id, 0, 6);
        }

        $record = [
            'id' => $id,
            'headline' => $headline,
            'title' => $headline,
            'brand_name' => $brandName !== '' ? $brandName : $headline,
            'tagline' => $tagline,
            'description' => $description,
            'cta_text' => $ctaText !== '' ? $ctaText : 'Explore',
            'target_url' => $targetUrl,
            'badge' => $badge !== '' ? strtoupper($badge) : 'FEATURED',
            'accent_color' => $accentColor,
            'gradient' => "linear-gradient(135deg, {$accentColor} 0%, #3b82f6 100%)",
            'icon' => 'bi-stars',
            'target_apps' => array_values(array_unique($targetApps)),
            'target_app_key' => trim((string) ($input['target_app_key'] ?? '')),
            'active' => $active,
            'mode' => $mode,
            'campaign_slug' => $campaignSlug,
            'start_at' => !empty($input['start_at']) ? trim((string) $input['start_at']) : null,
            'end_at' => !empty($input['end_at']) ? trim((string) $input['end_at']) : null,
            'updated_at' => date('Y-m-d H:i:s'),
            'created_at' => $all[$id]['created_at'] ?? date('Y-m-d H:i:s'),
        ];

        $all[$id] = $record;

        $persisted = self::persistAdverts($all);
        if (!$persisted) {
            return ['success' => false, 'error' => 'Failed to persist advert to storage file. Check file permissions.', 'id' => null];
        }

        self::$memoryCache = $all;
        return ['success' => true, 'error' => null, 'id' => $id];
    }

    /**
     * Toggles an advert's active status.
     *
     * @param string $id
     * @param bool $active
     * @return bool
     */
    public static function toggleAdvert(string $id, bool $active): bool
    {
        $all = self::getAllAdverts();
        if (!isset($all[$id])) {
            return false;
        }

        $all[$id]['active'] = $active;
        $all[$id]['updated_at'] = date('Y-m-d H:i:s');

        $persisted = self::persistAdverts($all);
        if ($persisted) {
            self::$memoryCache = $all;
        }
        return $persisted;
    }

    /**
     * Deletes an advert by ID.
     *
     * @param string $id
     * @return bool
     */
    public static function deleteAdvert(string $id): bool
    {
        $all = self::getAllAdverts();
        if (!isset($all[$id])) {
            return false;
        }

        unset($all[$id]);
        $persisted = self::persistAdverts($all);
        if ($persisted) {
            self::$memoryCache = $all;
        }
        return $persisted;
    }

    /**
     * Atomically writes adverts to the central storage file.
     *
     * @param array<string, array<string, mixed>> $data
     * @return bool
     */
    private static function persistAdverts(array $data): bool
    {
        $path = self::getStoragePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        try {
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return false;
        }

        $tmpFile = $path . '.tmp.' . bin2hex(random_bytes(4));
        $fp = @fopen($tmpFile, 'wb');
        if ($fp === false) {
            return false;
        }

        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $json);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            return @rename($tmpFile, $path);
        }

        fclose($fp);
        @unlink($tmpFile);
        return false;
    }

    /**
     * Handles HTTP actions (GET/POST) for the Ecosystem Adverts Management Screen.
     * Can be invoked directly from any portfolio application's admin router or controller.
     *
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     * @param array<string, mixed> $session
     * @return void
     */
    public static function handleAdminRequest(array $get, array $post, array &$session): void
    {
        $action = (string) ($get['action'] ?? $post['action'] ?? 'index');

        // Verify CSRF on state changes
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrfToken = (string) ($post['csrf_token'] ?? '');
            $sessionToken = (string) ($session['csrf_token'] ?? $session['token'] ?? '');
            if ($sessionToken === '' || !hash_equals($sessionToken, $csrfToken)) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Security token (CSRF) validation failed.']);
                exit;
            }

            if ($action === 'save') {
                $result = self::saveAdvert($post);
                if (!empty($post['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode($result);
                    exit;
                }
                header('Location: ' . strtok($_SERVER['REQUEST_URI'] ?? '', '?') . '?msg=' . ($result['success'] ? 'saved' : 'error'));
                exit;
            }

            if ($action === 'toggle') {
                $id = (string) ($post['id'] ?? '');
                $active = !empty($post['active']);
                $success = self::toggleAdvert($id, $active);
                header('Content-Type: application/json');
                echo json_encode(['success' => $success]);
                exit;
            }

            if ($action === 'delete') {
                $id = (string) ($post['id'] ?? '');
                $success = self::deleteAdvert($id);
                header('Content-Type: application/json');
                echo json_encode(['success' => $success]);
                exit;
            }
        }

        // Render full Admin UI
        $csrfToken = (string) ($session['csrf_token'] ?? $session['token'] ?? bin2hex(random_bytes(16)));
        $session['csrf_token'] = $csrfToken;

        echo self::renderAdminView([
            'csrf_token' => $csrfToken,
            'msg' => (string) ($get['msg'] ?? ''),
        ]);
        exit;
    }

    /**
     * Renders the complete, responsive, glassmorphic Admin UX interface.
     * Contains real-time interactive preview card, responsive form, and campaigns table.
     *
     * @param array<string, mixed> $params
     * @return string
     */
    public static function renderAdminView(array $params = []): string
    {
        $adverts = self::getAllAdverts();
        $csrfToken = htmlspecialchars((string) ($params['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');
        $msg = (string) ($params['msg'] ?? '');

        $appsList = [
            'partyplatform' => 'PartyPlatform',
            'familyplatform' => 'FamilyPlatform',
            'loaneasyfinance' => 'LoanEasyFinance',
            'idecide' => 'iDecide',
            'iaccount' => 'iAccountApp',
            'scoretenant' => 'ScoreTenant',
            'execmind' => 'ExecMind',
            'bcp' => 'Best Care Professionals',
        ];

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ecosystem Advert Manager — Centralized Studio Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --eco-bg: #0f172a;
            --eco-card-bg: rgba(30, 41, 59, 0.7);
            --eco-card-border: rgba(255, 255, 255, 0.08);
            --eco-accent: #6366f1;
            --eco-text: #f8fafc;
            --eco-muted: #94a3b8;
        }
        body {
            background-color: var(--eco-bg);
            color: var(--eco-text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
            padding-bottom: 60px;
        }
        .eco-glass-card {
            background: var(--eco-card-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--eco-card-border);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
        }
        .eco-preview-banner {
            border-radius: 14px;
            padding: 1.25rem 1.5rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
            transition: all 0.25s ease;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .form-control, .form-select {
            background-color: rgba(15, 23, 42, 0.6) !important;
            border: 1px solid rgba(255, 255, 255, 0.12) !important;
            color: #fff !important;
            border-radius: 10px;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--eco-accent) !important;
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25) !important;
        }
        .badge-pill {
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            padding: 0.35rem 0.65rem;
            border-radius: 9999px;
            font-weight: 700;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }
        .status-dot.active { background-color: #10b981; box-shadow: 0 0 8px #10b981; }
        .status-dot.paused { background-color: #64748b; }
        .btn-primary-gradient {
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            border: none;
            color: white;
            font-weight: 600;
            border-radius: 10px;
            padding: 0.6rem 1.4rem;
        }
        .btn-primary-gradient:hover {
            opacity: 0.95;
            color: white;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="container py-4" style="max-width: 1140px;">
        <!-- Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary border-opacity-25">
            <div>
                <span class="badge bg-primary bg-opacity-25 text-primary border border-primary border-opacity-25 badge-pill mb-2">
                    <i class="bi-broadcast me-1"></i> CENTRAL ECOSYSTEM CONTROL
                </span>
                <h2 class="fw-bold mb-1">Ecosystem Advert & Campaign Manager</h2>
                <p class="text-secondary small mb-0">Create, preview, schedule, and distribute dynamic adverts across all 8 portfolio apps in real time.</p>
            </div>
            <div class="mt-3 mt-md-0 d-flex gap-2">
                <a href="javascript:history.back()" class="btn btn-outline-light btn-sm rounded-pill px-3">
                    <i class="bi-arrow-left me-1"></i> Return to Admin
                </a>
                <button type="button" class="btn btn-primary-gradient btn-sm rounded-pill px-3" onclick="resetFormForNew()">
                    <i class="bi-plus-circle me-1"></i> Create New Advert
                </button>
            </div>
        </div>

        <?php if ($msg === 'saved'): ?>
            <div class="alert alert-success bg-success bg-opacity-10 border border-success border-opacity-25 text-success rounded-4 d-flex align-items-center mb-4">
                <i class="bi-check-circle-fill fs-5 me-2"></i>
                <div>Advert successfully saved and synchronized across the ecosystem!</div>
            </div>
        <?php endif; ?>

        <!-- Active Sister Apps Fallback Banner Notice -->
        <div class="eco-glass-card p-3 mb-4 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="p-2 bg-success bg-opacity-10 text-success rounded-circle me-3">
                    <i class="bi-shield-check fs-4"></i>
                </div>
                <div>
                    <strong class="d-block text-white">Sister Apps Automation: ACTIVE</strong>
                    <span class="text-secondary small">7 portfolio sister apps continue rotating automatically whenever no exclusive custom ad is running.</span>
                </div>
            </div>
            <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-25 badge-pill">
                100% BULLETPROOF FALLBACK
            </span>
        </div>

        <div class="row g-4">
            <!-- Left Column: Form -->
            <div class="col-lg-7">
                <div class="eco-glass-card p-4">
                    <h5 class="fw-bold mb-3 d-flex align-items-center text-white" id="formTitle">
                        <i class="bi-pencil-square text-primary me-2"></i> Configure Advert Campaign
                    </h5>

                    <form id="adForm" method="POST" action="?action=save">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="id" id="adId" value="">

                        <!-- Headline -->
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-semibold">Headline / Hook <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="headline" id="inputHeadline" required
                                placeholder="e.g. Throw Unforgettable Parties or Get 20% Off Launch Special"
                                oninput="updateLivePreview()">
                        </div>

                        <!-- Brand & Tagline -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-secondary small fw-semibold">Brand / Product Name</label>
                                <input type="text" class="form-control" name="brand_name" id="inputBrand"
                                    placeholder="e.g. PartyPlatform" oninput="updateLivePreview()">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small fw-semibold">Category Tagline</label>
                                <input type="text" class="form-control" name="tagline" id="inputTagline"
                                    placeholder="e.g. VIP Celebrations & Ticketing" oninput="updateLivePreview()">
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-semibold">Description / Value Proposition</label>
                            <textarea class="form-control" name="description" id="inputDescription" rows="2"
                                placeholder="Short, compelling sentence explaining the offer in under 20 words."
                                oninput="updateLivePreview()"></textarea>
                        </div>

                        <!-- Destination URL & CTA Text -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-7">
                                <label class="form-label text-secondary small fw-semibold">Destination URL <span class="text-danger">*</span></label>
                                <input type="url" class="form-control" name="target_url" id="inputUrl" required
                                    placeholder="https://example.com/promo" oninput="updateLivePreview()">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label text-secondary small fw-semibold">Button Label</label>
                                <input type="text" class="form-control" name="cta_text" id="inputCta"
                                    placeholder="e.g. Claim Offer →" oninput="updateLivePreview()">
                            </div>
                        </div>

                        <!-- Badge, Color & Mode -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label text-secondary small fw-semibold">Badge Label</label>
                                <input type="text" class="form-control" name="badge" id="inputBadge"
                                    placeholder="e.g. SPECIAL OFFER" oninput="updateLivePreview()">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-secondary small fw-semibold">Accent Color</label>
                                <input type="color" class="form-control form-control-color w-100" name="accent_color" id="inputColor"
                                    value="#6366f1" onchange="updateLivePreview()">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-secondary small fw-semibold">Delivery Mode</label>
                                <select class="form-select" name="mode" id="inputMode">
                                    <option value="exclusive">Exclusive Priority</option>
                                    <option value="blended">Blended (50% with Sisters)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Target Applications -->
                        <div class="mb-4">
                            <label class="form-label text-secondary small fw-semibold d-block">Target Applications</label>
                            <div class="d-flex align-items-center mb-2">
                                <div class="form-check me-3">
                                    <input class="form-check-input" type="checkbox" id="app_all" name="target_apps[]" value="all" checked onchange="toggleAllApps(this)">
                                    <label class="form-check-label fw-bold text-white small" for="app_all">All Applications (Universal)</label>
                                </div>
                            </div>
                            <div class="row g-2 p-2 rounded-3" style="background: rgba(15, 23, 42, 0.4); border: 1px solid rgba(255,255,255,0.06);">
                                <?php foreach ($appsList as $slug => $label): ?>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input specific-app" type="checkbox" id="app_<?= $slug ?>" name="target_apps[]" value="<?= $slug ?>" disabled>
                                            <label class="form-check-label text-secondary small" for="app_<?= $slug ?>"><?= $label ?></label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Status & Submit -->
                        <div class="d-flex align-items-center justify-content-between pt-3 border-top border-secondary border-opacity-25">
                            <div class="form-check form-switch fs-5">
                                <input class="form-check-input" type="checkbox" name="active" value="1" id="inputActive" checked>
                                <label class="form-check-label fs-6 text-white align-middle" for="inputActive">Publish / Activate</label>
                            </div>
                            <button type="submit" class="btn btn-primary-gradient px-4">
                                <i class="bi-cloud-arrow-up me-1"></i> Save & Broadcast Advert
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Column: Real-time Live Preview -->
            <div class="col-lg-5">
                <div class="eco-glass-card p-4 position-sticky" style="top: 20px;">
                    <h5 class="fw-bold mb-1 text-white d-flex align-items-center justify-content-between">
                        <span><i class="bi-eye text-info me-2"></i> Real-time Live Preview</span>
                        <span class="badge bg-secondary bg-opacity-25 text-secondary badge-pill small">WYSIWYG</span>
                    </h5>
                    <p class="text-secondary small mb-3">This is an authentic representation of how visitors will see the ribbon banner on desktop and mobile.</p>

                    <!-- Preview Container -->
                    <div id="previewCard" class="eco-preview-banner" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span id="previewBadge" class="badge badge-pill text-uppercase" style="background: rgba(99, 102, 241, 0.25); color: #a5b4fc; border: 1px solid rgba(99, 102, 241, 0.3);">
                                SPECIAL OFFER
                            </span>
                            <span class="text-white-50 small"><i class="bi-x-lg"></i></span>
                        </div>

                        <div class="d-flex align-items-start gap-3 mt-2">
                            <div id="previewIcon" class="p-2 rounded-3 text-white flex-shrink-0" style="background: rgba(255, 255, 255, 0.1);">
                                <i class="bi-stars fs-4"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div id="previewTagline" class="text-white-50 text-uppercase small" style="font-size: 0.7rem; letter-spacing: 0.05em; font-weight: 600;">
                                    Curated Spotlight
                                </div>
                                <h6 id="previewHeadline" class="fw-bold text-white mb-1" style="font-size: 1.05rem;">
                                    Stop Overthinking. Decide With Confidence.
                                </h6>
                                <p id="previewDesc" class="text-secondary small mb-3" style="line-height: 1.4;">
                                    Clear, simple guides to help you make tough life, money, job, and family choices in under 60 seconds.
                                </p>
                                <a id="previewCta" href="#" target="_blank" class="btn btn-sm rounded-pill px-3 fw-bold text-white shadow-sm" style="background: #6366f1;">
                                    Explore Offer &rarr;
                                </a>
                            </div>
                        </div>

                        <div class="mt-3 pt-2 border-top border-white border-opacity-10 d-flex justify-content-between align-items-center">
                            <span class="text-white-50" style="font-size: 0.65rem;">
                                <i class="bi-info-circle me-1"></i> Verified Partner Promotion — Ecosystem Studio
                            </span>
                        </div>
                    </div>

                    <!-- Target apps summary badge -->
                    <div class="mt-3 p-3 rounded-3" style="background: rgba(15, 23, 42, 0.4); border: 1px solid rgba(255,255,255,0.06);">
                        <div class="text-secondary small fw-semibold mb-1">Target Coverage:</div>
                        <div id="previewTargetSummary" class="text-white small fw-bold">
                            <i class="bi-globe2 text-info me-1"></i> Active across ALL 8 Portfolio Applications
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Existing Adverts Management Table -->
        <div class="eco-glass-card p-4 mt-5">
            <h5 class="fw-bold mb-3 text-white d-flex align-items-center justify-content-between">
                <span><i class="bi-collection-play text-warning me-2"></i> Configured Ecosystem Campaigns (<?= count($adverts) ?>)</span>
            </h5>

            <?php if (empty($adverts)): ?>
                <div class="text-center py-5 text-secondary">
                    <i class="bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                    No custom adverts currently created. The sister apps catalog is currently rotating 100% of impressions.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0" style="background: transparent;">
                        <thead>
                            <tr class="text-secondary small border-secondary border-opacity-25">
                                <th>Status</th>
                                <th>Headline & Details</th>
                                <th>Badge</th>
                                <th>Targets</th>
                                <th>Mode</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($adverts as $ad): ?>
                                <tr class="border-secondary border-opacity-10">
                                    <td>
                                        <button class="btn btn-sm rounded-pill px-2 py-1 <?= !empty($ad['active']) ? 'btn-success bg-opacity-25 text-success border-success' : 'btn-secondary bg-opacity-25 text-secondary border-secondary' ?>"
                                            onclick="toggleAd('<?= htmlspecialchars($ad['id'], ENT_QUOTES, 'UTF-8') ?>', <?= !empty($ad['active']) ? '0' : '1' ?>)">
                                            <span class="status-dot <?= !empty($ad['active']) ? 'active' : 'paused' ?>"></span>
                                            <?= !empty($ad['active']) ? 'ACTIVE' : 'PAUSED' ?>
                                        </button>
                                    </td>
                                    <td>
                                        <strong class="d-block text-white"><?= htmlspecialchars((string) ($ad['headline'] ?? $ad['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                        <a href="<?= htmlspecialchars((string) ($ad['target_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="text-info text-decoration-none small">
                                            <?= htmlspecialchars(substr((string) ($ad['target_url'] ?? ''), 0, 45) . '...', ENT_QUOTES, 'UTF-8') ?>
                                            <i class="bi-box-arrow-up-right ms-1" style="font-size: 0.7rem;"></i>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge badge-pill" style="background: rgba(99,102,241,0.2); color: #a5b4fc; border: 1px solid rgba(99,102,241,0.3);">
                                            <?= htmlspecialchars((string) ($ad['badge'] ?? 'PROMO'), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $t = (array) ($ad['target_apps'] ?? ['all']);
                                        if (in_array('all', $t, true)):
                                        ?>
                                            <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25">All Apps</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary bg-opacity-25 text-white"><?= count($t) ?> Apps</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="small text-secondary"><?= htmlspecialchars((string) ($ad['mode'] ?? 'exclusive'), ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary rounded-pill me-1" onclick='editAd(<?= json_encode($ad, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                            <i class="bi-pencil"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger rounded-pill" onclick="deleteAd('<?= htmlspecialchars($ad['id'], ENT_QUOTES, 'UTF-8') ?>')">
                                            <i class="bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const csrfToken = "<?= $csrfToken ?>";

        function updateLivePreview() {
            const headline = document.getElementById('inputHeadline').value || 'Stop Overthinking. Decide With Confidence.';
            const tagline = document.getElementById('inputTagline').value || 'Curated Spotlight';
            const desc = document.getElementById('inputDescription').value || 'Clear, simple guides to help you make tough life, money, job, and family choices in under 60 seconds.';
            const cta = document.getElementById('inputCta').value || 'Explore Offer →';
            const badge = (document.getElementById('inputBadge').value || 'SPECIAL OFFER').toUpperCase();
            const color = document.getElementById('inputColor').value || '#6366f1';

            document.getElementById('previewHeadline').innerText = headline;
            document.getElementById('previewTagline').innerText = tagline;
            document.getElementById('previewDesc').innerText = desc;
            document.getElementById('previewCta').innerText = cta;
            document.getElementById('previewCta').style.backgroundColor = color;
            document.getElementById('previewBadge').innerText = badge;
            document.getElementById('previewBadge').style.borderColor = color;
            document.getElementById('previewBadge').style.color = color;

            const allCheckbox = document.getElementById('app_all');
            const summary = document.getElementById('previewTargetSummary');
            if (allCheckbox.checked) {
                summary.innerHTML = '<i class="bi-globe2 text-info me-1"></i> Active across ALL 8 Portfolio Applications';
            } else {
                const checked = Array.from(document.querySelectorAll('.specific-app:checked')).map(el => el.nextElementSibling.innerText);
                summary.innerHTML = checked.length > 0 
                    ? '<i class="bi-check-circle text-success me-1"></i> Targeted to: ' + checked.join(', ')
                    : '<i class="bi-exclamation-triangle text-warning me-1"></i> No apps selected (will not be visible)';
            }
        }

        function toggleAllApps(el) {
            const specific = document.querySelectorAll('.specific-app');
            specific.forEach(cb => {
                cb.disabled = el.checked;
                if (el.checked) cb.checked = false;
            });
            updateLivePreview();
        }

        document.querySelectorAll('.specific-app').forEach(cb => {
            cb.addEventListener('change', updateLivePreview);
        });

        function resetFormForNew() {
            document.getElementById('adId').value = '';
            document.getElementById('adForm').reset();
            document.getElementById('app_all').checked = true;
            toggleAllApps(document.getElementById('app_all'));
            document.getElementById('formTitle').innerHTML = '<i class="bi-plus-circle text-primary me-2"></i> Create New Advert Campaign';
            updateLivePreview();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function editAd(ad) {
            document.getElementById('adId').value = ad.id || '';
            document.getElementById('inputHeadline').value = ad.headline || ad.title || '';
            document.getElementById('inputBrand').value = ad.brand_name || '';
            document.getElementById('inputTagline').value = ad.tagline || '';
            document.getElementById('inputDescription').value = ad.description || '';
            document.getElementById('inputUrl').value = ad.target_url || '';
            document.getElementById('inputCta').value = ad.cta_text || '';
            document.getElementById('inputBadge').value = ad.badge || '';
            document.getElementById('inputColor').value = ad.accent_color || '#6366f1';
            document.getElementById('inputMode').value = ad.mode || 'exclusive';
            document.getElementById('inputActive').checked = !!ad.active;

            const targetApps = ad.target_apps || ['all'];
            if (targetApps.includes('all')) {
                document.getElementById('app_all').checked = true;
                toggleAllApps(document.getElementById('app_all'));
            } else {
                document.getElementById('app_all').checked = false;
                toggleAllApps(document.getElementById('app_all'));
                document.querySelectorAll('.specific-app').forEach(cb => {
                    cb.checked = targetApps.includes(cb.value);
                });
            }

            document.getElementById('formTitle').innerHTML = '<i class="bi-pencil text-warning me-2"></i> Edit Advert: ' + (ad.headline || '');
            updateLivePreview();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function toggleAd(id, newState) {
            fetch('?action=toggle', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ id: id, active: newState, csrf_token: csrfToken })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error toggling advert state.');
                }
            });
        }

        function deleteAd(id) {
            if (!confirm('Are you sure you want to delete this advert campaign?')) return;

            fetch('?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ id: id, csrf_token: csrfToken })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error deleting advert.');
                }
            });
        }

        // Initialize preview on page load
        updateLivePreview();
    </script>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }
}
