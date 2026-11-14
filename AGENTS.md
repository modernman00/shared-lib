# ⚡ Master Engineering, Architecture & Security Governance (v5.0 — 10-Stage Autonomous Engine)

> [!IMPORTANT]
> **BINDING OPERATING CHARTER ACROSS ALL 8 PORTFOLIO APPLICATIONS**
> (`PartyPlatform`, `FamilyPlatform`, `iDecide`, `iAccount`, `TenantScore`, `ExecMind`, `LoanEasyFinance`, `BCP`)
> This charter enforces zero-touch autonomous delivery, mandatory upfront research, unyielding security, and empirical self-healing verification so the CEO can drop a raw voice/text prompt and walk away until the task is 100% finished.

---

## 0. The 10-Stage Autonomous Software Delivery Engine (Zero-Stop Loop)

Inspired by the 7-component automated production pipeline—and hardened with 3 sovereign enterprise safety layers—every task runs through this **Zero-Stop Autonomous Pipeline** without waking the user until 100% of gates pass (or a genuine architectural ambiguity requires 1 multiple-choice question):

| Stage | Role Name | Video Analogy | Autonomous Responsibility |
| :--- | :--- | :--- | :--- |
| **1** | **Prompt Transcriber** | *Parakeet* | Converts raw, unedited voice dumps, ALL-CAPS notes, or multi-message fragments into a structured `📝 Refined Brief` (`WHAT + WHERE + DONE WHEN`) immediately on turn 1. Never stops to ask for brief approval. |
| **2** | **Deep Researcher** | *Chrome Researcher* | **Mandatory on Tier 2 & Tier 3.** Never build blind. Before writing code, searches 2–3 industry benchmarks (`search_web` / docs) + existing `shared-lib` / codebase precedents and outputs a `🔬 5-Bullet Research Brief` (Best Practice, Reusable Primitives, Edge Cases, Security Vectors, UK Copy). |
| **3** | **Snapshot & Parity Guard** | *[Sovereign Layer]* | Captures working tree state (`git status --porcelain`) and verifies DB schema parity (`SHOW COLUMNS FROM <table>`) so any regression can be rolled back cleanly. |
| **4** | **Lead Editor (Backend)** | *Claude Code* | Implements PHP 8.2+, parameterised PDO SQL, and `modernman00/shared-lib` logic using strict `string` IDs, zero SQL interpolation, and zero symlinks. |
| **5** | **UI Animator (Frontend)** | *Hyperframes* | Crafts components using `global_design_standards.md` v2.1 tokens, Alpine.js (`x-data`, `x-collapse`, `x-trap.noscroll`, `x-cloak`), and the **6-State Standard** (Happy Path, Extreme 3/80 chars, Empty State, Submitting Spinner, Error/Offline, Skeleton). |
| **6** | **Sensory & Brand Designer** | *Epidemic Sound* | Applies strict British English (`en-GB`), tactile press states (`active:scale-[0.98]`), `ScoreTenantSpinner` (`data-spin="true"`), `$toast()` alerts, and `$clipboard()` haptics. |
| **7** | **Adversarial & Static Gate** | *[Sovereign Layer]* | Runs `bash scripts/autonomous_gate.sh` (incremental `php -l`, targeted PHPStan L8, `verify_engineering_must_haves.php`, targeted PHPUnit tests) plus adversarial 401/403/404 attack neutralization. |
| **8** | **Screen Editor (Browser QA)** | *Tella MCP* | Opens `$_ENV['APP_URL']` on Desktop (`1440×900`) and Mobile (`390×844`), runs `scripts/dom_audit.js` (`window.runSovereignDomAudit()`) to verify ≥44px touch targets, 0 horizontal overflow, 0 black-on-red or ghost contrast flaws, and 0 console errors, and captures visual screenshots. |
| **9** | **Self-Healing Finisher** | *FFmpeg* | **Circuit-Breaker Loop (Max 5 Cycles):** If any check in Stages 7 or 8 fails, the agent **does not stop or ask the user**. It diagnoses the root cause, patches the code, and re-runs Stages 7–8 autonomously (up to 5 times) until Exit Code 0 is achieved. |
| **10**| **Playbook & Proof Capsule** | *[Sovereign Layer]* | Updates `/playbook` or `/how-to-use` in lockstep and outputs the final **Empirical Proof Capsule** so the user returns to a finished, verified product. |

---

## 0.1 Prompt Specialist & Auto-Triage Protocol (Stage 1 & 2)

1. **Show `📝 Refined Brief` (3–6 lines) on Turn 1**:
   - **Goal**, **Where** (route/file), **Tier** (1/2/3 inferred automatically), **Constraints** (UK English, privacy, domain guide), and **Done when**.
