# WordPress.org Review Remediation Report

**Plugin Name:** EWA AI String Assistant for Loco Translate  
**Canonical Slug:** `ewa-ai-string-assistant-for-loco-translate`  
**Version:** 1.7.0  
**Author:** Error Web Agency  
**Contributors:** errorwebagency  
**Text Domain:** `ewa-ai-string-assistant-for-loco-translate`  
**Tested up to:** 7.1  
**Requires at least:** 6.0  
**Requires PHP:** 7.4  
**Development Repository:** https://github.com/error-agency/ewa-ai-string-assistant-for-loco-translate  

---

# Executive Summary

This remediation release (v1.7.0) comprehensively resolves all requirements identified during the WordPress.org Plugin Directory pre-review and subsequent compliance audit.

A complete codebase-wide audit was conducted across all PHP, JavaScript, CSS, and documentation files. All identified issues have been resolved. The production package is verified against WordPress Coding Standards, Guideline 11 (Administrative Interface), External Service disclosures, prefix collision rules, scanner restrictions (elimination of NOWDOC), and security best practices.

---

# Verification & Compliance Matrix

| WordPress.org finding | Resolution | Verification |
| :--- | :--- | :--- |
| **Prefixes** | **Resolved** — Unified canonical prefix `ewaas_` (6 chars, >= 4 requirement) across all functions, constants (`EWAAS_`), options (`ewaas_settings`, `ewaas_schema_version`), AJAX actions (`wp_ajax_ewaas_*`), transients (`ewaas_job_*`, `ewaas_cancel_*`), nonces (`ewaas_nonce`), script/style handles, and JS globals (`ewaasAdmin`, `ewaasLoco`). Legacy `ewa_` exists exclusively inside an idempotent migration routine and uninstaller cleanup. | Project-wide audit (`git grep`) confirmed 0 active runtime `ewa_` registrations, 0 legacy constants, 0 legacy AJAX aliases, 0 legacy JS globals. |
| **Direct AI provider** | **Evaluated / Intentionally Retained** — Directly evaluated WordPress 7.0 AI Client (`wp_ai_client_prompt`). Retained direct provider adapter to preserve backward compatibility with WordPress 6.0–6.8, local Ollama endpoints (`http://localhost:11434`), OpenRouter aggregation, and administrator-configured endpoints. | Architecture review & technical rationale documented for review response. |
| **External services** | **Resolved** — Comprehensive `== External Services ==` section in `readme.txt` documenting OpenRouter (with downstream model provider routing), Ollama (with official Terms of Service link), OpenAI API (dedicated section for the official API preset), and Custom OpenAI-Compatible Endpoints. Wording updated to accurately state direct connections without agency-operated intermediary servers. | README / code 1:1 comparison against network payloads in `class-api-client.php`. All official URLs verified. |
| **Admin notices** | **Resolved** — Confined administrative dependency warnings strictly to `plugins.php` and the plugin's own settings screen with `activate_plugins` capability check and `is-dismissible` classes. Zero global notices or activation redirects. | Screen scope review in `ewa-ai-string-assistant-for-loco-translate.php` with allowlist checking. |
| **Naming / trademark** | **Resolved** — Plugin display name rebranded to `EWA AI String Assistant for Loco Translate`, slug to `ewa-ai-string-assistant-for-loco-translate`, main file to `ewa-ai-string-assistant-for-loco-translate.php`, and text domain synchronized. Prominent disclaimers included regarding Loco Translate independence. | Verified matching slug, folder name, main file name, text domain, and headers. |
| **Common technical issues** | **Resolved** — Eliminated all NOWDOC/HEREDOC constructs (`<<<'PROMPT'` replaced with array + `implode()`). Moved `load_plugin_textdomain()` to `init` hook for WordPress 6.7+ compatibility. Hardened provider input with strict allowlist (`openrouter`, `ollama`, `custom`). Wrapped all user-facing strings in Gettext functions. | Static code scanning (`git grep "<<<"`: 0 occurrences in production code), `php -l` on all files, and automated test suite (49/49 passed). |

---

# Detailed Remediation Breakdown

## 1. Complete Prefix Remediation (P0)

* **Canonical Prefix:** `ewaas_` / `EWAAS_` (6 characters, strictly compliant with WordPress.org minimum 4-character rule).
* **Constants:** Replaced `EWA_AI_TRANSLATOR_*` with `EWAAS_VERSION`, `EWAAS_PATH`, `EWAAS_URL`, `EWAAS_BASENAME`. Removed all legacy aliases.
* **Global Functions:** Removed legacy alias `ewa_ai_translator_plugin()` and `if ( ! function_exists(...) )` collision-prone wrappers. Access is provided via namespaced `Plugin::instance()` and canonical `ewaas_plugin()`.
* **AJAX Registrations:** Removed all dual registrations (`wp_ajax_ewa_*`). Only canonical `wp_ajax_ewaas_*` hooks are registered:
  * `wp_ajax_ewaas_get_po_info`
  * `wp_ajax_ewaas_translate_file`
  * `wp_ajax_ewaas_cancel_job`
  * `wp_ajax_ewaas_fetch_models`
  * `wp_ajax_ewaas_test_connection`
* **Nonces:** Removed dual-checking for `ewa_nonce`. Exclusively validates `ewaas_nonce`.
* **Transients:** Purged fallback references to `ewa_job_*` and `ewa_cancel_*`. Exclusively uses `ewaas_job_{job_id}` and `ewaas_cancel_{job_id}`.
* **JavaScript Globals:** Removed legacy aliases `ewaAdmin` and `ewaLoco`. Localized data and frontend scripts exclusively reference `ewaasAdmin` and `ewaasLoco`.

## 2. Database Options & Idempotent Settings Migration

