# Executive Engineering & Architecture Report: Universal Poster & Creative Synthesis Engine

**Document Reference:** `ATG-REP-2026-11-06-EXP-BOOST`  
**Governing Body:** Technical Approval Team (TAT) & Advanced Technology Group (ATG)  
**Lead Authors:** Victor (CTO & Head of BRATS), Callum Wright (Lead Staff Full-Stack Engineer), Maya Lindqvist (Lead Interactive Frontend Architect)  
**Status:** Completed & Empirically Verified (Exit Code 0)  
**Date:** 6 November 2026

---

## 1. Executive Summary & Background

Following executive feedback on creative quality and multi-tenant boundary compliance, the Advanced Technology Group (ATG) undertook a comprehensive architectural and visual overhaul of the **Universal Poster & Flyer Studio** across the entire application ecosystem (`PartyPlatform`, `FamilyPlatform`, `LoanEasyFinance`, `TenantScore`, `iAccountApp`, `ExecMindApp`, `iDecideApp`, and `BCP`).

The executive directives addressed were:
1. **Total Single-App Tenant Isolation:** Completely eliminate cross-app brand exposure (e.g., preventing event hosts on `PartyPlatform` from seeing links or controls for other portfolio apps like `LoanEasyFinance` or `iAccount`).
2. **Proper Navigation Placement:** 
   - Position the creative studio prominently as **Family Flyer Studio** in `FamilyPlatform`'s user navigation.
   - Restrict access in the remaining enterprise, fintech, and civic apps to authorized administrator routes (`/admin/poster-studio` or `/poster-studio` behind admin role guards).
3. **Retail Consumer Lending Transformation (`LoanEasyFinance`):** Redesign the corporate SME lending interface into a consumer-focused retail mortgage and personal finance suite inspired by tier-1 UK retail lenders (Virgin Money Fee-Saver and HSBC Home Loans).
4. **Gamified Experian-Style Visual Archetypes (`TenantScore` & `iAccountApp`):** Architect creative, consumer-grade visual archetypes inspired by Experian credit score dashboards, featuring:
   - 5-segment radial arc score gauges (Orange $\to$ Yellow $\to$ Light Green $\to$ Green $\to$ Dark Green).
   - Smartphone device frame mockups with top speaker notches and organic curved wave backgrounds.
   - 3D celebratory rocket boost launches with multi-coloured confetti ribbons and sparkles.
   - Interactive glassmorphic metric impact chips and floating elevated referencing/audit shield cards with embedded QR verification.

---

## 2. Architectural Implementations

### 2.1 Single-Tenant Isolation & Admin Route Placement
- **Excised Portfolio Switcher Strip:** The shared template view rendered a top navigation bar (`#appSwitcherStrip` / `.app-pill-strip`) allowing arbitrary cross-app navigation. This was excised; the top bar now displays only the active application's branding, subtitle, and an accessible link to the 4-Quadrant How-to-Use Guide.
- **Dynamic Context Normalisation:** `UniversalPosterStudioController::normaliseAppKey()` inspects the current host environment (`HTTP_HOST`), query parameters, or framework configuration to lock the studio strictly to the host application's domain.
- **Family Flyer Studio Integration:** In `FamilyPlatform`, the tool was branded as **Family Flyer Studio** and integrated directly into the primary navigation bar, user dropdown menu, and mobile drawer.
- **Admin Navigation Guards:** In `bcp`, `loaneasyfinance`, `iaccountapp`, `execmindapp`, `idecideapp`, and `scoretenant`, navigation links were integrated into admin sub-navigation menus.

### 2.2 Consumer Retail Lending Engine (`LoanEasyFinance`)
Transformed the templates from dry corporate facility term sheets into high-converting consumer retail lending flyers:
- **Virgin Money Style (`retail_banking_features`):** Crisp white container card over a warm family homeowner backdrop, featuring bold rate typography (`From 4.40%`), product pill tags (`NEW Exclusive Products`, `Fee-Saver`), loan-to-value badges (`90% & 95% LTV Available`), deposit callouts (`5% Deposit • No Arrangement Fee`), and FCA APR warnings.
- **HSBC Style (`consumer_loan_campaign`):** Dynamic chevron visual layout with human joy moving-day photography, bold emotional headline (`Finding the perfect home is hard. Owning it isn't.`), highlighted rate (`Interest rate starting at just 8.55%*`), and fast-decision callouts.

