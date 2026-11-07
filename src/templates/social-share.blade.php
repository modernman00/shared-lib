@php
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
    $httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $rawBaseUrl = !empty($baseUrl) ? $baseUrl : (!empty($_ENV['APP_URL']) ? $_ENV['APP_URL'] : ($protocol . '://' . $httpHost));
    $cleanBaseUrl = rtrim($rawBaseUrl, '/');
    $currentShareUrl = $cleanBaseUrl . (strtok($requestUri, '?') ?: '/');
    
    // Determine Application Name & Tailored Share Copy
    $detectedAppName = $_ENV['APP_NAME'] ?? 'Platform';
    if (str_contains($httpHost, 'partyplatform') || str_contains($cleanBaseUrl, 'partyplatform')) {
        $detectedAppName = 'PartyPlatform';
        $defaultShareTitle = 'PartyPlatform — The Easiest Way to Plan & Celebrate Events';
        $defaultShareText = 'Automate RSVPs, seating charts & live memory walls with PartyPlatform: ' . $cleanBaseUrl;
    } elseif (str_contains($httpHost, 'family') || str_contains($cleanBaseUrl, 'family')) {
        $detectedAppName = 'FamilyPlatform';
        $defaultShareTitle = 'FamilyPlatform — Private Family Social Network & Living Heritage Sanctuary';
        $defaultShareText = 'Preserve precious family memories, build interactive family trees, and stay connected on FamilyPlatform: ' . $cleanBaseUrl;
    } elseif (str_contains($httpHost, 'idecide') || str_contains($cleanBaseUrl, 'idecide')) {
        $detectedAppName = 'iDecide';
        $defaultShareTitle = 'iDecide — Intelligent Decision Matrix & Evidence-Based Choices';
        $defaultShareText = 'Make informed, regret-free decisions backed by empirical evidence on iDecide: ' . $cleanBaseUrl;
    } elseif (str_contains($httpHost, 'iaccount') || str_contains($cleanBaseUrl, 'iaccount')) {
        $detectedAppName = 'iAccount';
        $defaultShareTitle = 'iAccount — Automated Cash Flow & Double-Entry Accounting';
        $defaultShareText = 'Streamline bookkeeping, cash flow, and financial clarity with iAccount: ' . $cleanBaseUrl;
    } elseif (str_contains($httpHost, 'loaneasy') || str_contains($cleanBaseUrl, 'loaneasy')) {
        $detectedAppName = 'LoanEasy Finance';
        $defaultShareTitle = 'LoanEasy Finance — Fast, Transparent & Fair Online Loans';
        $defaultShareText = 'Simple, transparent online finance with instant decisions on LoanEasy Finance: ' . $cleanBaseUrl;
    } elseif (str_contains($httpHost, 'tenant') || str_contains($cleanBaseUrl, 'tenant')) {
        $detectedAppName = 'TenantScore';
        $defaultShareTitle = 'TenantScore — Verified Rental Passport & Trust Scoring';
        $defaultShareText = 'Verify your rental history and secure your dream tenancy faster with TenantScore: ' . $cleanBaseUrl;
    } elseif (str_contains($httpHost, 'execmind') || str_contains($cleanBaseUrl, 'execmind')) {
        $detectedAppName = 'ExecMind';
        $defaultShareTitle = 'ExecMind — Cognitive Governance & Strategic Advisory Intelligence';
        $defaultShareText = 'Enterprise executive decision support and market intelligence powered by ExecMind: ' . $cleanBaseUrl;
    } elseif (str_contains($httpHost, 'bestcare') || str_contains($cleanBaseUrl, 'bestcare') || str_contains($httpHost, 'bcp')) {
        $detectedAppName = 'Best Care Professionals';
        $defaultShareTitle = 'Best Care Professionals — 24/7 Healthcare Staffing & Emergency Carer Recruitment';
        $defaultShareText = 'Compliant nurses, healthcare assistants, and specialist support workers with 15-minute response dispatch: ' . $cleanBaseUrl;
    } else {
        $defaultShareTitle = $detectedAppName . ' — Leading Digital Platform';
        $defaultShareText = 'Check out ' . $detectedAppName . ': ' . $cleanBaseUrl;
    }

    $finalShareTitle = $shareTitle ?? $defaultShareTitle;
    $finalShareText = $shareText ?? $defaultShareText;
    $encUrl = rawurlencode($currentShareUrl);
    $encTitle = rawurlencode($finalShareTitle);
    $encText = rawurlencode($finalShareText);
    $encWa = rawurlencode($finalShareText . ' ' . $currentShareUrl);

    // App Logo
    $appLogoUrl = trim($_ENV['APP_LOGO'] ?? '', " '\"") ?: '/apple-touch-icon.png';
    if ($appLogoUrl && !str_starts_with($appLogoUrl, 'http')) {
        $appLogoUrl = $cleanBaseUrl . (str_starts_with($appLogoUrl, '/') ? '' : '/') . $appLogoUrl;
    }
