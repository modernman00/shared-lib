# RFC-0013: Sovereign Universal Poster & Flyer Generator Engine (ATG In-House Canva Replacement)

- **Status:** Approved (TAT 2.0 Final Executive Sign-Off by Olutobi)
- **Date:** 2026-11-06
- **Authors:** Maya Lindqvist (ATG Interactive Systems Architect), Callum Wright (Lead Staff Systems Architect), Damilola "Dami" Ogunleye (Principal Systems Architect)
- **Reviewers:** Wally (CEO), Jumoke Olaogun (Special Assistant to the CEO & Chief of Staff), Victor (CTO & Head of BRATS), James (Head of TFT), David (Gatewatcher), Dr. Silas Thorne (Contrarian Lead), Marcus ("Ghost" Reinholt - SecOps), Sarah (CPO)
- **Scope:** `modernman00/shared-lib`, `@modernman00/shared-js-lib`, and all 8 portfolio applications (`PartyPlatform`, `FamilyPlatform`, `ExecMind`, `BCP`, `LoanEasyFinance`, `iAccount`, `iDecide`, `TenantScore`)

---

## 1. Context & Problem Statement

Users across our ecosystem frequently require high-impact, studio-grade promotional posters and event flyers. Historically, users were forced to:
1. Pay £15–£45 on Canva, Etsy, or Fiverr for graphic design assets.
2. Manually copy-paste event dates, addresses, and RSVPs into external tools (often leading to third-party Canva watermarks as seen on sample flyers).
3. Post plain text messages on social media, resulting in poor viral distribution ($K$-factor $< 0.6$).

Under the **Advanced Technology Group (ATG Pod 3 & Pod 4)** charter, we have engineered an in-house, zero-dependency, sovereign visual synthesis engine that renders 4K-ready, bespoke flyers natively within the browser and server-side link previews.

---

## 2. Mandatory 4-Quadrant Architecture & Living Playbook

### Quadrant 1: Operational Overview & Core Architecture

The engine is decoupled into two interoperable layers:
1. **Client-Side High-Velocity Engine (`AtgUniversalPosterEngine.js` in `@modernman00/shared-js-lib`):**
   - Renders 100% client-side onto HTML5 `<canvas>` and `OffscreenCanvas`.
   - Multi-layer composition pipeline:
     - **Layer 1:** Ambient procedural gradient & texture backdrop.
     - **Layer 2:** Atmospheric lighting FX (deterministic golden bokeh, radial pyro sunburst rays, neon streaks).
     - **Layer 3:** Kinetic watermark typography sitting behind the subject.
     - **Layer 4:** Subject photo staging with ghost echo silhouette and rim-light glow.
     - **Layer 5:** Foreground framing (botanical peony blooms, tropical palm fronds, or geometric border).
     - **Layer 6:** Multi-pass headline shaders (Metallic Gold Foil, 3D Chrome Bevel, Neon Tube Glow, Bold Extrusion).
     - **Layer 7:** Verified badges, metric pills, and native offline QR code synthesis.
     - **Layer 8:** Hardcoded statutory regulatory footers (FCA/CQC/Chatham House).
2. **Server-Side Companion Service (`UniversalPosterSynthesisService.php` in `modernman00/shared-lib`):**
   - High-performance PHP 8.2+ GD renderer for dynamic Open Graph (OG) cards, WhatsApp link cards, and print rasterisation.
   - Enforces British English invariants and statutory regulatory disclosures.

---

### Quadrant 2: Sector Presets & Dynamic Aspect Ratios

The engine supports multi-aspect ratio reflow in 1 click:
- **Story (9:16 - 1080x1920):** Tailored for Instagram Stories, TikTok, and WhatsApp Status.
- **Feed (1:1 - 1080x1080):** Tailored for Instagram Feed, LinkedIn, and WhatsApp Chat cards.
- **Card (1200x630):** High-resolution Open Graph link previews.
- **Print Poster (A4 - 1240x1754):** High-density physical print format.

#### Supported Sector Presets Across All 8 Applications:
1. **`party_black_gold` (`PartyPlatform`):** Luxury matte obsidian, warm radial gold bokeh, Didot serif metallic gold foil shader, gold circular seal.
2. **`party_neon` (`PartyPlatform`):** Cyber-tropical purple/indigo backdrop, 3-pass neon glow tube stroke, cursive script kicker overlay.
3. **`party_pool_tropical` (`PartyPlatform`):** Fresh high-key day-club styling, 3D extruded lime/forest green block lettering, palm frond framing.
4. **`party_pyro_club` (`PartyPlatform`):** Radial amber pyro rays, sparks, kinetic background watermark ("FRIDAY FRIDAY"), 3D chrome bevel headline.
5. **`family_heirloom_gold` (`FamilyPlatform`):** Royal navy and gold crest, elegant serif typography, family reunion/anniversary dates.
6. **`family_botanical` (`FamilyPlatform`):** Soft sage and blush pink, peony floral clusters, warm milestone anniversary pill.
7. **`exec_sapphire_summit` (`ExecMind`):** Deep sapphire navy, brushed platinum and gold typography, Chatham House Rule seal.
8. **`bcp_urgent_shift` (`BCP`):** Clinical navy with urgent amber/red alert pill, prominent hourly pay rate pill (`£28.50 - £34.00 / HR`), CQC Reg 18/19 badge.
9. **`bcp_clinical_excellence` (`BCP`):** Clean trust cyan and white, Care Home Open Day layout, NHS & CQC Single Assessment certification mark.
10. **`fintech_sme_capital` (`LoanEasyFinance`):** Emerald noir, gold geometric borders, APR representative badge, mandatory FCA statutory risk warning.
11. **`iaccount_tax_readiness` (`iAccount`):** Slate Swiss grid, statutory HMRC deadline pill, CPD Accredited points badge (`2 CPD Hours`).
12. **`idecide_civic_debate` (`iDecide`):** Democratic cobalt and crimson debate surface, bold referendum motion headline, ballot casting QR.
13. **`tenant_property_showcase` (`TenantScore`):** Architectural charcoal, spec pills (`4 Beds • 3 Baths • EPC A`), landlord briefing banner.