### 2.3 Experian-Style Gamified Score & Health Archetypes

#### A. TenantScore: `tenant_credit_boost_mockup`
* **Default Preset:** `tenant_credit_boost_experian` (`Experian Style — Tenant Rent Boost & Score Card (1250/1250)`)
* **Vector 3D Rocket:** Procedural vector rocket tilted at $-40^\circ$ blasting off over the top-left shoulder of the phone frame with flame plumes, exhaust smoke, and floating pastel confetti ribbons, rectangles, and stars.
* **5-Segment Radial Arc Credit Gauge:** A $270^\circ$ radial arc ($135^\circ$ to $405^\circ$) divided into 5 rounded segments separated by angular gaps:
  1. `#f97316` (Warm Orange)
  2. `#facc15` (Sunny Yellow)
  3. `#84cc16` (Lime Light Green)
  4. `#22c55e` (Vibrant Emerald Green)
  5. `#15803d` (Deep Forest Green)
  * Central score display: bold `1250` over `out of 1250`, accompanied by a 4-tile brand emblem cluster.
* **Curved Navy Wave Surface:** Realistic smartphone frame holding a white upper greeting card and an organic dark navy wave lower section (`#0f172a` to `#1e3a8a`).
* **Metric Impact Chips:** Glassmorphic status pills: `↗ Score up by 45 pts`, `🔄 36 mos on-time rent`, and `🔒 Passport verified`.
* **Elevated White Card:** Floating referencing card detailing pre-approved tenancy status, zero deposit qualification, a CTA link (`View Verified Passport ›`), and an embedded scannable verification QR badge.

#### B. iAccount: `tax_health_score_app`
* **Default Preset:** `iaccount_tax_health_experian` (`Experian Style — Tax Health & Audit Shield Score (980/1000)`)
* **Corporate Sapphire & Emerald Lighting:** Ambient glow backdrop tailored for corporate treasury and accounting professionals.
* **Tax Health Segmented Gauge:** Displays a calibrated **980 out of 1000** Tax Health Score with low audit risk certification.
* **Statutory Impact Chips:** Glassmorphic status pills: `↗ Tax saved +18%`, `🔄 100% MTD compliant`, and `🔒 £0 Penalty Shield`.
* **Floating Tax Optimisation Card:** Displays **£14,850** in identified tax savings, FRS 102 Corporation Tax & VAT audit readiness, CTA link (`Download HMRC Defense Pack ›`), and an embedded scannable ledger audit QR badge.

---

## 3. Files Modified & Added

| File Path | Description of Changes |
| :--- | :--- |
| `src/Media/UniversalPosterSynthesisService.php` | Registered `ARCHETYPE_TENANT_CREDIT_BOOST`, `ARCHETYPE_TAX_HEALTH_SCORE`, `THEME_TENANT_CREDIT_BOOST`, and `THEME_IACCOUNT_TAX_HEALTH` with inference logic and schema defaults. |
| `src/Media/UniversalPosterStudioController.php` | Configured default presets, tailored form field labels for TenantScore and iAccount, and updated `renderArchetypeCards()` filter logic. |
| `helpers/universal-poster-engine.js` | Implemented procedural vector rendering methods (`_renderArchetypeTenantCreditBoost`, `_renderArchetypeTaxHealthScore`, `_renderSegmentedCreditGauge`, `_renderPhoneDeviceFrame`, `_renderConfettiParticles`, `_renderRocketIllustration`, and `_wrapText`). |
| `shared-js-lib/AtgUniversalPosterEngine.js` | Synchronised the engine build to `@modernman00/shared-js-lib`. |
| `resources/poster-assets/` | Stored reference assets (`asset_score_gauge_sample.png`, `asset_app_mockup_sample.png`, `asset_boost_rocket_sample.png`, `bg_consumer_family_home.jpg`, and `bg_consumer_moving_joy.jpg`). |
| `tests/browser_verify_experian_boost.mjs` | Automated Playwright browser verification suite for TenantScore and iAccount Experian templates. |
| `tests/browser_verify_all_studios.mjs` | Automated 8-app regression verification suite checking HTTP status, canvas rendering, single-tenant isolation, and console errors. |

---