@endphp

<style nonce="{{ $nonce ?? '' }}">
    /* ══════════════════════════════════════════════════════════════════════
       PORTFOLIO UNIVERSAL SOCIAL SHARE DOCK (Desktop & Mobile)
       Strict v2.0 Design Standard: Fitts's Law >=44px, WCAG AA Contrast,
       Zero Mid-Word Truncation, Tactile Micro-Interactions
       ══════════════════════════════════════════════════════════════════════ */
    #universalSocialSidebar {
        position: fixed;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        z-index: 1040;
        display: flex;
        flex-direction: column;
        transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease;
        box-shadow: 2px 4px 20px rgba(0, 0, 0, 0.15);
        border-top-right-radius: 12px;
        border-bottom-right-radius: 12px;
        overflow: hidden;
    }

    .uni-social-item {
        width: 48px;
        min-width: 48px;
        height: 48px;
        min-height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff !important;
        text-decoration: none !important;
        border: none;
        outline: none;
        padding: 0;
        margin: 0;
        cursor: pointer;
        position: relative;
        transition: all 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        box-sizing: border-box;
    }

    .uni-social-item svg {
        width: 20px;
        height: 20px;
        fill: currentColor;
        pointer-events: none;
        transition: transform 0.2s ease;
    }

    .uni-social-item:hover {
        width: 62px;
        padding-left: 12px;
        filter: brightness(1.1);
        border-top-right-radius: 6px;
        border-bottom-right-radius: 6px;
    }

    .uni-social-item:hover svg {
        transform: scale(1.15);
    }

    .uni-social-item:active {
        transform: scale(0.96);
    }

    .uni-social-item:focus-visible {
        outline: 2px solid #ffffff;
        outline-offset: -2px;
    }

    /* Authentic 8-Channel Palette from System Spec */
    .uni-social-logo      { background: #0f172a; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
    .uni-social-linkedin  { background-color: #0077b5; }
    .uni-social-facebook  { background-color: #3b5998; }
    .uni-social-share     { background-color: #7bc043; }
    .uni-social-viber     { background-color: #665cac; }
    .uni-social-whatsapp  { background-color: #25d366; }
    .uni-social-x         { background-color: #000000; }
    .uni-social-sms       { background-color: #ffb400; color: #1e293b !important; }
    .uni-social-sms svg   { fill: #1e293b; }
    .uni-social-copy      { background-color: #1e6823; }

    /* Mobile Floating Action Button (Viewport < 992px) */
    #universalMobileShareTrigger {
        position: fixed;
        bottom: 24px;
        right: 20px;
        width: 50px;
        min-width: 50px;
        height: 50px;
        min-height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, #2563eb, #7c3aed);
        color: #ffffff;
        box-shadow: 0 8px 24px rgba(37, 99, 235, 0.45);
        border: 2px solid rgba(255, 255, 255, 0.3);
        z-index: 1045;
        display: none;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        padding: 0;
        margin: 0;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    #universalMobileShareTrigger:hover {
        transform: scale(1.08);
        box-shadow: 0 12px 30px rgba(124, 58, 237, 0.55);
    }

    #universalMobileShareTrigger:active {
        transform: scale(0.92);
    }

    #universalMobileShareTrigger svg {
        width: 22px;
        height: 22px;
        fill: #ffffff;
    }

    @media (max-width: 991.98px) {
        #universalSocialSidebar {
            display: none !important;
        }
        #universalMobileShareTrigger {
            display: flex !important;
        }
    }

    /* Universal Glassmorphic Modal Drawer */
    #universalShareModalOverlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(15, 23, 42, 0.78);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        z-index: 99999;
        display: none;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        padding: 16px;
        box-sizing: border-box;
    }

    #universalShareModalOverlay.uni-show {
        display: flex !important;
        opacity: 1;
    }

    .uni-share-dialog {
        background: #0f172a;
        color: #f8fafc;
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 20px;
        max-width: 520px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.85);
        padding: 24px;
        box-sizing: border-box;
        transform: scale(0.95) translateY(12px);
        transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }

    #universalShareModalOverlay.uni-show .uni-share-dialog {
        transform: scale(1) translateY(0);
    }

    .uni-modal-grid-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 58px;
        border-radius: 12px;
        text-decoration: none !important;
        color: #ffffff !important;
        font-size: 0.75rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: transform 0.2s ease, filter 0.2s ease;
        padding: 6px 4px;
        box-sizing: border-box;
    }

    .uni-modal-grid-item svg {
        width: 22px;
        height: 22px;
        fill: currentColor;
    }

    .uni-modal-grid-item:hover {
        filter: brightness(1.12);
        transform: translateY(-2px);
    }

    .uni-modal-grid-item:active {
        transform: scale(0.96);
    }