2. **Show `🔬 5-Bullet Research Brief` on all Tier 2 & Tier 3 Tasks**:
   - Before writing code on Tier 2/3, inspect codebase precedents (`shared-lib`, existing controllers/views) and external best practices (`search_web` when applicable), summarising: (1) UX/Industry Benchmark, (2) Existing Reusable Code, (3) Edge Cases & Failure Modes, (4) Security/IDOR/CSRF Vector, (5) British English Copy & Micro-interactions.
3. **Merge Follow-Ups**: Treat follow-up messages as amendments to the active brief; re-plan once and continue executing.
4. **Routing**:
   - Idea / workflow critique → `/grill-me` (interview 1 question at a time, no code until aligned).
   - Complex multi-module build → `/plan` or `/boost`.
   - Standard feature / bug → Execute Stages 1–10 autonomously to completion.

---

## 1. The 3-Tier Delivery Framework

| Tier | Scope | Required Autonomous Stages | Verification Gate |
| :--- | :--- | :--- | :--- |
| **🟢 Tier 1: Routine Velocity** | UI styling, copy fixes, self-contained bug fixes | Stages 1, 4–6, 7–9 (skips Research Brief & 4-Lens Matrix) | `bash scripts/autonomous_gate.sh` + Browser visual & `dom_audit.js` check |
| **🟡 Tier 2: Strategic Features** | New UI flows, components, APIs, notification triggers | All Stages 1–10 (`🔬 Research Brief` + 4-Lens Matrix + Adversarial PoC) | `bash scripts/autonomous_gate.sh` + Browser Desktop/Mobile `dom_audit.js` (`preflight.sh` at deploy) |
| **🔴 Tier 3: Core Architecture** | Schema migrations (`ALTER`/`CREATE`), Auth, Payments, `shared-lib`, `sw.js` | All Stages 1–10 + 1-Page RFC + Automated Rollback | Full `bash scripts/preflight.sh` (Exit 0) + TAT Executive Sign-Off |

---

## 2. The 4 Functional Review Lenses (Tier 2 & Tier 3)

```markdown
### 🛡️ 1. Security & Red-Team Lens
- **Threat Vector**: (e.g. IDOR, CSRF, parameter tampering, unauthenticated session state)
- **Neutralization Proof**: (Exact automated test or curl PoC showing 401/403/404)

### ⚡ 2. Systems & Architecture Lens
- **Efficiency & Blast Radius**: (Big-O analysis, query locks, zero-downtime DDL, strict typing)
- **Defensive Pattern Compliance**: (Verified against Section 4 gotchas)

### 🎨 3. Product & Usability Lens
- **Ergonomics & Invariants**: (≥44px touch targets, WCAG AA contrast, UK English, button spinners)
- **Visual Verification**: (6-State check + `dom_audit.js` Desktop & Mobile pass)

### ⚖️ 4. Commercial & Compliance Lens
- **Definition of Done (DoD)**: (Navigation discoverability, zero dead links, no phantom elements)
- **Living Guide Parity**: (`/playbook` or `/how-to-use` updated in lockstep)
```

---

## 3. Mandatory Empirical Proof Capsule (Stage 10 Output)

No deliverable may be marked complete without providing the **Empirical Proof Capsule**:
1. **Research & Precedent Summary** *(Tier 2/3)*: The `🔬 5-Bullet Research Brief` confirming the feature was engineered against proven patterns.
2. **Validation Terminal Output**: Verbatim output of `bash scripts/autonomous_gate.sh` (Exit Code 0) during iteration; full `bash scripts/preflight.sh` (Exit Code 0) run once at deployment.
3. **Security Attack Neutralization**: Verbatim proof showing malicious payloads return `401 Unauthorized`, `403 Forbidden`, or `404 Not Found`.
4. **Browser & DOM Audit Evidence**: Live browser inspection (`$_ENV['APP_URL']`) on Desktop and Mobile with `window.runSovereignDomAudit()` reporting `passed: true` (`0` violations) and `0` console errors.
5. **Living Guide Update**: Confirmation that `/playbook` or `/how-to-use` reflects the changes.

---

## 4. Non-Negotiable Technical Invariants & Defensive Traps

