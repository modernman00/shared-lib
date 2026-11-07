<?php

declare(strict_types=1);

namespace Src\WorldEngine;

/**
 * WorldStudio — renders the ATG World Engine studio (Explainer videos + Posters).
 *
 *   echo WorldStudio::renderSection('partyplatform');          // embed in an admin dashboard
 *   (new WorldStudio())->showPage('familyplatform');           // full standalone page
 *   (new WorldStudio())->serveFont($_GET['file'] ?? '');       // optional: self-host fonts
 *
 * Everything runs in the browser: zero SaaS, zero API keys. Scripts are inlined with the
 * page's CSP nonce, so it works under the shared `script-src 'nonce-…'` policy.
 * Fonts load from `fontBase` (if the app routes serveFont) and otherwise fall back to the
 * Google Fonts CDN (already allowed by the shared CSP), then to system fonts.
 */
final class WorldStudio
{
    /** Google Fonts CDN fallbacks for the bundled OFL fonts (used only if self-hosting is not routed). */
    private const FONT_CDN = [
        'caveat' => 'https://fonts.gstatic.com/s/caveat/v23/WnznHAc5bAfYB2QRah7pcpNvOx-pjRV6eIWpYT5Kmgq3sw.woff2',
        'fredoka' => 'https://fonts.gstatic.com/s/fredoka/v17/X7nP4b87HvSqjb_WIi2yDCRwoQ_k7367_B-i2yQag0-mac3OLyX8EemKttxNbikt.woff2',
        'luckiest' => 'https://fonts.gstatic.com/s/luckiestguy/v25/_gP_1RrxsjcxVyin9l9n_j2hTd52ijl7aQ.woff2',
        'anton' => 'https://fonts.gstatic.com/s/anton/v27/1Ptgg87LROyAm3Kz-C8CSKlv.woff2',
        'bricolage' => 'https://fonts.gstatic.com/s/bricolagegrotesque/v9/3y9H6as8bTXq_nANBjzKo3IeZx8z6up5BeSl9D4dj_x9PpZBMnuECoAsyJBOm_OJ2iCwA1XphjhQYg.woff2',
        'fraunces' => 'https://fonts.gstatic.com/s/fraunces/v38/6NUu8FyLNQOQZAnv9bYEvDiIdE9Ea92uemAk_WBq8U_9v0c2Wa0K7iN7iQcIfJD58niz0oc7qv8oRcTn.woff2',
        'spacemono' => 'https://fonts.gstatic.com/s/spacemono/v17/i7dMIFZifjKcF5UAWdDRaPpZUFWaHi6WZ3Q.woff2',
        'monoton' => 'https://fonts.gstatic.com/s/monoton/v22/5h1aiZUrOngCibe4TkHLQka4BU4.woff2',
        'shrikhand' => 'https://fonts.gstatic.com/s/shrikhand/v17/a8IbNovtLWfR7T7bMJwrA4KR8TtctQ.woff2',
        'bungee' => 'https://fonts.gstatic.com/s/bungee/v17/N0bU2SZBIuF2PU_0DXR1C9zfmQ.woff2',
        'marker' => 'https://fonts.gstatic.com/s/permanentmarker/v16/Fh4uPib9Iyv2ucM6pGQMWimMp004La2Cf5b6jlg.woff2',
        'dmserif' => 'https://fonts.gstatic.com/s/dmserifdisplay/v17/-nFnOHM81r4j6k0gjAW3mujVU2B2G_Bx0vrx52g.woff2',
        'syne' => 'https://fonts.gstatic.com/s/syne/v24/8vIS7w4qzmVxsWxjBZRjr0FKM_24vg6jTY_vDE5YfqU.woff2',
        'pacifico' => 'https://fonts.gstatic.com/s/pacifico/v23/FwZY7-Qmy14u9lezJ-6H6MmBp0u-.woff2',
        'inter' => 'https://fonts.gstatic.com/s/inter/v20/UcCO3FwrK3iLTeHuS_nVMrMxCp50SjIw2boKoduKmMEVuI6fAZ9hiJ-Ek-_EeA.woff2',
        'interbold' => 'https://fonts.gstatic.com/s/inter/v20/UcCO3FwrK3iLTeHuS_nVMrMxCp50SjIw2boKoduKmMEVuDyYAZ9hiJ-Ek-_EeA.woff2',
    ];

    private const JS_FILES = ['01-core.js', '02-cast.js', '03-audio.js', '04-explainer.js', '05-poster.js', '06-director.js', '07-studio.js'];

    private static ?string $bundleCache = null;

    public static function resourceDir(): string
    {
        return dirname(__DIR__, 2) . '/resources/world-engine';
    }

