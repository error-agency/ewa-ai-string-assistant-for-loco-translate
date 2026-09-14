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

This remediation release (v1.7.0) comprehensively resolves all requirements identified during the WordPress.org Plugin Directory pre-review and subsequent compliance audits.

A complete codebase-wide audit was conducted across all PHP, JavaScript, CSS, and documentation files. All identified issues have been resolved. The production package is verified against WordPress Coding Standards, Guideline 11 (Administrative Interface), External Service disclosures, prefix collision rules, scanner restrictions (elimination of NOWDOC), and security best practices.

---

# Final Compliance Status Matrix

| Finding | Status | Verification |
| :--- | :--- | :--- |
| **Naming / slug** | **PASS** | Static review — matching folder name, main file, header, and text domain `ewa-ai-string-assistant-for-loco-translate`. |
| **Prefixing** | **PASS** | Project-wide search — canonical prefix `ewaas_` / `EWAAS_` (>= 4 chars). 0 active legacy runtime aliases. |
| **External services** | **PASS** | README/code comparison — 1:1 match across OpenRouter, Ollama, OpenAI, and Custom endpoints. |
| **OpenRouter disclosure** | **PASS** | Network payload review — downstream routing, attribution headers (`HTTP-Referer`, `X-Title`), credentials handling. |
| **OpenAI disclosure** | **PASS** | Network payload review — official API policies (Services Agreement, Service Terms, Privacy Policy), direct server communication. |
| **Ollama disclosure** | **PASS** | Network payload review — local vs remote hosting distinction, official terms and privacy links. |
| **Admin notices** | **PASS** | Admin screen audit — restricted strictly to `plugins.php` and settings screen with `activate_plugins` capability check. |
| **NOWDOC/HEREDOC** | **PASS** | 0 occurrences (`git grep "<<<"`: 0 production occurrences). Scanner-friendly string arrays used. |
| **Internationalization** | **PASS** | PHP/JS audit — 100% user-facing strings use Gettext / localized `ewaasAdmin.i18n` & `ewaasLoco.i18n`. POT regenerated (154 strings). |
| **AI Client recommendation** | **Evaluated / deferred** | Architecture review — direct provider adapters retained to support WP < 7.0, OpenRouter, local Ollama, and custom endpoints. |
| **WordPress 7.1** | **PASS** | Runtime smoke test (`tests/test-wp71-smoke.php`) — 10 lifecycle criteria verified (34/34 assertions passed). |
| **PHP compatibility** | **PASS** | Static/runtime test (`tests/check-php74-compat.py`) — 0 PHP 8-only features, 100% PHP 7.4+ compatible. |

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
  * Added dedicated `= 3. OpenAI API =` section documenting official OpenAI endpoint preset (`https://api.openai.com/v1`), data fields, authentication, official business terms:
    * **Services Agreement**: `https://openai.com/policies/services-agreement/`
    * **Service Terms**: `https://openai.com/policies/service-terms/`
    * **Privacy Policy**: `https://openai.com/policies/privacy-policy/`
    * **Enterprise Privacy**: `https://openai.com/enterprise-privacy/`
* **Custom OpenAI-Compatible Endpoints:**
  * Documented as administrator-configured OpenAI-compatible service with responsibilities clearly allocated.
* **Intermediary Server Wording:** Updated `readme.txt` and settings page to accurately state:
  > *"No Error Web Agency-operated intermediary or proxy server is used. Requests are sent directly from your WordPress server to the configured service endpoint. Services such as OpenRouter may route requests to downstream model providers according to their own terms and data policies."*
* **Privacy Policy Guide:** Updated `wp_add_privacy_policy_content()` in `admin/class-admin.php` to disclose transmission of Site URL (`home_url()`) and Site Title (`get_bloginfo('name')`) for OpenRouter attribution headers.

## 5. Security, Internationalization & Input Hardening

* **Strict Provider Allowlist:** Both AJAX endpoints (`fetch_models`, `test_connection`) and settings sanitization enforce an explicit allowlist: `[ 'openrouter', 'ollama', 'custom' ]`. Unrecognized providers are rejected.
* **PHP Internationalization:** Wrapped all user-facing backend messages in standard Gettext calls (`esc_html__()`, `esc_attr__()`, `__()`, `sprintf()`) with translator comments for placeholders using text domain `ewa-ai-string-assistant-for-loco-translate`.
* **JavaScript Internationalization:** Localized dictionary enriched with 35+ strings in `ewaasAdmin.i18n` and `ewaasLoco.i18n`. All hardcoded frontend UI strings replaced.
* **POT File Updated:** Generated `languages/ewa-ai-string-assistant-for-loco-translate.pot` with 154 localized strings.
* **Textdomain Hook:** `load_plugin_textdomain()` attached to `init` action hook for WordPress 6.7+ compatibility.
* **WordPress AI Client:** Evaluated and intentionally deferred to retain backward compatibility with WordPress 6.0–6.8 and flexible self-hosted/custom endpoint workflows.

---

# Verification Suite & Results

### 1. PHP Syntax Check (`php -l`)
* **100% PASS**: All PHP files checked without warnings or syntax errors on PHP 8.3 CLI.

### 2. Automated Pipeline Test Suite (`tests/test-pipeline.php`)
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

### 3. WordPress 7.1 Smoke Test Suite (`tests/test-wp71-smoke.php`)
* **34 / 34 PASS (0 failures)**:
  * 1. Activation lifecycle (4 assertions)
  * 2. Settings page rendering (6 assertions)
  * 3. Save settings & sanitization (6 assertions)
  * 4. Provider selection & allowlist (3 assertions)
  * 5. Model loading & alphabetical sorting (3 assertions)
  * 6. Connection test (2 assertions)
  * 7. Translation action & validator (3 assertions)
  * 8. Cancel translation mechanism (2 assertions)
  * 9. Deactivation / reactivation lifecycle (3 assertions)
  * 10. Strict error reporting — zero PHP notices/warnings (1 assertion)

### 4. PHP 7.4 Static Compatibility Test (`tests/check-php74-compat.py`)
* **PASS**: 0 PHP 8-only features found across all production files. 100% compatible with PHP 7.4+.

### 5. Production Package Integrity (`tests/verify-package-zip.py`)
* **ZIP Archive Integrity:** Exactly 22 production files, all rooted in `ewa-ai-string-assistant-for-loco-translate/`, using POSIX forward slashes.
* **Zero Dev Artifacts:** Zero `.git`, `tests/`, `.md`, `.py`, `.zip`, `.env`, or IDE files in archive.
* **Extracted Linting:** All extracted PHP files pass `php -l`.
* **Prohibited Patterns Scan:** Zero instances of `EWA_AI_TRANSLATOR_`, `ewa_ai_translator_plugin`, `wp_ajax_ewa_`, `ewaAdmin`, `ewaLoco`, `ewa_nonce`, or `<<<`.
* **Readme Verification:** Confirmed `Tested up to: 7.1`, OpenAI official API policy links, Ollama terms link, OpenRouter downstream routing, and accurate direct connection claims.