### 🛡️ Core Security & Engineering Invariants
1. **Fast Iterative Gate vs. Deploy Pre-Flight**: During autonomous self-healing loops (Stage 7 & 9), run `bash scripts/autonomous_gate.sh [test_path]`. Run the full 8-gate `bash scripts/preflight.sh` (Exit Code 0) once at deployment/release (or when explicitly requested).
2. **Universal CSRF**: Every `<form>` in Blade templates MUST contain `@include('csrf')` or `@csrf`.
3. **Zero SQL String Interpolation**: Raw string interpolation (`{$var}`, `$var`, or concatenation) inside SQL is **strictly forbidden across ALL PHP files (including test fixtures)**. Always use parameterized PDO statements (`prepare` & `execute`). Decouple dynamic `?,?,?` placeholders via `sprintf()`.
4. **Universal Button Spinners**: Every state-changing button MUST trigger a loading spinner (`ScoreTenantSpinner.start(btn)` / `data-spin="true"`) to prevent double submissions.
5. **Universal British English (UK)**: All user-facing copy, labels, placeholders, and tooltips must use British English (`-ise`, `-our`, `licence`, `centre`, `enquiry`, `mould`).
6. **Safe Touch Targets**: All interactive controls must have a minimum hit area of 44×44px (`min-h-[44px] min-w-[44px]`) and ≥8px separation between adjacent controls.
7. **Zero Mid-Word Truncation & Button Integrity**: Dynamic strings must use `line-clamp-2` or `text-overflow: ellipsis` with native tooltips. Buttons and pills must enforce `white-space: nowrap; flex-shrink: 0;`.
8. **Strict Colour & Contrast**: Minimum WCAG AA 4.5:1 contrast. Never place dark fonts on red backgrounds, and never place white/faint fonts on light backgrounds.
9. **Strict String IDs**: All user and entity IDs must be strictly typed as `string` in PHP models, controllers, and DTOs to prevent silent alphanumeric truncation to `0`.
10. **Shared-Lib Centralization**: Backend logic must leverage `modernman00/shared-lib` (`\Src\ApiResponse::json()`, `\Src\Limiter::limit()`, `\Src\AuditLogger::log()`). Frontend components must leverage `@modernman00/shared-js-lib`. Zero symlinks to `shared-lib` in production; install versioned packages via Composer and propagate updates across all 8 apps.
11. **Multi-Surface Definition of Done (Anti-Headless)**: Every user-facing feature must have (a) backend/schema tests, (b) clickable navigation entry point (no URL-only hidden pages), (c) zero phantom DOM elements, and (d) browser verification.
12. **Interactive Playbook Parity**: Every app's `/playbook` or `/how-to-use` must be updated in lockstep with new features, written in domain terms (no raw table/column names).

### 🚨 Critical Defensive Traps & Fatal Patterns
- **Trap A: PHP `readonly` Constructor Trap (PHP 8.1+)**:
  NEVER combine constructor-promoted `readonly` properties with body-level reassignment (`$this->prop = $prop ?? new ...`). It causes a fatal runtime error. Either drop `readonly` or provide a static factory constructor `create()`.
- **Trap B: Router Whitelist & Session Dependency (`RouteDispatch.php`)**:
  Any controller calling `$this->requireManager()` or reading `$_SESSION['manager_id']` MUST NOT be in `$publicControllers`. Whitelisted routes bypass JWT session decoding.
- **Trap C: MySQL `ONLY_FULL_GROUP_BY`**:
  Never use `GROUP BY` on queries with `SELECT *` or non-aggregated columns. Deduplicate in the PHP application array layer instead.
- **Trap D: Pre-Flight Schema Parity (`SHOW COLUMNS`)**:
  When debugging "works locally, fails in production" issues, run `SHOW COLUMNS FROM <table>` on both environments to verify `DEFAULT` constraints and types before editing PHP.
- **Trap E: Controlled Deletion (`rsync --delete-excluded`)**:
  All deployment syncs must use `.rsync-filter` protecting persistent directories (`/public/uploads/***`, `/storage/***`, `.env`) and perform a dry-run assertion first.
- **Trap F: Alpine.js Enterprise Invariant**:
  Ban brittle DOM queries (`document.getElementById`). Use declarative Alpine directives (`x-data`, `x-show`, `x-collapse`, `x-trap.noscroll`, `Alpine.$persist()`). Any element using dynamic bindings on initial load must declare `x-cloak`.

---

## 5. Domain-Specific Governance References (Loaded On-Demand)

To prevent prompt bloat, specialized domain regulations live in dedicated reference guides and should be read **only when working on those domains**:
- **Advanced Technology Group (ATG — Sovereign R&D & SaaS Replacement)**: [`docs/governance/atg_deep_tech.md`](./docs/governance/atg_deep_tech.md) (proprietary micro-libraries, ML/AI with deterministic fallbacks, zero-SaaS builds, $\le O(n \log n)$ benchmarking).
- **Healthcare & CQC Compliance (BCP)**: [`docs/governance/bcp_healthcare.md`](./docs/governance/bcp_healthcare.md) (CQC Reg 5 FPPT, workforce bifurcation).
- **FinTech & Credit Underwriting**: [`docs/governance/fintech_standards.md`](./docs/governance/fintech_standards.md) (FCA risk tiers, double-entry ledger math, LTV:CAC).
- **Interactive Gaming & Canvas**: [`docs/governance/canvas_games.md`](./docs/governance/canvas_games.md) (60fps HTML5 Canvas/WebGL loops, physics, audio feedback).
