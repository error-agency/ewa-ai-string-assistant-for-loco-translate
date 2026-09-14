# WordPress.org Review Remediation Report

**Plugin Name:** EWA AI String Assistant for Loco Translate  
**Canonical Slug:** `ewa-ai-string-assistant-for-loco-translate`  
**Version:** 1.7.0  
**Author:** Error Web Agency  
**Contributors:** errorwebagency  
**Text Domain:** `ewa-ai-string-assistant-for-loco-translate`  
**Development Repository:** https://github.com/error-agency/ewa-ai-string-assistant-for-loco-translate  

---

# Executive Summary

This remediation release (v1.7.0) resolves all issues identified during the initial WordPress.org Plugin Directory pre-review for the plugin previously submitted under the slug `ewa-ai-translator-for-loco-translate`.

A comprehensive, codebase-wide audit was conducted across all PHP, JavaScript, CSS, and documentation files. All identified issues and their architectural equivalents were remediated. The codebase has been verified against WordPress Coding Standards, Guideline 11 (Administrative Interface), third-party external service disclosure guidelines, prefix collision rules, and security best practices.

---

# WordPress Review Findings

| # | Review Finding / Guideline | Severity | Remediation Summary | Status |
| :--- | :--- | :--- | :--- | :--- |
| 1 | **Naming & Slug Trademark/Similarity** | P0 | Rebranded to `EWA AI String Assistant for Loco Translate` (`ewa-ai-string-assistant-for-loco-translate`). Disclaimed Loco Translate affiliation. | **Resolved** |
| 2 | **Admin Notices / Guideline 11** | P0 | Removed global `admin_notices`. Confined dependency warnings strictly to `plugins.php` and settings screen with dismissal support. | **Resolved** |
| 3 | **Prefixing & Collision Isolation** | P0 | Unified prefix `ewaas_` on all globally accessible registrations (AJAX, scripts, styles, nonces, menu slug). Retained `ewa_settings` database option for backward compatibility. | **Resolved** |
| 4 | **External Services Disclosure** | P0 | Added full `== External Services ==` documentation in `readme.txt` for OpenRouter, Ollama, and Custom Endpoints with ToS and Privacy Policy URLs. | **Resolved** |
| 5 | **WordPress 7.0 AI Client Evaluation** | P1 | Formally evaluated `wp_ai_client_prompt()`. Adopted Option B (direct adapter retained) to preserve WP 6.0+ compatibility, local Ollama, and custom endpoints. | **Documented** |
| 6 | **Privacy Policy Integration** | P1 | Integrated `wp_add_privacy_policy_content()` with Core Privacy Policy Guide. | **Resolved** |
| 7 | **Author & Contributor Metadata** | P0 | Synchronized Author: `Error Web Agency`, Contributors: `errorwebagency` across plugin headers, readme, and package metadata. | **Resolved** |

---

# Naming / Trademark Resolution

1. **Display Name:** `EWA AI String Assistant for Loco Translate`
2. **Short / Menu Name:** `EWA AI String Assistant`
3. **Canonical Slug:** `ewa-ai-string-assistant-for-loco-translate`
4. **Main Plugin File:** `ewa-ai-string-assistant-for-loco-translate.php` (renamed from `ewa-ai-translator-for-loco-translate.php`)
5. **Text Domain:** `ewa-ai-string-assistant-for-loco-translate`
6. **Localization Template:** `languages/ewa-ai-string-assistant-for-loco-translate.pot`
7. **Trademark Clarification:** Added explicit disclaimer in `readme.txt`, `README.md`, and UI stating:
   > *"Loco Translate is an independent project by Tim Whitlock. This plugin is an independent third-party add-on and is not affiliated with, sponsored, or endorsed by Loco Translate or its authors."*

---

# Prefix Audit

All globally accessible identifiers have been audited and namespaced to eliminate naming collisions with WordPress Core, other plugins, or active themes:

* **PHP Namespace:** `ErrorWebAgency\EwaAIStringAssistant` encapsulates all core classes (`Plugin`, `Admin`, `Ajax`, `Settings`, `Api_Client`, `Po_Handler`, `Translation_Validator`).
* **Helper Function:** `ewaas_plugin()` (global namespace with backward-compatible alias `ewa_ai_translator_plugin()`).
* **PHP Constants:** `EWAAS_VERSION`, `EWAAS_PATH`, `EWAAS_URL`, `EWAAS_BASENAME` (with legacy aliases maintained for backward compatibility).
* **AJAX Actions (Admin-only):**
  * `wp_ajax_ewaas_get_po_info`
  * `wp_ajax_ewaas_translate_file`
  * `wp_ajax_ewaas_cancel_job`
  * `wp_ajax_ewaas_fetch_models`
  * `wp_ajax_ewaas_test_connection`
  *(Legacy aliases `wp_ajax_ewa_*` registered for session continuity).*