    /** Concatenated engine bundle (cached per request). */
    public static function scriptBundle(): string
    {
        if (self::$bundleCache !== null) {
            return self::$bundleCache;
        }
        $out = '';
        foreach (self::JS_FILES as $file) {
            $path = self::resourceDir() . '/js/' . $file;
            if (is_file($path)) {
                $out .= (string) file_get_contents($path) . "\n";
            }
        }
        // Never allow a literal closing script tag inside the inline bundle.
        return self::$bundleCache = str_ireplace('</script', '<\/script', $out);
    }

    public static function styles(): string
    {
        $path = self::resourceDir() . '/studio.css';
        return is_file($path) ? str_ireplace('</style', '<\/style', (string) file_get_contents($path)) : '';
    }

    /** Resolve the page's CSP nonce the same way the existing studios do. */
    public static function cspNonce(): string
    {
        $nonce = defined('CSP_NONCE') ? (string) constant('CSP_NONCE') : (string) ($GLOBALS['__cspNonce'] ?? ($_SESSION['csp_nonce'] ?? ''));
        if ($nonce === '' && function_exists('headers_list')) {
            foreach (headers_list() as $hdr) {
                if (stripos($hdr, 'Content-Security-Policy') === 0 && preg_match("/'nonce-([a-zA-Z0-9+\/=_]+)'/", $hdr, $m)) {
                    $nonce = $m[1];
                    break;
                }
            }
        }
        return preg_match('/^[a-zA-Z0-9+\/=_-]{8,128}$/', $nonce) === 1 ? $nonce : '';
    }

    /**
     * Client configuration for one app.
     *
     * @param array<string, mixed> $options fontBase (e.g. '/world-engine/font?file={file}'), aiEndpoint, csrf
     * @return array<string, mixed>
     */
    public static function clientConfig(string $appKey, array $options = []): array
    {
        $cfg = StudioBlueprints::forApp($appKey);
        $cfg['fontUrl'] = isset($options['fontBase']) && is_string($options['fontBase']) ? $options['fontBase'] : '';
        $cfg['fontFallback'] = self::FONT_CDN;
        $cfg['aiEndpoint'] = isset($options['aiEndpoint']) && is_string($options['aiEndpoint']) && str_starts_with($options['aiEndpoint'], '/') ? $options['aiEndpoint'] : null;
        $cfg['csrf'] = isset($options['csrf']) && is_string($options['csrf']) ? $options['csrf'] : null;
        return $cfg;
    }

    /**
     * Embeddable studio section (e.g. inside an admin dashboard).
     *
     * @param array<string, mixed> $options
     */
    public static function renderSection(string $appKey, array $options = []): string
    {
        $nonce = self::cspNonce();
        $na = $nonce !== '' ? ' nonce="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '"' : '';
        $id = 'we-studio-' . bin2hex(random_bytes(4));
        $json = json_encode(self::clientConfig($appKey, $options), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $title = htmlspecialchars((string) ($options['title'] ?? 'World Engine — Explainer & Poster Studio'), ENT_QUOTES, 'UTF-8');

        return '<section class="we-studio-section" aria-label="' . $title . '" style="margin:0 0 2rem">'
            . '<style' . $na . '>' . self::styles() . '</style>'
            . '<div id="' . $id . '"><noscript>The World Engine studio needs JavaScript.</noscript></div>'
            . '<script' . $na . '>' . self::scriptBundle() . "\n"
            . '(function(){var el=document.getElementById(' . json_encode($id) . ');if(el&&window.WorldEngine&&window.WorldEngine.Studio){window.WorldEngine.Studio.mount(el,' . $json . ');}})();'
            . '</script></section>';
    }

    /**
     * Full standalone studio page.
     *
     * @param array<string, mixed> $options
     */
    public function showPage(string $appKey = '', array $options = []): void
    {
        $key = StudioBlueprints::normaliseAppKey($appKey !== '' ? $appKey : (string) ($_GET['app'] ?? ($_ENV['APP_NAME'] ?? 'partyplatform')));
        $cfg = StudioBlueprints::forApp($key);
        $name = htmlspecialchars((string) ($cfg['brand']['name'] ?? 'Studio'), ENT_QUOTES, 'UTF-8');
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo '<!DOCTYPE html><html lang="en-GB"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $name . ' · World Engine Studio</title></head>'
            . '<body style="margin:0;background:#080a14;padding:16px;font-family:system-ui,sans-serif">'
            . self::renderSection($key, $options)
            . '</body></html>';
    }

    /** Serve a bundled font (route this at e.g. /world-engine/font?file={file}). */
    public function serveFont(string $file): void
    {
        $safe = basename($file);
        $path = self::resourceDir() . '/fonts/' . $safe;
        if (preg_match('/^we-font-[a-z]+\.woff2$/', $safe) !== 1 || !is_file($path)) {
            if (!headers_sent()) {
                http_response_code(404);
            }
            echo 'Not found';
            return;
        }
        if (!headers_sent()) {
            header('Content-Type: font/woff2');
            header('Content-Length: ' . (string) filesize($path));
            header('Cache-Control: public, max-age=31536000, immutable');
        }
        readfile($path);
    }
}
