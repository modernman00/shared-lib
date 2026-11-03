# RFC-0012: Universal Post-Login & App-Open Biometric Passkey Activation UI

- **Status:** Approved (TAT 2.0 Final Executive Sign-Off by Olutobi)
- **Date:** 2026-11-03
- **Authors:** Callum Wright (Lead Staff Full-Stack Engineer), Maya Lindqvist (Senior Staff Frontend Architect), Tariq Vance (Lead PWA Engineer)
- **Reviewers:** Victor (CTO), James (Head of TFT), David (Gatewatcher), Dr. Viktor Kovács & Marcus (SecOps), Sarah (CPO)
- **Scope:** `modernman00/shared-lib`, `@modernman00/shared-js-lib`, and all 8 portfolio applications (`PartyPlatform`, `FamilyPlatform`, `iDecide`, `iAccount`, `TenantScore`, `ExecMind`, `LoanEasyFinance`, `BCP`)

---

## 1. Context & Problem Statement
Biometric Passkey (FIDO2 / WebAuthn) authentication is implemented across the portfolio, allowing users to sign in via Face ID, Touch ID, Android Biometrics, or Windows Hello. However, passkey registration was previously hidden inside secondary Account Settings pages. In native mobile and fintech applications, users are prompted to activate biometric sign-in immediately after logging in or opening the app with an active authenticated session.

## 2. Architecture & Data Flow

### 2.1 Server-Side State Resolution (`Src\functionality\WebAuthnFunctionality`)
1. **Unified Session & JWT Detection (`resolveAuthenticatedUser()`):**
   Inspects active PHP session keys (`$_SESSION['user_id']`, `$_SESSION['id']`, `$_SESSION['auth']['identifyCust']`, `$_SESSION['manager_id']`, `$_SESSION['staff_email']`, `$_SESSION['client_email']`) and falls back to `SignIn::rehydrateSession()` when an `auth_token` cookie is present.
2. **$O(1)$ Session-Cached Enrolment Check (`getClientPromptState()`):**
   Queries `SELECT COUNT(*) FROM user_passkeys WHERE user_id = ?` once per session, caching the boolean result in `$_SESSION['webauthn_enrolled_' . $userId]`.
   - When `registerDevice()` succeeds, sets `$_SESSION['webauthn_enrolled_' . $userId] = true`.
   - When `revokeDevice()` deletes a passkey, clears `$_SESSION['webauthn_enrolled_' . $userId]` so it re-evaluates accurately.
3. **Duplicate Credential Prevention (`excludeCredentials`):**
   `getRegistrationOptions()` queries existing `credential_id` values for the authenticated user and passes them in `excludeCredentials` so platform authenticators never create duplicate passkeys for the same user on the same authenticator.
4. **Status Endpoint (`GET|POST /webauthn/status`):**
   Returns `{ status: "success", data: { authenticated: bool, userKey: string, hasPasskey: bool, passkeyCount: int } }`.

### 2.2 Client-Side Native Activation Engine (`PasskeyPromptManager` in `@modernman00/shared-js-lib`)
1. **Hardware Capability Detection:**
   Verifies `window.isSecureContext`, `window.PublicKeyCredential`, and `PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()`.
2. **Trigger Conditions:**
   Displays the native-style Biometric Passkey Activation UI after login or app open when:
   - `state.authenticated === true`
   - `state.hasPasskey === false`
   - `localStorage.getItem('passkey_enrolled_' + state.userKey) !== 'true'`
   - Not dismissed within the 72-hour cooldown window (`localStorage.getItem('passkey_dismissed_' + state.userKey)`)
   - Current route is not a login/register/reset/error page or guest-hold page.
3. **4-State UI & Accessibility:**
   - Accessible dialog (`role="dialog"`, `aria-modal="true"`, `Escape` key handler, $\ge 44\text{px}$ tap targets, British English copy, hardware-specific label detection for Face ID / Touch ID vs Fingerprint / Windows Hello).
   - Immediate loading spinner on click (`Verifying Biometrics...`), bounded by a 60s hardware timeout and 10s network watchdog.
   - Graceful handling of `InvalidStateError` (marks device as enrolled if credential already exists in platform keychain) and `NotAllowedError` (allows retry or "Maybe Later" without error lockups).

## 3. Security & Failure Modes
- **Single-Use Cryptographic Challenge:** `$_SESSION['webauthn_challenge']` is consumed and unset on first verification attempt.
- **Per-User Storage Isolation:** `localStorage` keys include a non-PII `userKey` hash (`substr(hash('sha256', $appName . ':' . $userId), 0, 16)`) so shared devices prompt each distinct user account accurately.
- **Database Self-Healing:** `WebAuthnFunctionality::ensureSchema()` idempotently creates `user_passkeys` if missing, preventing 500 errors during rollout.
