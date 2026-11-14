# 🏢 Claude Code & Agent Developer Operations Guide (`CLAUDE.md` — v5.0)

> **Source of Truth:** For full architectural standards, the 10-Stage Autonomous Software Delivery Engine, the 4-Lens Review Matrix, and defensive patterns, see **[`AGENTS.md`](./AGENTS.md)**.

---

## 1. The 10-Stage Zero-Stop Autonomous Loop (Mandatory on Every Task)

When the user drops a raw voice transcript, ALL-CAPS brain dump, or one-liner, **do not ask for permission to start**. Execute the 10-Stage Pipeline autonomously to completion:

1. **Stage 1 — Prompt Transcriber (*Parakeet*)**: Output a 3–6 line `📝 Refined Brief` (**Goal + Where + Tier + Constraints + Done When**) at the top of Turn 1.
2. **Stage 2 — Deep Researcher (*Chrome Researcher*)**: **Mandatory on Tier 2 & Tier 3.** Never build blind. Search 2–3 industry benchmarks (`search_web` / docs) + existing `shared-lib` / codebase patterns and output a `🔬 5-Bullet Research Brief` (UX Benchmark, Reusable Code, Edge Cases, Security Vectors, UK Copy) before writing code.
3. **Stage 3 — Snapshot & Parity Guard**: Check `git status --porcelain` and DB column parity (`SHOW COLUMNS FROM <table>`).
4. **Stage 4 — Lead Editor (*Claude Code*)**: Implement PHP 8.2+, parameterised PDO SQL, strict `string` IDs, and `modernman00/shared-lib` primitives (zero symlinks).
5. **Stage 5 — UI Animator (*Hyperframes*)**: Apply `global_design_standards.md` v2.1 tokens, Alpine.js (`x-data`, `x-collapse`, `x-trap.noscroll`, `x-cloak`), and the **6-State Standard** (Happy Path, Extreme 3/80 chars, Empty State, Submitting Spinner, Error/Offline, Skeleton).
6. **Stage 6 — Sensory & Brand Designer (*Epidemic*)**: Enforce British English (`en-GB`), tactile press states (`active:scale-[0.98]`), `ScoreTenantSpinner` (`data-spin="true"`), `$toast()`, and `$clipboard()`.
7. **Stage 7 — Adversarial & Static Gate**: Run `bash scripts/autonomous_gate.sh [test_path]` + adversarial 401/403/404 PoC checks.
8. **Stage 8 — Screen Editor (*Tella / Browser QA*)**: Open `$_ENV['APP_URL']` on Desktop (`1440×900`) and Mobile (`390×844`), run `scripts/dom_audit.js` (`window.runSovereignDomAudit()`) to verify ≥44px touch targets, 0 horizontal overflow, 0 black-on-red or ghost text contrast violations, and 0 console errors.
9. **Stage 9 — Self-Healing Finisher (*FFmpeg*)**: **5-Cycle Circuit Breaker:** If any check in Stage 7 or Stage 8 fails, diagnose the root cause, patch the code, and re-run Stages 7–8 autonomously up to **5 times** until Exit Code `0` is achieved without waking the user.
10. **Stage 10 — Playbook & Proof Capsule**: Update `/playbook` or `/how-to-use` in lockstep and output the final **Empirical Proof Capsule**.

---

## 2. 3-Tier Triage & Essential Validation Commands

- **🟢 Tier 1 (Routine UI/copy/bug)**: Stages 1, 4–6, 7–9 (skips Research Brief & 4-Lens Matrix).
- **🟡 Tier 2 (Strategic Feature/API)**: All Stages 1–10 (`🔬 Research Brief` + 4-Lens Matrix + `autonomous_gate.sh` + Browser `dom_audit.js`).
- **🔴 Tier 3 (Schema/Auth/Payments/`shared-lib`)**: All Stages 1–10 + 1-Page RFC + full `bash scripts/preflight.sh` (Exit Code 0).

```bash
# Fast Iterative Finisher Gate (Run during Stage 7 & Stage 9 self-healing loops)
bash scripts/autonomous_gate.sh [optional_phpunit_test_path]

# Automated Engineering Invariants Check (CSRF, SQL interpolation, UK English, BladeOne syntax)
php scripts/verify_engineering_must_haves.php

# Full 8-Gate Deployment Pre-Flight (Run once at deployment / release, or on Tier 3)
bash scripts/preflight.sh
```

---

## 3. Golden Engineering Invariants (Summary)
1. **Never overwrite existing functions**: Use surgical diffs; preserve existing comments and docstrings.
2. **Universal CSRF**: All `<form>` tags must include `@include('csrf')` or `@csrf`.
3. **Zero SQL String Interpolation**: Parameterised PDO only (`prepare` + `execute`). No exceptions for test files.
4. **Universal Button Spinners**: Every state-changing button must show a loading spinner (`ScoreTenantSpinner`).
5. **British English (UK)**: Mandatory UK spellings (`-ise`, `-our`, `licence`, `centre`, `enquiry`).
6. **Mobile Touch Targets & Contrast**: Minimum 44×44px hit areas with ≥8px separation. Zero black-on-red and zero white-on-white ghost text.
7. **PHP `readonly` Trap**: Never reassign a promoted `readonly` property in the constructor body.
8. **Strict String IDs**: All user and entity IDs must be typed as `string`.
9. **Alpine `@error` Blade Collision Guard**: In `.blade.php` templates, always write `x-on:error="..."` instead of `@error="..."` on HTML elements so BladeOne does not misparse it as a validation directive.
10. **Self-Correction & Hygiene**: Loop and self-correct up to **5 times autonomously** on gate/browser failures. Offload massive logs to `scratch/` rather than dumping in chat.
