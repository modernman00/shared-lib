# ⚡ Master Engineering, Architecture & Security Governance (v4.0 Lean)

> [!IMPORTANT]
> **BINDING OPERATING CHARTER ACROSS ALL 8 PORTFOLIO APPLICATIONS**
> (`PartyPlatform`, `FamilyPlatform`, `iDecide`, `iAccount`, `TenantScore`, `ExecMind`, `LoanEasyFinance`, `BCP`)
> This charter enforces high-velocity engineering, unyielding security, and empirical verification. Token efficiency is achieved by eliminating conversational filler and theatrical persona roleplay, while preserving 100% of technical rigor.

---

## 0. Prompt Specialist Protocol (Runs FIRST on every user message)

The user writes informally (often CAPS, fragments, multi-message). Before any work, the agent acts as the **Prompt Specialist**:

1. **Refine silently into a Refined Brief** using WHAT + WHERE + DONE WHEN, and show it as a 3–6 line block headed `📝 Refined Brief`:
   - **Goal** (the outcome, in plain words), **Where** (page/route/file; find it via search if unnamed), **Tier** (1/2/3, inferred), **Constraints** (privacy, UK English, relevant domain guide), **Done when** (default: `php -l` + `scripts/preflight.sh` Exit 0 + browser check of `$_ENV['APP_URL']`).
2. **Merge** all pending/follow-up messages into one brief; later messages amend earlier ones.
3. **Add the unstated but implied**: edge cases, persistence across reload, server-side enforcement for privacy, removal of dead DOM/JS, mobile check.
4. **Do not stop to ask for approval of the brief.** Proceed immediately. Ask the user only if a missing fact would cause irreversible or wrong-direction work (max 1–2 questions, multiple-choice).
5. **Route**: idea/risks → behave as `/grill-me` (no code); large build → plan first; hard bug/security/ATG → deep investigation; otherwise just execute.
6. **Never inflate**: a Tier 1 request gets a 3-line brief, not a ceremony.

Example — user types: *"WE DONT NEED MY STORY HERE. REMOVE IT. ALSO SHOW ONLINE/LAST SEEN"*
→ `📝 Refined Brief` — Goal: remove "My Story" (keep Reels), add Online/Last Seen presence. Where: `kin-whispers`. Tier 2. Constraints: privacy toggle enforced server-side, no phantom DOM. Done when: preflight Exit 0 + browser verified.

---

## 0.1 Auto-Triage (The User Does Not Need to Specify Anything)

Prompts follow the shape **WHAT + WHERE + DONE WHEN** (see `docs/governance/PROMPT_CHEATSHEET.md`). The agent MUST:
1. **Infer the tier itself** from Section 1; never ask the user to name it.
2. **Apply all invariants (Section 4) silently**: UK English, CSRF, spinners, 44px targets, parameterised SQL.
3. **Assume a default "Done when"** if none is given: `php -l` + `scripts/preflight.sh` Exit 0 + browser check of `$_ENV['APP_URL']`.
4. **Treat follow-up messages as amendments** to the same task and re-plan once, not restart.
5. **Load domain guides** (`docs/governance/*.md`) automatically when the task touches BCP, FinTech, games, or SaaS replacement/ML.
6. For live-only bugs, **investigate local-vs-live parity first** (env, CORS/CSP, schema via `SHOW COLUMNS`, autoload) before changing code.

---

## 1. The 3-Tier Delivery Framework

Every task is triaged into one of three risk tiers:

| Tier | Scope | Required Governance & Output | Verification Gate |
| :--- | :--- | :--- | :--- |
| **🟢 Tier 1: Routine Velocity** | UI styling, copy fixes, self-contained bug fixes | Diffs only + `php -l` + Mobile touch check (≥44px). Skips review matrices. | `php -l` + browser visual check |
| **🟡 Tier 2: Strategic Features** | New UI flows, components, APIs, notification triggers | Structured 4-Lens Review Matrix + In-flight automated attack neutralization. | `scripts/inflight.sh` + Pre-flight + Browser Screenshot |
| **🔴 Tier 3: Core Architecture** | Schema migrations (`ALTER`/`CREATE`), Auth, Payments, `shared-lib`, `sw.js` | 1-Page RFC + Full 4-Lens Gauntlet + Empirical Proof Capsule + Automated Rollback. | `bash scripts/preflight.sh` (Exit 0) + Executive Sign-Off |

---

## 2. The 4 Functional Review Lenses

For Tier 2 and Tier 3 deliverables, evaluate against the **4 Objective Lenses** (no conversational roleplay required):

```markdown
### 🛡️ 1. Security & Red-Team Lens
- **Threat Vector**: (e.g. IDOR, CSRF, parameter tampering, unauthenticated session state)
- **Neutralization Proof**: (Exact automated test or curl PoC showing 401/403/404)

### ⚡ 2. Systems & Architecture Lens
- **Efficiency & Blast Radius**: (Big-O analysis, query locks, zero-downtime DDL, strict typing)
- **Defensive Pattern Compliance**: (Verified against Section 4 gotchas)

### 🎨 3. Product & Usability Lens
- **Ergonomics & Invariants**: (≥44px touch targets, WCAG AA contrast, UK English, button spinners)
- **Visual Verification**: (4-State check: Happy Path, Extreme Lengths, Empty State, Submitting)

### ⚖️ 4. Commercial & Compliance Lens
- **Definition of Done (DoD)**: (Navigation discoverability, zero dead links, no phantom elements)
- **Living Guide Parity**: (`/playbook` or `/how-to-use` updated in lockstep)
```

---

## 3. Mandatory Empirical Proof Capsule

No deliverable may be marked complete without providing the **Empirical Proof Capsule**:
1. **Pre-Flight Terminal Output**: Verbatim output of `bash scripts/preflight.sh` showing Exit Code 0 (0 syntax errors, 0 PHPStan L8 errors, 100% passing tests, including `tests/verify_e2e_robust_suite.php`).
2. **Security Attack Neutralization**: Verbatim proof showing malicious payloads return `401 Unauthorized`, `403 Forbidden`, or `404 Not Found` (or fail safely).
3. **Browser Visual Evidence**: Live browser inspection (`$_ENV['APP_URL']`) confirming 0 console errors, mobile touch targets ≥44px, and correct layout rendering.
4. **Living Guide Update**: Confirmation that `/playbook` or `/how-to-use` reflects the changes.

---

## 4. Non-Negotiable Technical Invariants & Defensive Traps

### 🛡️ Core Security & Engineering Invariants
1. **Deterministic Pre-Flight Gate**: Run `php scripts/verify_engineering_must_haves.php` and `bash scripts/preflight.sh` to Exit Code 0 before completion.
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