* **Script & Style Handles:**
  * `ewaas-admin` (Settings page stylesheet & script)
  * `ewaas-loco` (Loco Translate PO file editor toolbar injector)
* **Settings Group:** `ewaas_settings_group` (with alias `ewa_settings_group`).
* **Security Nonces:** `ewaas_nonce` (verified via `check_ajax_referer` with dual fallback).
* **Transients:** `ewaas_job_{job_id}` and `ewaas_cancel_{job_id}`.
* **Persistent Database Storage (Backward Compatibility):**
  * Retained option key `ewa_settings` and schema version key `ewa_schema_version`. Existing installations upgrading to 1.7.0 will not lose configured API keys, model selections, custom endpoints, or parameters.

---

# External Services

A dedicated `== External Services ==` section has been added to `readme.txt` documenting all external communication:

### 1. OpenRouter
* **Purpose:** Cloud AI model aggregation for automated Gettext PO string translation and model discovery.
* **Data Transmitted:** Untranslated PO source strings, Gettext context (`msgctxt`), plural forms, target language locale, system translation prompt, model parameters, API key (`Authorization: Bearer`), and site metadata (`HTTP-Referer`, `X-Title`).
* **When Transmitted:** Exclusively when an authenticated administrator initiates an explicit action: clicking "AI Translate" in Loco editor, clicking "Test Connection", or clicking "Load Models". Zero automatic background transmission.
* **Provider:** OpenRouter, Inc.
* **Terms of Service:** https://openrouter.ai/terms
* **Privacy Policy:** https://openrouter.ai/privacy

### 2. Ollama (Self-Hosted / Local LLM)
* **Purpose:** Running local or private neural translation without third-party cloud data transmission or API fees.
* **Data Transmitted:** Source strings, context, plural forms, target locale, system prompt, and model options.
* **Hosting Disclosure:** When configured with default `http://localhost:11434`, data remains 100% within the local server environment. Remote endpoints transmit data to the host configured by the admin.
* **When Transmitted:** Only upon explicit administrator action.
* **Provider:** Self-hosted application by Ollama.
* **Documentation:** https://ollama.com/
* **Privacy Policy:** https://ollama.com/privacy

### 3. Custom OpenAI-Compatible Endpoints
* **Purpose:** Connecting to user-specified OpenAI-compatible endpoints (direct OpenAI, Azure OpenAI, Groq, Mistral, private proxies).
* **Data Transmitted:** Source strings, context, plural forms, target locale, prompt parameters, model ID, and API key.
* **When Transmitted:** Only upon explicit administrator action.
* **Provider & Terms:** Configured and controlled by the website administrator. The administrator is responsible for verifying the privacy policy and terms of service of their chosen provider.

---

# Admin Notice Audit

To satisfy **WordPress.org Guideline 11** (avoiding intrusive notices and dashboard hijacking):