</style>

<!-- ══════════════════════════════════════════════════════════════════════
     DESKTOP SOCIAL SHARE SIDEBAR (Docked to Left Margin)
     ══════════════════════════════════════════════════════════════════════ -->
<div id="universalSocialSidebar" aria-label="Social Share Sidebar" role="region">
    <!-- Brand / Logo Square -->
    <a href="{{ $cleanBaseUrl }}" class="uni-social-item uni-social-logo" title="{{ htmlspecialchars($detectedAppName) }}" aria-label="{{ htmlspecialchars($detectedAppName) }}">
        <img src="{{ $appLogoUrl }}" alt="{{ htmlspecialchars($detectedAppName) }}" width="26" height="26" style="border-radius: 6px; object-fit: contain;" onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='block';">
        <svg style="display:none; width: 22px; height: 22px; fill: #38bdf8;" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zm0 9l2.5-1.25L12 8.5l-2.5 1.25L12 11zm0 2.5l-5-2.5-5 2.5 10 5 10-5-5-2.5-5 2.5z"/></svg>
    </a>

    <!-- 1. LinkedIn -->
    <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ $encUrl }}" target="_blank" rel="noopener noreferrer" class="uni-social-item uni-social-linkedin" title="Share on LinkedIn" aria-label="Share on LinkedIn">
        <svg viewBox="0 0 24 24"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.46 8.76c-.85 0-1.54-.69-1.54-1.54s.69-1.54 1.54-1.54 1.54.69 1.54 1.54-.69 1.54-1.54 1.54m1.39 9.74v-8.37H5.07v8.37h2.78z"/></svg>
    </a>

    <!-- 2. Facebook -->
    <a href="https://www.facebook.com/sharer/sharer.php?u={{ $encUrl }}" target="_blank" rel="noopener noreferrer" class="uni-social-item uni-social-facebook" title="Share on Facebook" aria-label="Share on Facebook">
        <svg viewBox="0 0 24 24"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/></svg>
    </a>

    <!-- 3. Web Share API / Universal Trigger -->
    <button type="button" class="uni-social-item uni-social-share" onclick="handleUniversalSocialShare()" title="Share Link" aria-label="Share Link">
        <svg viewBox="0 0 24 24"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/></svg>
    </button>

    <!-- 4. Viber -->
    <a href="viber://forward?text={{ $encWa }}" class="uni-social-item uni-social-viber" title="Share on Viber" aria-label="Share on Viber">
        <svg viewBox="0 0 24 24"><path d="M19.9 14.5c-.5-.3-1.9-.9-2.2-1-.3-.1-.5-.1-.7.2s-.8 1-1 1.2c-.2.2-.4.2-.7.1s-1.3-.5-2.5-1.5c-.9-.8-1.5-1.8-1.7-2.1-.2-.3 0-.5.1-.7.1-.1.3-.4.5-.6.2-.2.2-.3.3-.5.1-.2 0-.4 0-.5s-.7-1.7-1-2.3c-.3-.6-.5-.5-.7-.5h-.6c-.2 0-.6.1-.9.4s-1.2 1.2-1.2 2.9 1.2 3.4 1.4 3.6c.2.3 2.4 3.7 5.9 5.2 2.9 1.2 3.5 1 4.1.9.6-.1 1.9-.8 2.2-1.5.3-.8.3-1.4.2-1.5-.1-.2-.3-.3-.8-.5z"/></svg>
    </a>

    <!-- 5. WhatsApp -->
    <a href="https://api.whatsapp.com/send?text={{ $encWa }}" target="_blank" rel="noopener noreferrer" class="uni-social-item uni-social-whatsapp" title="Share on WhatsApp" aria-label="Share on WhatsApp">
        <svg viewBox="0 0 24 24"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm5.78 14.07c-.24.68-1.4 1.26-1.92 1.31-.5.05-1.12.07-3.61-.95-2.12-.87-3.5-3.03-3.61-3.17-.11-.14-.85-1.13-.85-2.16s.54-1.53.73-1.74c.2-.2.43-.25.57-.25.14 0 .29 0 .41.01.14.01.32-.05.5.38.2.47.68 1.66.74 1.78.06.12.1.26.02.41-.08.15-.12.25-.24.38-.12.14-.25.31-.36.42-.12.12-.24.25-.1.5.14.24.63 1.04 1.36 1.69.94.84 1.73 1.1 1.97 1.22.25.12.39.1.53-.06.15-.16.63-.73.8-1 .17-.26.34-.22.58-.13.24.09 1.52.72 1.78.85.26.13.43.2.5.31.06.12.06.7-.18 1.38z"/></svg>
    </a>

    <!-- 6. X (formerly Twitter) -->
    <a href="https://twitter.com/intent/tweet?text={{ $encTitle }}&url={{ $encUrl }}" target="_blank" rel="noopener noreferrer" class="uni-social-item uni-social-x" title="Share on X" aria-label="Share on X">
        <svg viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
    </a>

    <!-- 7. SMS -->
    <a href="sms:?&body={{ $encWa }}" class="uni-social-item uni-social-sms" title="Share via SMS" aria-label="Share via SMS">
        <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM9 11H7V9h2v2zm4 0h-2V9h2v2zm4 0h-2V9h2v2z"/></svg>
    </a>

    <!-- 8. Copy Link -->
    <button type="button" class="uni-social-item uni-social-copy" onclick="copyUniversalShareLink(this)" title="Copy Link" aria-label="Copy Link" id="uniSidebarCopyBtn">
        <svg viewBox="0 0 24 24"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
    </button>
