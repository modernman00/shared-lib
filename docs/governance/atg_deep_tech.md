# 🔬 Advanced Technology Group (ATG) — Sovereign R&D & Deep-Tech Standards

> **Scope:** Read and enforce this guide when designing **proprietary core systems, ML/AI pipelines, real-time analytics, zero-dependency micro-libraries, or replacing paid third-party SaaS/COTS software** across the 8 portfolio apps.

---

## 1. Core Mandate: Zero Unnecessary External Subscriptions
Any capability that can be engineered natively into `shared-lib` (PHP 8.2+) or `shared-js-lib` (Vanilla JS/TS) with superior performance and zero recurring licensing cost MUST be built in-house:
- **Search:** Native indexed/full-text search over Algolia.
- **Real-Time Events:** Native SSE / WebSockets / push routers over Pusher.
- **Feature Flags & Config:** Native lightweight flag routers over LaunchDarkly.
- **Document & Media Synthesis:** Native server/Canvas PDF & image synthesis over paid third-party rendering APIs.

---

## 2. Technical Pillars

### 🤖 1. Applied ML & Sovereign AI
- **Local / Edge Inference & Scoring:** Design lightweight predictive scoring models (credit risk, tenant profiling, shift allocation) and semantic/vector routing without bloated external dependencies.
- **Deterministic Fallbacks (Mandatory):** Every ML/AI pipeline MUST provide a graceful, deterministic rule-based fallback if model confidence falls below **95%** or if hardware/API constraints occur.
- **AI Safety & Guardrails:** Enforce prompt-injection neutralisation, strict output schemas, and PII/privacy leak prevention.

### 📊 2. High-Velocity Data & Streaming Telemetry
- **Zero-Lock Analytics:** Use append-only event buses and non-blocking aggregations for real-time telemetry (session tracking, rage-click/friction detection).
- **Financial & Ledger Integrity:** Use mathematically proven double-entry ledger engines with atomic transactions and row-level locking.

### 🧱 3. Sovereign Micro-Libraries (`shared-lib` / `shared-js-lib`)
- Build modular, zero-bloat, zero-symlink libraries shared across all 8 applications.
- Keep public APIs clean, strictly typed (`string` IDs, PHPStan Level 8), and documented in British English (en-GB).

### 🛡️ 4. Algorithmic Profiling & Stress Benchmarking
- **Complexity Ceiling:** Core algorithms and data pipelines must target $\le O(n \log n)$ time complexity.
- **Memory & Concurrency Audits:** Profile memory footprints, eliminate leaks, verify database query plans (`EXPLAIN`), and run high-concurrency stress benchmarks before certifying any R&D engine for production.
