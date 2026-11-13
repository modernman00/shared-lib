# 🏥 BCP Healthcare & CQC Compliance Governance

> **Scope:** Read and enforce this guide ONLY when working on **Best Care Professionals (BCP)** or healthcare HR/clinical features.

---

## 1. Regulatory & Clinical Hard-Stops
- **CQC Reg 18 / 19 Compliance:** Zero tolerance for box-ticking compliance that fails inspection. Enforce hard-stops on expired/missing training, DBS checks, and right-to-work documentation before shift allocation.
- **Caldicott Principle 5 (Data Minimisation):** Strictly limit access to patient/resident clinical data (MAR sheets, pressure ulcer tracking, safeguarding escalations) to authorised roles.
- **Audit-Ready Packs:** All compliance logs and approvals must generate immutable audit trails via `\Src\AuditLogger::log()`.

## 2. Workforce Bifurcation & CQC Reg 5 (FPPT)
1. **Workforce Bifurcation:** Never apply clinical care dependency ratios to office, administrative, or executive roles. Office roles are justified by operational workload and bed count (e.g., 1 Admin per 45 beds).
2. **CQC Regulation 5 Fit and Proper Person Test (FPPT):** All Registered Care Home Manager (Band 7) and Director (Band 8a) recruitment pipelines MUST enforce:
   - Insolvency Register checks
   - Companies House Disqualified Directors Register checks
   - Level 5 Leadership Diploma verification

## 3. Frontline Carer UX
- **Mobile-First & Offline Resilience:** Frontline workflows (clock-in, shift acceptance, EWA transparency) must work reliably in low-connectivity environments (e.g. care home car parks at 06:55 AM) with ≥44×44px touch targets.
- **Care Home Continuity:** Prioritise 1-click approvals and Preferred Pools to maintain shift fill rates >98%.
