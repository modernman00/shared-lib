# 💳 FinTech & Credit Underwriting Governance

> **Scope:** Read and enforce this guide ONLY when working on `LoanEasyFinance`, `iAccountApp`, `iDecide`, or `TenantScore`.

---

## 1. Financial Integrity & Ledger Math
- **Double-Entry Ledger Accuracy:** All financial transactions, disbursements, and repayments must follow mathematically proven double-entry accounting invariants. Never use floating-point arithmetic (`float`) for currency calculations; use integer minor units (pence/cents) or arbitrary-precision decimal types (`BCMath`).
- **Concurrency & Row-Level Locking:** Balance updates and payment disbursements must use database transactions (`BEGIN` / `COMMIT`) with `SELECT ... FOR UPDATE` row-level locks to prevent race conditions and double-spending.

## 2. Regulatory & Fraud Controls (FCA / PSD2)
- **Zero Regulatory Compromise:** Stress-test payment rails, KYC/AML funnels, and webhook replay protection.
- **Strict Authorization & IDOR Prevention:** Every financial endpoint must validate ownership against the authenticated user/tenant ID before returning or mutating records.
- **Audit Logging:** All credit scoring decisions, underwriting overrides, and disbursement triggers must record immutable entries via `\Src\AuditLogger::log()`.

## 3. Commercial & Unit Economics
- **Value Justification:** Features must demonstrate clear unit economics (LTV:CAC, net interest margin protection, or credit friction reduction) without introducing unprofitable sprawl.
