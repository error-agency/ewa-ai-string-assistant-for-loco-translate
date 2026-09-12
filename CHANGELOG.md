# Changelog — EWA AI Translator for Loco Translate

All notable changes to this plugin are documented here.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
Versioning follows [Semantic Versioning](https://semver.org/).

## [1.6.1] — 2026-08-14

### Translation Pipeline Overhaul & Security Hardening
- **`msgctxt` Support & Safe Deduplication** — Full `msgctxt` support across the entire pipeline. Deduplication keys now use JSON-encoded `[msgctxt, msgid, msgid_plural]` arrays to prevent context collisons.
- **Deterministic ID-Based AI Protocol** — Sent items include stable IDs based on original PO indices (`entry_154`). AI responses are parsed via JSON envelope (`{"translations": [...]}`) and mapped deterministically by ID.
- **Strict Validation Layer** — Added `Translation_Validator` class for full tokenization of printf specifiers (`%s`, `%d`, `%02d`, `%1$s`, `%2$03d`, `%%`), variable templates (`{var}`, `{{var}}`), HTML tags/attributes (`href`, `src`, `title`, `alt`), and HTML entities (`&nbsp;`, `&amp;`, `&hellip;`).
- **Plural Form Integrity & Strategy B** — Plural forms are validated against `nplurals`. Partial plural entries are treated as incomplete translation units and fully regenerated.
- **Job Transient State & Idempotency** — Job state is maintained in `ewa_job_{job_id}` transients. Includes `request_id` idempotency protection for AJAX retries.
- **Non-Fuzzy Failure Tracking** — API/network failures no longer pollute PO entries with gettext fuzzy flags (`#, fuzzy`).
- **Machine-Readable Error Classification** — `Api_Client` surfaces WP_Error data payloads (`http_status`, `retryable`, `retry_after`). Permanent errors (401, 403, 404) abort immediately. Validation failures trigger automatic prompt corrections.
- **Atomic PO Saving & Permissions** — PO writes use temporary files (`.tmp.*`), `LOCK_EX`, permission preservation (`chmod`), and atomic `rename()`. MO compilation warnings are surfaced to UI.
- **Canonical Roots Path Security** — Hardened `validate_po_path()` using `realpath()` boundary checks against allowed WordPress language and plugin/theme locations.
- **API Key DOM Masking** — API key input fields no longer expose secrets in raw HTML DOM attributes and support explicit key clearing.
- **Automated Test Suite** — Created CLI automated test runner (`tests/test-pipeline.php` and `tests/bootstrap.php`) covering 32 pipeline assertions.

---

## [1.6.0] — 2026-08-14

### Rebrand & WordPress.org Compliance
- **Plugin Rebrand** — Rebranded plugin to **EWA AI Translator for Loco Translate** by **Error Web Agency (EWA)**.
- **Canonical Slug & Text Domain** — Standardized canonical slug (`ewa-ai-translator-for-loco-translate`) and text domain (`ewa-ai-translator-for-loco-translate`).
- **Main Plugin File Rename** — Renamed main plugin file to `ewa-ai-translator-for-loco-translate.php`.
- **PHP Namespacing & Global Prefixing** — Refactored PHP codebase into the `ErrorWebAgency\LocoAITranslator` namespace and prefixed WordPress global identifiers with `ewa_`.
- **Removed GitHub Update Checker** — Removed legacy update checker libraries completely to rely exclusively on WordPress.org update distribution.
- **Settings Migration** — Implemented backward-compatible settings migration to `ewa_settings` with schema version tracking (`ewa_schema_version`).
- **Standardized Requirements** — Standardized minimum PHP requirement to 7.4 and WordPress requirement to 6.0 across all plugin headers and documentation.
- **WordPress.org Readme** — Created official `readme.txt` with Third-Party Services disclosures for OpenRouter, Ollama, and custom OpenAI-compatible endpoints with verified official links.
- **Licensing & Attribution** — Standardized `LICENSE` file (GPL-2.0-or-later) and source code attributions (Developer: Error Web Agency (EWA), Lead Developer: K2D).

---

## [1.5.3] — 2026-06-18

### Changed
- **WPCS & Code Quality Alignment** — Verified compliance of all newly added methods and variables with WordPress Coding Standards (using proper snake_case conventions, strict identity checks, sanitization, and array structures).

---

## [1.5.2] — 2026-06-18

### Fixed
- **Deduplication of duplicate strings** — Identical untranslated strings are now grouped and sent to the AI only once per batch, then propagated to all duplicates in memory, reducing API token costs.
- **Bypass of non-translatable strings** — Strings that are purely numeric, URLs, special HTML characters, or pure placeholders are auto-translated in memory without making any AI API requests.

### Added
- **Dynamic Character-Based Batching** — Batches are dynamically limited by character count (up to 3,000 characters of source text) to balance payload size, preventing timeouts on long strings and optimizing request size for short ones.
- **Cache-Friendly Prompt** — Shortened and refactored the default system prompt to reduce prompt tokens and make it compatible with provider-level prompt caching.

---

## [1.5.1] — 2026-06-18

### Fixed
- **Plural strings skipped** — Plural entries (with `msgid_plural`) were previously skipped or only partially translated. Now they are correctly detected and translated.
- **Support for translating plural forms** — AI prompt and API client now request exactly the number of plural forms (`nplurals`) required by the target language.

### Added
- **Performance Caching (Transient Cache)** — Added transient-based caching of the parsed PO entries array during translation jobs.

---

## [1.5.0] — 2026-03-19

### Fixed
- **Translation loop offset bug** — Always slice untranslated entries from index 0.
- **Progress bar display** — Calculate percent based on original vs remaining strings.
- **Failed batch handling** — Flag failed batches as fuzzy to prevent infinite retries.

### Added
- **Token usage tracking** — Surface token counts from API response choices.
- **Per-batch log table** — Display batch stats and source text previews.
- **Live summary strip** — Running totals of time, strings, and tokens.

---

## [1.1.0] — 2026-03-19

### Fixed
- **Editor Panel Injection** — Improved multi-selector panel injection in Loco Translate editor.
- **PHP 7.4 Compatibility** — Replaced PHP 8.0-only functions for host compatibility.

---

## [1.0.0] — 2026-03-18

### Added
- Initial release of AI translation add-on for Loco Translate.