## 4. Empirical Proof Capsule

### 4.1 PHP Static Syntax Verification (`php -l`)
Both core PHP files passed linting with 0 syntax errors:
```bash
php -l src/Media/UniversalPosterStudioController.php
# Output: No syntax errors detected in src/Media/UniversalPosterStudioController.php

php -l src/Media/UniversalPosterSynthesisService.php
# Output: No syntax errors detected in src/Media/UniversalPosterSynthesisService.php
```

### 4.2 PHPUnit Automated Test Suite
```bash
./vendor/bin/phpunit tests/Unit/Media/UniversalPosterSynthesisServiceTest.php
# Output: OK (7 tests, 61 assertions, 100% pass)
```

### 4.3 JavaScript Core Library Suite (`@modernman00/shared-js-lib`)
```bash
npm --prefix /Users/waleolaogun/Sites/shared-js-lib test
# Output:
# Test Suites: 30 passed, 30 total
# Tests:       338 passed, 338 total
# Snapshots:   0 total
# Time:        4.641 s
```

### 4.4 Live Browser Automated Verification & Visual Captures
Executed headless Chromium tests across all applications with zero console errors:

1. **TenantScore (`https://scoretenant.test/poster-studio`):**
   - HTTP Status: `200 OK`
   - Active Preset: `Experian Style — Tenant Rent Boost & Score Card (1250/1250)`
   - Canvas Render Size: `1080x1920` (Data URL size: 1,070,954 bytes)
   - Console Errors: `0`
   - Visual Captures:
     - Studio UI: `studio_scoretenant_credit_boost_rendered.png`
     - Canvas Output: `poster_scoretenant_credit_boost_canvas.png`

2. **iAccount (`https://iaccountapp.test/poster-studio`):**
   - HTTP Status: `200 OK`
   - Active Preset: `Experian Style — Tax Health & Audit Shield Score (980/1000)`
   - Canvas Render Size: `1080x1920` (Data URL size: 1,310,654 bytes)
   - Console Errors: `0`
   - Visual Captures:
     - Studio UI: `studio_iaccount_tax_health_rendered.png`
     - Canvas Output: `poster_iaccount_tax_health_canvas.png`

3. **8-App Regression Suite (`tests/browser_verify_all_studios.mjs`):**
   - Verified `bcp`, `loaneasyfinance`, `iaccountapp`, `execmindapp`, `idecideapp`, `scoretenant`, `partyplatform`, and `familyplatform`.
   - Result: **All 8 sector studios verified in browser with 0 console errors and 100% tenant isolation.**

---

## 5. Artifact Directory References
The high-resolution rendered output assets and full studio screenshots are available in the conversation artifact directory:
- [`studio_scoretenant_credit_boost_rendered.png`](file:///Users/waleolaogun/.gemini/antigravity/brain/636adfe9-8e08-4458-ae18-e506eab051ff/studio_scoretenant_credit_boost_rendered.png)
- [`poster_scoretenant_credit_boost_canvas.png`](file:///Users/waleolaogun/.gemini/antigravity/brain/636adfe9-8e08-4458-ae18-e506eab051ff/poster_scoretenant_credit_boost_canvas.png)
- [`studio_iaccount_tax_health_rendered.png`](file:///Users/waleolaogun/.gemini/antigravity/brain/636adfe9-8e08-4458-ae18-e506eab051ff/studio_iaccount_tax_health_rendered.png)
- [`poster_iaccount_tax_health_canvas.png`](file:///Users/waleolaogun/.gemini/antigravity/brain/636adfe9-8e08-4458-ae18-e506eab051ff/poster_iaccount_tax_health_canvas.png)
- [`studio_loaneasyfinance_rendered.png`](file:///Users/waleolaogun/.gemini/antigravity/brain/636adfe9-8e08-4458-ae18-e506eab051ff/studio_loaneasyfinance_rendered.png)
- [`poster_loaneasy_virgin_money.png`](file:///Users/waleolaogun/.gemini/antigravity/brain/636adfe9-8e08-4458-ae18-e506eab051ff/poster_loaneasy_virgin_money.png)
- [`poster_loaneasy_hsbc.png`](file:///Users/waleolaogun/.gemini/antigravity/brain/636adfe9-8e08-4458-ae18-e506eab051ff/poster_loaneasy_hsbc.png)