* **Canonical Keys:**
  * Option Key: `ewaas_settings`
  * Schema Version: `ewaas_schema_version`
  * Settings Group: `ewaas_settings_group`
* **Idempotent Migration:**
  * In `Settings::maybe_migrate_settings()`, if `ewaas_settings` does not exist, the routine reads legacy `ewa_settings` (or older `error_lait_settings` / `lat_settings`), sanitizes all values, and writes them to `ewaas_settings`.
  * If `ewaas_settings` already exists, existing settings are preserved and never overwritten.
  * All user preferences (API keys, active provider, model, endpoint, temperature, batch size, custom system prompts) are preserved seamlessly on upgrade.
* **Admin Form Inputs:** Form input names in `admin/views/settings-page.php` updated from `name="ewa_settings[...]"` to `name="ewaas_settings[...]"` matching the canonical storage key.
* **Uninstallation:** `uninstall.php` cleans up both new canonical options (`ewaas_settings`, `ewaas_schema_version`) and legacy historical keys.

## 3. Scanner-Friendly Prompt Architecture (P0)

* **Eliminated NOWDOC:** Replaced `<<<'PROMPT' ... PROMPT;` in `Settings::default_system_prompt()` with an array of lines combined via `implode( "\n", $lines )`.
* **Static Scanner Cleanliness:** Project-wide scan for `<<<` confirms **0 occurrences** across all production PHP files.

## 4. Comprehensive External Services Disclosure (P0)

* **OpenRouter:**
  * Documented Purpose, Data Transmitted, When Transmitted, Provider, Service Website, Terms of Service, and Privacy Policy.
  * Added explicit disclosure regarding **Downstream Model Provider Routing** (OpenRouter routes translation prompts to the administrator's chosen model provider under that provider's data handling policies).
* **Ollama:**
  * Documented Purpose, Data Transmitted, Local vs Remote hosting distinction.
  * Added direct link to official **Terms of Service** (`https://ollama.com/terms`) and Privacy Policy (`https://ollama.com/privacy`).
* **OpenAI API:**
  * Added dedicated `= 3. OpenAI API =` section documenting official OpenAI endpoint preset (`https://api.openai.com/v1`), data fields, authentication, terms (`https://openai.com/policies/terms-of-use/`), privacy policy (`https://openai.com/policies/privacy-policy/`), and enterprise data policies.
* **Custom OpenAI-Compatible Endpoints:**
  * Documented as administrator-configured OpenAI-compatible service with responsibilities clearly allocated.
* **Intermediary Server Wording:** Updated `readme.txt` and settings page to accurately state:
  > *"No Error Web Agency-operated intermediary or proxy server is used. Requests are sent directly from your WordPress server to the configured service endpoint. Services such as OpenRouter may route requests to downstream model providers according to their own terms and data policies."*
* **Privacy Policy Guide:** Updated `wp_add_privacy_policy_content()` in `admin/class-admin.php` to disclose transmission of Site URL (`home_url()`) and Site Title (`get_bloginfo('name')`) for OpenRouter attribution headers.

## 5. Security & Input Hardening

* **Strict Provider Allowlist:** Both AJAX endpoints (`fetch_models`, `test_connection`) and settings sanitization enforce an explicit allowlist: `[ 'openrouter', 'ollama', 'custom' ]`. Unrecognized providers are rejected.
* **Internationalization:** Wrapped all user-facing backend messages in standard Gettext calls (`esc_html__()`, `__()`) using text domain `ewa-ai-string-assistant-for-loco-translate`. Regenerated template POT file containing 68 localized strings.
* **Textdomain Hook:** Moved `load_plugin_textdomain()` to `init` action hook to support WordPress 6.7+ best practices and avoid `_load_textdomain_just_in_time` notices.
* **Version Compatibility:** Tested up to WordPress 7.1.

---

# Verification Suite & Results

### 1. PHP Syntax Check (`php -l`)
* **100% PASS**: All PHP files checked without warnings or syntax errors on PHP 8.3 CLI.

### 2. Automated Test Suite (`tests/test-pipeline.php`)
* **49 / 49 PASS (0 failures)**:
  * 1. msgctxt separation & deduplication (5 assertions)
  * 2. Placeholder tokenizer validation (8 assertions)
  * 3. HTML & entity validation (4 assertions)
  * 4. Plurals & Strategy B partial regeneration (3 assertions)
  * 5. ID mapping & endpoint construction (6 assertions)
  * 6. PO round-trip integrity (4 assertions)
  * 7. Atomic file saving & compilation (2 assertions)
  * 8. Settings migration & idempotency (6 assertions)
  * 9. System prompt structure & absence of NOWDOC (5 assertions)
  * 10. Prefixing & absence of legacy identifiers (4 assertions)
  * 11. Provider allowlist validation (2 assertions)

### 3. Production Package Audit (`scratch/validate_zip.py`)
* **ZIP Archive Integrity:** Exactly 22 production files, all rooted in `ewa-ai-string-assistant-for-loco-translate/`, using POSIX forward slashes.
* **Zero Dev Artifacts:** Zero `.git`, `tests/`, `.md`, `.py`, `.zip`, or OS files in archive.
* **Extracted Linting:** All extracted PHP files pass `php -l`.
* **Activation Smoke Test:** Extracted package initializes cleanly without errors.
* **Prohibited Patterns Scan:** Zero instances of `EWA_AI_TRANSLATOR_`, `ewa_ai_translator_plugin`, `wp_ajax_ewa_`, `ewaAdmin`, `ewaLoco`, `ewa_nonce`, or `<<<`.
* **Readme Verification:** Confirmed `Tested up to: 7.1`, OpenAI section, Ollama terms link, OpenRouter downstream routing, and accurate direct connection claims.