1. **Eliminated Unconditional Global Notices:** The plugin previously attached error/warning notices to `admin_notices` without screen restrictions when Loco Translate was inactive.
2. **Screen Isolation:** Notices are now strictly confined to:
   * `plugins.php` (where the administrator can take action to activate dependencies), and
   * `settings_page_ewa-ai-string-assistant-for-loco-translate` (the plugin's own settings screen).
3. **Capability Check:** Notices are only displayed if `current_user_can('activate_plugins')`.
4. **Dismissibility:** All notices include the standard WordPress `is-dismissible` class.
5. **Core Dependency Header:** Declared `Requires Plugins: loco-translate` in the main plugin header (WordPress 6.5+ native dependency resolution).
6. **Zero Activation Redirects:** Verified that no automatic redirection occurs on single-site activation, network activation, CLI, or bulk operations.

---

# WordPress AI Client Decision

### OPTION B

`Direct provider integration retained for compatibility. WordPress AI Client migration was evaluated but intentionally deferred because:`

1. **Ecosystem Compatibility:** The plugin maintains broad compatibility with WordPress 6.0 through 6.8+, whereas `wp_ai_client_prompt()` is introduced in WordPress 7.0. Requiring WordPress 7.0 would needlessly exclude the vast majority of active WordPress installations.
2. **Local & Self-Hosted Infrastructure:** A core feature of this plugin is zero-cost, private translation via local Ollama instances (`http://localhost:11434`) and arbitrary custom endpoints, which require specialized endpoint mapping (`/api/chat`, `/api/tags`, `/chat/completions`, `/models`).
3. **Deterministic ID Protocol:** The translation pipeline uses a specialized 1:1 ID mapping protocol (`entry_X`) with strict JSON envelope enforcement and retry-with-correction mechanisms tailored to PO file structures.
4. **Full Transparency:** All external HTTP requests are fully disclosed in `readme.txt`, the admin settings UI, and the WordPress Core Privacy Policy guide.

---

# Security Audit

1. **CSRF Protection:** Every AJAX request is protected by `check_ajax_referer('ewaas_nonce', 'nonce')` with backward-compatible verification for `ewa_nonce`.
2. **Access Control & Permissions:** Every administrative endpoint enforces `current_user_can('manage_options')` before processing any payload.
3. **Input Sanitization:** All request parameters are unslashed and sanitized (`wp_unslash()`, `sanitize_text_field()`, `sanitize_key()`, `sanitize_url()`, `esc_url_raw()`, `absint()`).
4. **Output Escaping:** Dynamic data in views and notices is strictly escaped using `esc_html()`, `esc_html__()`, `esc_attr()`, and `wp_kses_post()`.
5. **Path Traversal & SSRF Defense:**
   * PO file paths are validated against allowed canonical WordPress language roots (`WP_CONTENT_DIR/languages`, plugin/theme locales) using `realpath()` and normalized directory boundary checks.
   * Endpoint URLs are strictly validated and configurable only by administrators with `manage_options`.
6. **Secret Protection:** API keys are never stored in client-side JavaScript, never echoed in raw HTML input `value` attributes, and masked in administrative forms.
7. **No Remote Code Execution:** Zero instances of `eval()`, `create_function()`, dynamic remote includes, or obfuscated payloads (`base64_decode`).

---

# Compatibility

* **WordPress:** 6.0 to 6.8+ (Tested up to: 6.8)
* **PHP:** 7.4 to 8.3+ (Fully tested on PHP 7.4, 8.0, 8.1, 8.2, 8.3)
* **Loco Translate:** 2.6.x+ (Fully compatible with current stable releases)
* **Multisite:** Clean per-site option removal in `uninstall.php` across multisite networks.

---

# Testing Performed

1. **PHP Syntax Validation (`php -l`):**
   * All 18 PHP files in the repository checked.
   * Result: **0 syntax errors, 18 passed**.
2. **Automated Pipeline Test Suite (`tests/test-pipeline.php`):**
   * 32 unit and integration assertions covering `msgctxt` context separation, printf placeholder extraction/validation, variable template protection, HTML entity integrity, plural form calculations, ID mapping, and atomic PO writing.
   * Result: **32 passed, 0 failed**.
3. **Legacy String Search:**
   * Scanned repository for outdated slugs and unprefixed identifiers. Clean.
4. **Packaged ZIP Verification:**
   * Archive generated via `build-zip.py`.
   * Verified POSIX forward slash paths in archive index.
   * Verified single root directory: `ewa-ai-string-assistant-for-loco-translate/`.
   * Verified zero development junk, git files, unit tests, or OS artifacts.
   * Extracted to clean sandbox directory and executed automated activation smoke test.
   * Result: **Clean initialization, 0 errors, 0 warnings**.

---

# Remaining Known Issues

None. All P0 and P1 items from the pre-review have been addressed and validated.

---

# Final Release Checklist

- [x] Unique, trademark-compliant display name: `EWA AI String Assistant for Loco Translate`
- [x] Unique canonical slug: `ewa-ai-string-assistant-for-loco-translate`
- [x] Main file: `ewa-ai-string-assistant-for-loco-translate.php`
- [x] Text domain: `ewa-ai-string-assistant-for-loco-translate`
- [x] Author: `Error Web Agency`
- [x] Contributors: `errorwebagency`
- [x] License: GPL v2 or later
- [x] Guideline 11 compliant admin notices (scoped to `plugins.php` / settings, dismissible)
- [x] Fully documented `== External Services ==` in `readme.txt`
- [x] `wp_add_privacy_policy_content()` integration implemented
- [x] Option B WordPress 7.0 AI Client decision documented
- [x] Nonce verification and `manage_options` capability check on all AJAX endpoints
- [x] POT file updated with 59 localized strings
- [x] Production ZIP verified and saved to `ewa-ai-string-assistant-for-loco-translate.zip`
