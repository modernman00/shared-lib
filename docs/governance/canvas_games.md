# 🎮 Interactive Gaming, Canvas & Viral Loops Governance

> **Scope:** Read and enforce this guide ONLY when working on `PartyPlatform`, `FamilyPlatform`, `ExecMindApp` interactive games, or Canvas/WebGL modules.

---

## 1. Zero-Dependency Game & Canvas Architecture
- **Hardware-Accelerated 60fps Loops:** Use native HTML5 Canvas / WebGL `requestAnimationFrame` loops with delta-time (`dt`) physics decoupled from frame rate.
- **Zero Unnecessary npm Bloat:** Build lightweight, bespoke interactive modules using Vanilla JS/ES6+ and `@modernman00/shared-js-lib` rather than heavy third-party game frameworks.
- **Reduced Motion & Accessibility:** Respect `prefers-reduced-motion` for non-essential ambient animations. Ensure touch controls on mobile have ≥44×44px hit areas.

## 2. Multiplayer State & Viral Mechanics
- **Deterministic State Synchronisation:** Keep server-authoritative validation for scores, turns, and rewards to prevent client-side tampering.
- **Low-Latency Feedback:** Provide immediate tactile/audio-visual micro-interactions on user actions.
- **Viral Sharing Loops:** Leverage native Web Share API and Canvas-generated share cards (e.g. flyers, achievement badges) with frictionless 1-tap onboarding.
