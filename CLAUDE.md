# 🏢 Claude Code & Engineering Operations Guide (`CLAUDE.md`)

> [!IMPORTANT]
> **SUPREME GOVERNANCE & ARCHITECTURE CHARTER:**
> For full multi-agent personas, the 4 Sovereign Sector Boards, Definition of Done, the Custodianship Allocation Roster, and the Token-Optimized Battle Matrix, refer to **[`AGENTS.md`](./AGENTS.md)**. 
> `AGENTS.md` is the canonical, binding source of truth across all 8 portfolio applications (`PartyPlatform`, `FamilyPlatform`, `iDecide`, `iAccount`, `TenantScore`, `ExecMind`, `LoanEasyFinance`, and `BCP`).

---

## 1. Executive Front-Door & Fast-Path Triage (Jumoke's Mandate) ⚡

- **Automatic Intake:** Jumoke Olaogun (Special Assistant to the CEO & Chief of Staff) automatically ingests Wally's requests without needing her name mentioned.
- **Strict Velocity Mode by Default:** Avoid unsolicited conversational waffle or simulated monologues. Deliver immediate, high-density diffs and empirical proof.
- **The 3-Tier Delivery Framework:**

| Risk Tier | Scope | Required Governance & Output | Target Tokens |
| :--- | :--- | :--- | :--- |
| **🟢 Tier 1: Routine Delivery** | Local bug fixes, copy, CSS adjustments | Diffs only + `php -l` + **Dual-Lens Delivery Report** (Marcus Security Check + Segun Mobile Touch Check). Skips board debates. | 1,500 – 2,500 |
| **🟡 Tier 2: Strategic Features** | New UI components, onboarding flows, APIs | **Token-Optimized Battle Matrix** (Chamber 1 debate, TFT Strike Pod, Dr. Viktor Kovács & Marcus PoC attack, TAT 2.0 consensus). | 4,000 – 6,500 |
| **🔴 Tier 3: Core Architecture** | Schema migrations, Auth, Payments, `shared-lib` | 1-Page RFC, Victor 6-Pillar CTO Pre-Approval, Full Adversarial Gauntlet, David's 4 Structural Gates, and Olutobi Final Executive Sign-Off. | 8,000 – 12,000 |

---

## 2. Non-Negotiable Agent Operational Rules

1. **NEVER overwrite existing functions unless explicitly told.** Use targeted, surgical diffs. Preserve all comments and docstrings.
2. **Always run `php -l` on modified PHP files** to check for syntax errors before presenting changes.
3. **Do not use outdated npm or composer packages.** Stick to native modern features (PHP 8.2+, ES6+ modules).
4. **If a UI element changes, verify via the browser** using `$_ENV['APP_URL']` (or `https://partyplatform.test`) and confirm zero console errors and $\ge 44\text{px}$ touch targets.
5. **Loop and self-correct up to 3 times autonomously** if a local test or lint fails before asking for human intervention.
6. **Maintain Context Cleanliness (The Amnesia Defense):** Never dump massive terminal outputs or logs directly into chat. Offload diagnostic logs to scratch files (`scratch/`).

---

## 3. Essential CLI & Validation Commands

All commands run from the root of `shared-lib` (or the corresponding application repository):

```bash
# 1. PHP Syntax Linter (Mandatory before every commit)
find src helpers tests -name "*.php" -exec php -l {} \;

# 2. PHPStan Static Analysis (Level 8 Strict Typing)
vendor/bin/phpstan analyse src helpers --level=8

# 3. PHPUnit Test Suite
vendor/bin/phpunit

# 4. Enforce 10 Golden Engineering Invariants
php scripts/verify_engineering_must_haves.php

# 5. Deterministic Pre-Flight Gate (when available in app)
bash scripts/preflight.sh
```

---

## 4. Key Architectural & Runtime Shields (Quick Reference)

For full details, see **[`AGENTS.md` Section 4](./AGENTS.md#4-non-negotiable-engineering--code-standards)**.

- **Standard 18 (Zero-Symlinks):** Symlinks to `vendor/modernman00/shared-lib` are **STRICTLY PROHIBITED** in production or committed workspaces. Must install physical, versioned packages via Composer (`composer update modernman00/shared-lib --prefer-dist`).
- **Standard 19 (Interactive Playbook & Living Guide):** Every app must maintain an interactive guide (`/playbook` or `/how-to-use`) updated in lockstep with new features as a DoD requirement.
- **Standard 20 (PHP `readonly` Fatal Pattern):** NEVER combine constructor-promoted `readonly` properties with body-level reassignment (`$this->prop = $prop ?? new ...`). Either drop `readonly` or provide a static factory `create()`.
- **Standard 21 (Strict String IDs):** All user/entity IDs must be strictly typed as `string` to prevent alphanumeric ID truncation to `0`.
- **Standard 22 (Router Whitelist & Session Dependency):** Any controller calling `$this->requireManager()` or reading `$_SESSION['manager_id']` MUST NOT be in `$publicControllers` in `RouteDispatch.php`. Whitelisting skips JWT session population.
- **Standard 23 (MySQL `ONLY_FULL_GROUP_BY`):** Ban `GROUP BY` on `SELECT *` queries. Perform multi-column record deduplication in the PHP array layer.
- **Standard 24 (Schema Parity First):** Always run `SHOW COLUMNS FROM <table>` on local vs production DBs before debugging complex PHP code.
- **Standard 25 (Controlled Deletion):** Deployment `rsync --delete-excluded` MUST be guarded by `.rsync-filter` protecting `/public/uploads/***`, `/storage/***`, and `.env`.

---

## 5. Git & Shared-Lib Release Hygiene

1. **Pull Before You Type:** Always execute `git fetch` and `git pull --rebase origin master` before editing source code in shared libraries.
2. **Remote Tag Verification:** Query remote tags (`git tag -l | sort -V`) before tagging a new release. Never assume local version numbers.
3. **Cross-App Propagation:** When `shared-lib` is modified and tagged, **all 8 consumer applications must be immediately updated** to the new release tag.
4. **Execution & Accountability Manifest:** Include the attribution manifest on deliverable reports identifying who engineered it (Victor/James) and who red-teamed it (Marcus/Ghost/Dr. Kovács).