---

### Quadrant 3: Adversarial Red-Team & Structural Safety Gates

1. **Gate 1: Defensive Typing (David's Clearance):**
   - TypeScript/JSDoc strict parameter validation.
   - PHP 8.2 strict types (`declare(strict_types=1);`), PHPStan Level 8 clean with 0 errors.
2. **Gate 2: Fallback Handling & Graceful Degradation:**
   - If user photo upload fails or has no transparent background, falls back to an elegant vignette scrim.
   - If GD extension is missing on server, throws an explicit `RuntimeException` with clear system guidance.
3. **Gate 3: Watchdogs & Memory Protection:**
   - 1200ms watchdog timer on remote image loading in `_loadImage()` prevents UI freezes.
   - Canvas resolution capped at safe mobile limits (max 1080x1920 on client).
4. **Gate 4: Injection Neutralisation (Marcus & Ghost PoC Clearance):**
   - Text fields are sanitized against script tags and XSS before Canvas text drawing.
   - Zero raw SVG parsing; QR codes are synthesized via native bit-matrices rather than external APIs.
   - British English Invariant statically enforced (`optimise`, `customise`, `licence`, `colour`, `behaviour`).

---

### Quadrant 4: Step-by-Step Developer Integration Guide

#### A. Client-Side Browser Usage (`@modernman00/shared-js-lib`)
```javascript
import { AtgUniversalPosterEngine } from '@modernman00/shared-js-lib';

const canvas = document.getElementById('poster-canvas');
const engine = new AtgUniversalPosterEngine({
  canvas,
  aspectRatio: 'story', // 'story' | 'feed' | 'poster_a4'
  theme: AtgUniversalPosterEngine.THEMES.PARTY_BLACK_GOLD,
  data: {
    title: "AARON'S BIRTHDAY",
    subtitle: 'All Black Luxury Affair',
    date: 'SAT MAY 07 2026',
    time: '9:00 PM',
    venue: 'Tape London',
    address: '17 Hanover Square, London W1S 1HU',
    accentMetric: 'VIP GUEST',
    perks: ['ALL BLACK DRESS CODE', 'VIP TABLE SERVICE', 'LIVE DJ'],
    qrUrl: 'https://partyplatform.com/events/aaron-30'
  }
});

// Staged photo cutout
await engine.setSubjectImage('/uploads/hosts/aaron-cutout.png');

// Render to canvas
engine.render();

// Export as PNG
const dataUrl = engine.toDataURL('image/png', 0.95);
engine.download('aarons-birthday-flyer.png');
```

#### B. Server-Side PHP Generation (`Src\Media\UniversalPosterSynthesisService`)
```php
use Src\Media\UniversalPosterSynthesisService;

$service = UniversalPosterSynthesisService::create([
    'aspect_ratio' => UniversalPosterSynthesisService::RATIO_STORY,
    'theme' => UniversalPosterSynthesisService::THEME_BCP_URGENT_SHIFT,
    'data' => [
        'title' => 'URGENT ITU NURSES NEEDED',
        'accent_metric' => '£38.50 / HR',
        'venue' => 'St Thomas Hospital, Westminster',
        'perks' => ['CQC REGISTERED', 'DAILY PAYROLL', 'MILEAGE PAID'],
        'qr_url' => 'https://bestcareprofessionals.org.uk/shifts/itu-urgent',
    ],
]);

// Save directly to disk for social media link preview
$service->save('/path/to/storage/flyer-og.png');

// Or return as Data URI
$dataUri = $service->toDataUri();
```

---

## 3. Verification & Compliance Sign-Off

- **PHPUnit Tests:** `tests/Unit/Media/UniversalPosterSynthesisServiceTest.php` passing (5/5 tests, 23 assertions).
- **Jest Unit Tests:** `shared-js-lib/__tests__/AtgUniversalPosterEngine.test.js` passing (9/9 tests).
- **PHPStan Static Analysis:** Level 8 certified (0 errors).
- **British English Verification:** 100% compliant with en-GB spelling invariants.
- **TAT 2.0 Sign-Off:** Olutobi (Deloitte Lead Tech Consultant) on behalf of Wally (CEO).
