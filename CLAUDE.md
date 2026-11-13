# 🏢 Claude Code Developer Operations Guide (`CLAUDE.md`)

> **Source of Truth:** For full architectural standards, the 4-Lens Review Matrix, and defensive patterns, see **[`AGENTS.md`](./AGENTS.md)**.

---

## 1. Quick Workflow Reference
- **Strict Velocity by Default**: Deliver high-density surgical diffs and empirical test proof. Avoid conversational preambles or simulated persona roleplay.
- **3-Tier Triage**:
  - **Tier 1 (Routine UI/bug)**: Targeted diffs + `php -l` + visual check.
  - **Tier 2 (Feature/API)**: 4-Lens review summary + automated attack test + pre-flight.
  - **Tier 3 (Schema/Auth/Money)**: 1-Page RFC + Full Proof Capsule + automated pre-flight gate.

---

## 2. Essential Validation Commands
```bash
# 1. PHP Syntax Check (Mandatory before every commit)
find src helpers app tests -name "*.php" -exec php -l {} \;

# 2. PHPStan Static Analysis (Level 8 Strict Typing)
vendor/bin/phpstan analyse --level=8

# 3. PHPUnit Test Suite
vendor/bin/phpunit

# 4. Automated Engineering Invariants Check (CSRF, SQL interpolation, UK English)
php scripts/verify_engineering_must_haves.php

# 5. Deterministic Pre-Flight Gate (Run prior to sign-off)
bash scripts/preflight.sh
```

---

## 3. Golden Engineering Invariants (Summary)
1. **Never overwrite existing functions**: Use surgical diffs; preserve existing comments and docstrings.
2. **Universal CSRF**: All `<form>` tags must include `@include('csrf')` or `@csrf`.
3. **Zero SQL String Interpolation**: Parameterized PDO only (`prepare` + `execute`). No exceptions for test files.
4. **Universal Button Spinners**: Every state-changing button must show a loading spinner (`ScoreTenantSpinner`).
5. **British English (UK)**: Mandatory UK spellings (`-ise`, `-our`, `licence`, `centre`).
6. **Mobile Touch Targets**: Minimum 44×44px hit areas with ≥8px separation.
7. **PHP `readonly` Trap**: Never reassign a promoted `readonly` property in the constructor body.
8. **Strict String IDs**: All user and entity IDs must be typed as `string`.
9. **Interactive Playbook**: New features must update `/playbook` or `/how-to-use` as a DoD prerequisite.
10. **Self-Correction & Hygiene**: Loop and self-correct up to 3 times autonomously on test failures. Offload massive logs to `scratch/` rather than dumping in chat.