</div>

<!-- ══════════════════════════════════════════════════════════════════════
     MOBILE FLOATING SHARE TRIGGER (Viewport < 992px)
     ══════════════════════════════════════════════════════════════════════ -->
<button type="button" id="universalMobileShareTrigger" onclick="openUniversalShareModal()" aria-label="Share to WhatsApp &amp; Social Media" title="Share">
    <svg viewBox="0 0 24 24"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/></svg>
</button>

<!-- ══════════════════════════════════════════════════════════════════════
     UNIVERSAL GLASSMORPHIC SHARE MODAL
     ══════════════════════════════════════════════════════════════════════ -->
<div id="universalShareModalOverlay" role="dialog" aria-modal="true" aria-labelledby="uniShareModalTitle">
    <div class="uni-share-dialog">
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <img src="{{ $appLogoUrl }}" alt="{{ htmlspecialchars($detectedAppName) }}" width="36" height="36" style="border-radius: 10px; object-fit: contain;" onerror="this.onerror=null; this.style.display='none';">
                <div>
                    <h3 id="uniShareModalTitle" style="margin: 0; font-size: 1.2rem; font-weight: 700; color: #ffffff;">Share {{ htmlspecialchars($detectedAppName) }}</h3>
                    <p style="margin: 2px 0 0 0; font-size: 0.8rem; color: #94a3b8;">Distribute link &amp; invite your network</p>
                </div>
            </div>
            <button type="button" onclick="closeUniversalShareModal()" aria-label="Close share dialogue" style="background: rgba(255,255,255,0.08); border: none; color: #cbd5e1; width: 44px; height: 44px; border-radius: 50%; cursor: pointer; font-size: 1.4rem; display: flex; align-items: center; justify-content: center; transition: background 0.2s ease;">&times;</button>
        </div>

        <!-- High-Impact 1-Tap WhatsApp Button -->
        <a href="https://api.whatsapp.com/send?text={{ $encWa }}" target="_blank" rel="noopener noreferrer" style="display: flex; align-items: center; justify-content: center; gap: 10px; background: #25D366; color: #ffffff !important; text-decoration: none !important; border-radius: 14px; padding: 12px 18px; font-weight: 700; font-size: 1.02rem; margin-bottom: 20px; box-shadow: 0 6px 20px rgba(37, 211, 102, 0.35); min-height: 48px;">
            <svg style="width: 22px; height: 22px; fill: #ffffff;" viewBox="0 0 24 24"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm5.78 14.07c-.24.68-1.4 1.26-1.92 1.31-.5.05-1.12.07-3.61-.95-2.12-.87-3.5-3.03-3.61-3.17-.11-.14-.85-1.13-.85-2.16s.54-1.53.73-1.74c.2-.2.43-.25.57-.25.14 0 .29 0 .41.01.14.01.32-.05.5.38.2.47.68 1.66.74 1.78.06.12.1.26.02.41-.08.15-.12.25-.24.38-.12.14-.25.31-.36.42-.12.12-.24.25-.1.5.14.24.63 1.04 1.36 1.69.94.84 1.73 1.1 1.97 1.22.25.12.39.1.53-.06.15-.16.63-.73.8-1 .17-.26.34-.22.58-.13.24.09 1.52.72 1.78.85.26.13.43.2.5.31.06.12.06.7-.18 1.38z"/></svg>
            <span>Share via WhatsApp</span>
        </a>

        <!-- 8 Platforms Grid -->
        <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; font-weight: 700; margin-bottom: 10px;">Share directly to channels</div>
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px;">
            <!-- WhatsApp -->
            <a href="https://api.whatsapp.com/send?text={{ $encWa }}" target="_blank" rel="noopener noreferrer" class="uni-modal-grid-item" style="background: #25d366;">
                <svg viewBox="0 0 24 24"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2z"/></svg>
                <span>WhatsApp</span>
            </a>
            <!-- LinkedIn -->
            <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ $encUrl }}" target="_blank" rel="noopener noreferrer" class="uni-modal-grid-item" style="background: #0077b5;">
                <svg viewBox="0 0 24 24"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.28 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.75M6.46 8.76c-.85 0-1.54-.69-1.54-1.54s.69-1.54 1.54-1.54 1.54.69 1.54 1.54-.69 1.54-1.54 1.54m1.39 9.74v-8.37H5.07v8.37h2.78z"/></svg>
                <span>LinkedIn</span>
            </a>
            <!-- Facebook -->
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ $encUrl }}" target="_blank" rel="noopener noreferrer" class="uni-modal-grid-item" style="background: #3b5998;">
                <svg viewBox="0 0 24 24"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/></svg>
                <span>Facebook</span>
            </a>
            <!-- X -->
            <a href="https://twitter.com/intent/tweet?text={{ $encTitle }}&url={{ $encUrl }}" target="_blank" rel="noopener noreferrer" class="uni-modal-grid-item" style="background: #000000; border: 1px solid rgba(255,255,255,0.2);">
                <svg viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                <span>X</span>
            </a>
            <!-- Viber -->
            <a href="viber://forward?text={{ $encWa }}" class="uni-modal-grid-item" style="background: #665cac;">
                <svg viewBox="0 0 24 24"><path d="M19.9 14.5c-.5-.3-1.9-.9-2.2-1-.3-.1-.5-.1-.7.2s-.8 1-1 1.2c-.2.2-.4.2-.7.1s-1.3-.5-2.5-1.5c-.9-.8-1.5-1.8-1.7-2.1-.2-.3 0-.5.1-.7.1-.1.3-.4.5-.6.2-.2.2-.3.3-.5.1-.2 0-.4 0-.5s-.7-1.7-1-2.3c-.3-.6-.5-.5-.7-.5h-.6c-.2 0-.6.1-.9.4s-1.2 1.2-1.2 2.9 1.2 3.4 1.4 3.6c.2.3 2.4 3.7 5.9 5.2 2.9 1.2 3.5 1 4.1.9.6-.1 1.9-.8 2.2-1.5.3-.8.3-1.4.2-1.5-.1-.2-.3-.3-.8-.5z"/></svg>
                <span>Viber</span>
            </a>
            <!-- SMS -->
            <a href="sms:?&body={{ $encWa }}" class="uni-modal-grid-item" style="background: #ffb400; color: #1e293b !important;">
                <svg style="fill: #1e293b;" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM9 11H7V9h2v2zm4 0h-2V9h2v2zm4 0h-2V9h2v2z"/></svg>
                <span>SMS</span>
            </a>
            <!-- Native Device Share -->
            <button type="button" onclick="handleUniversalNativeShare()" class="uni-modal-grid-item" style="background: #7bc043;">
                <svg viewBox="0 0 24 24"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/></svg>
                <span>Device</span>
            </button>
            <!-- Copy Link -->
            <button type="button" onclick="copyUniversalShareLink(this)" class="uni-modal-grid-item" style="background: #1e6823;">
                <svg viewBox="0 0 24 24"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
                <span>Copy</span>
            </button>
        </div>

        <!-- Copy Link Input Box -->
        <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; font-weight: 700; margin-bottom: 8px;">Or copy page link</div>
        <div style="display: flex; gap: 8px;">
            <input type="text" id="uniShareLinkInput" value="{{ $currentShareUrl }}" readonly style="flex: 1; background: #020617; color: #f8fafc; border: 1px solid rgba(255,255,255,0.18); border-radius: 10px; padding: 10px 14px; font-size: 0.88rem; outline: none; font-family: monospace;">
            <button type="button" id="btnCopyUniModalLink" onclick="copyUniversalShareLink(this)" style="background: #1e6823; border: none; color: #ffffff; font-weight: 700; border-radius: 10px; padding: 0 18px; cursor: pointer; min-height: 44px; display: flex; align-items: center; gap: 6px; white-space: nowrap; flex-shrink: 0;">
                <span>Copy</span>
            </button>
        </div>
    </div>
