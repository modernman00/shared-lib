## 2026-09-24T06:42:10Z

You are a Security, SSRF, & Test Infrastructure Explorer.
Your working directory is: /Users/waleolaogun/Sites/shared-lib/.agents/explorer_survey_security/
The project root is: /Users/waleolaogun/Sites/shared-lib
The user's original request is at: /Users/waleolaogun/Sites/shared-lib/.agents/ORIGINAL_REQUEST.md
Master Governance Charter is at: /Users/waleolaogun/Sites/shared-lib/AGENTS.md

Your task is to investigate the security risks, governance requirements, and test infrastructure for the PWA notification system:
1. Push Service Endpoint SSRF Vulnerability Analysis:
   - How attackers exploit push subscription endpoints (submitting AWS metadata `http://169.254.169.254`, localhost `127.0.0.1`, internal RFC1918 private IPs, non-standard ports, scheme manipulation).
   - Legitimate push service domain patterns and allowlists: Google FCM (`*.googleapis.com`, `*.fcm.googleapis.com`), Apple APNs WebPush (`*.push.apple.com`), Mozilla Autopush (`*.push.services.mozilla.com`), Microsoft WNS (`*.notify.windows.com`), Opera/UC, etc.
   - Safe URL parsing and IP resolution validation (DNS rebinding defense, scheme must be HTTPS, standard port 443).
2. VAPID RFC 8292 and RFC 8291 Payload Encryption:
   - Standards for VAPID headers (`Authorization: vapid t=..., k=...` or `WebPush ...`, `Crypto-Key`).
   - Payload encryption using ECDH (curve P-256) and AES-128-GCM.
   - Handling of public/private VAPID keys, subject (mailto or URL).
3. Bounded cURL and Network Timeout Constraints (David Gate 4):
   - Strict connection timeout (e.g., 3-5s) and execution timeout (e.g., 10s).
   - Retry strategies and circuit breaker / fallback queue mechanics.
4. Static Analysis and Quality Gates:
   - PHPStan Level 8 configuration in the project, defensive null typing, return types.
   - Test framework setup (PHPUnit execution, mock libraries, testing cURL/network requests without external network access).
5. Threat model & PoC attack scenarios that Marcus/Ghost will test (e.g., malicious subscription endpoint SSRF, malformed payload injection, ReDoS, credential leak in logs).

Write your complete findings into `/Users/waleolaogun/Sites/shared-lib/.agents/explorer_survey_security/handoff.md`.
Send a completion message back to the orchestrator when finished with a summary.
