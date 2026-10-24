# RFC-0003: MenuVisionExtractor (read a menu photo or PDF into dishes)

- **Author(s):** Ryan Mitchell (Squad Lead, Social & Lifestyle), Daniel (Full Stack)
- **Lead TPM:** Oluwafemi "Femi" Adeleke
- **Technical Reviewer(s):** Damilola Ogunleye (Backend)
- **Gatewatchers:** David (Architecture) / Marcus & Dapo (SecOps) / Ajibike (AI governance)
- **Status:** IMPLEMENTED in the working tree, awaiting CEO review. Not committed, tagged or pushed.
- **Created Date:** 2026-10-24
- **Target Apps:** shared-lib (new code only); first consumer PartyPlatform (restaurant menus Step 5, `docs/rfcs/2026-10-23_restaurant_menus.md` in that repo)
- **Tier:** 2 (new external call with untrusted input; new release of the shared library)

## 1. Why

PartyPlatform hosts who book a restaurant need its menu in the app. Pasting works, but a printed menu or a PDF cannot be copied. The library already talks to Gemini (`Src\Fintech\Vision\DocumentVisionEngine`), but that class is payslip/ID specific, its transport is private, and it silently returns simulated data when the key is missing or the call fails. That is acceptable for a score and wrong for a menu: a host would be shown dishes nobody read.

Non-goals: reading web pages or delivery platforms (CEO decision 2026-10-23: no scraping), prices guessing, translation, ordering.

## 2. Design

New namespace `Src\Vision`; nothing existing is modified.

| Class | Job |
|---|---|
| `MenuVisionExtractor::extract(string $filePath)` | Validate the file, call the provider, validate the answer, return `{items, warnings}`. |
| `MenuVisionExtractor::normalise(array)` | Pure: turns the model's JSON into validated items. Public so callers and tests can use it without a network. |
| `VisionTransport` / `CurlVisionTransport` | One HTTPS POST behind an interface (testable, chaos-testable). TLS verification on, no redirects, HTTPS only. |
| `VisionException` + 5 subclasses | `NotConfigured`, `InvalidInput`, `Timeout`, `RequestFailed`, `Parse`. Messages are safe to show a user. |

Item shape matches PartyPlatform's paste parser so one review screen and one save path serve both: `category` (Starter/Main/Dessert/Drink), `item_name`, `description`, `price_pence`, `dietary_tags` (comma list), `section_label`.

Config: `GEMINI_API_KEY` (already in the apps), optional `GEMINI_MENU_MODEL` (default `gemini-2.0-flash`, matching the existing engine), timeouts 5 s connect / 45 s total, 8 MB file cap.

## 3. Blast radius

- **Shared library:** additive only. `DocumentVisionEngine`, the FinTech apps and every existing class are untouched. No new Composer dependency (cURL and `finfo` are core).
- **Portfolio:** no app changes behaviour until it calls the new class. Release as **v2.7.0** (minor, new feature). Per Mandate 8 all affected apps must then move to the new version; only PartyPlatform uses it, the rest take the bump with no code change.
- **Cost:** one Gemini call per upload. Callers must cap per host per day (PartyPlatform: Step 5).

## 4. Failure modes (chaos)

No key: `NotConfigured`, nothing sent. Missing, empty, oversized, or wrong-type file: `InvalidInput`, nothing sent; the type is sniffed from the bytes, so a text or PHP file renamed `.png` is refused. Provider timeout: `Timeout`. 400/403/429/5xx: `RequestFailed` with the status; the provider's body is never passed on (it can echo the key or URL). Blocked, truncated, non-JSON, empty or unusable answer: `Parse`. No retries inside the class. In every case the caller shows "add the dishes by hand" instead of fake data.

## 5. Security

- **Key handling:** the key travels in the `x-goog-api-key` header, never in the URL (the old engine puts it in the query string, where logs and error text can capture it). Tested.
- **Untrusted model output:** strict response schema requested; then every field is type-checked, stripped of markup and control characters, length-capped, mapped onto allowlists (course, dietary tags), de-duplicated, and capped at 200 dishes. Prices must be a finite number from 0 to 10,000 or become null.
- **Prompt injection:** the prompt states the document is untrusted data and to never follow instructions in it; the model has no tools, so the worst outcome of a hostile menu is odd dish text, which the host reviews and which is encoded on output. Tested.
- **Marcus / Dapo PoC (automated in `tests/Vision/MenuVisionExtractorTest.php`):** a `.png` that is really text/PHP is rejected before any request; a provider error body containing the key never reaches the exception message; markup in a dish name never survives.

## 6. Dual sign-off (Mandate 10.8)

- **Victor (CTO), blast radius:** additive namespace, no existing class or dependency changed, no app affected until it opts in. **Approved**, condition: the release notes say that `DocumentVisionEngine` still simulates on failure and must not be used for anything a user will trust without review.
- **Olutobi (Audit/TAT), cost/benefit:** removes the main friction in the restaurant-menu feature for one Gemini call per upload; cost is bounded by the caller's daily cap and the 8 MB limit. **Approved**, conditions: the consuming app must enforce a per-host daily cap and a visible review screen before anything is saved; no web fetching.
- **David (gates):** PHPStan L8 clean on `src/Vision` (the 19 errors elsewhere in `src` pre-date this and are unchanged), typed exceptions instead of silent fallbacks, bounded timeouts, no cached state, defensive typing on all provider data. Chaos tests cover timeout, HTTP errors, truncated and blocked answers.

## 7. Definition of Done

- [x] `tests/Vision/MenuVisionExtractorTest.php`: 46 tests, 98 assertions, no network.
- [x] PHPStan level 8: no errors in `src/Vision`.
- [ ] Commit, tag v2.7.0, push (needs the CEO's go-ahead; `release.sh` bumps `config/version.php` and tags).
- [ ] PartyPlatform `composer update modernman00/shared-lib` and Step 5 (upload, review screen, per-host cap).