</div>

<script nonce="{{ $nonce ?? '' }}">
    const universalShareData = {
        title: "{{ addslashes($finalShareTitle) }}",
        text: "{{ addslashes($finalShareText) }}",
        url: "{{ $currentShareUrl }}"
    };

    function openUniversalShareModal() {
        const modal = document.getElementById('universalShareModalOverlay');
        if (modal) {
            modal.style.display = 'flex';
            requestAnimationFrame(() => modal.classList.add('uni-show'));
        }
    }

    function closeUniversalShareModal() {
        const modal = document.getElementById('universalShareModalOverlay');
        if (modal) {
            modal.classList.remove('uni-show');
            setTimeout(() => { modal.style.display = 'none'; }, 250);
        }
    }

    async function handleUniversalSocialShare() {
        if (navigator.share) {
            try {
                await navigator.share(universalShareData);
                return;
            } catch (err) {
                if (err && err.name !== 'AbortError') {
                    console.warn('[SocialShare] Native share fallback:', err);
                } else if (err && err.name === 'AbortError') {
                    return;
                }
            }
        }
        openUniversalShareModal();
    }

    async function handleUniversalNativeShare() {
        if (navigator.share) {
            try {
                await navigator.share(universalShareData);
                closeUniversalShareModal();
                return;
            } catch (err) {
                if (err && err.name !== 'AbortError') {
                    console.warn('[SocialShare] Native share failed:', err);
                }
            }
        }
        const input = document.getElementById('uniShareLinkInput');
        if (input) input.select();
    }

    function copyUniversalShareLink(btn) {
        const urlToCopy = universalShareData.url;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(urlToCopy).then(() => {
                showCopiedFeedback(btn);
            }).catch(() => {
                fallbackCopyPrompt(urlToCopy);
            });
        } else {
            fallbackCopyPrompt(urlToCopy);
        }
    }

    function showCopiedFeedback(btn) {
        if (!btn) return;
        const originalContent = btn.innerHTML;
        btn.innerHTML = '<span style="color:#4ade80; font-weight:700;">✓ Copied!</span>';
        setTimeout(() => {
            btn.innerHTML = originalContent;
        }, 2000);
    }

    function fallbackCopyPrompt(url) {
        const input = document.getElementById('uniShareLinkInput');
        if (input) {
            input.focus();
            input.select();
        }
        window.prompt('Copy link to clipboard:', url);
    }

    // Dismiss on backdrop click & ESC
    document.addEventListener('click', (e) => {
        const modal = document.getElementById('universalShareModalOverlay');
        if (modal && e.target === modal) closeUniversalShareModal();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeUniversalShareModal();
    });
</script>
