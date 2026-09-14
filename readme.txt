=== EWA AI String Assistant for Loco Translate ===
Contributors: errorwebagency
Tags: translation, ai, localization, gettext, loco translate
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 1.8.0
Requires PHP: 7.4
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-assisted string translation for Loco Translate using native WordPress AI Client (WordPress 7.0+), OpenRouter, Ollama, or custom OpenAI-compatible endpoints.

== Description ==

EWA AI String Assistant for Loco Translate integrates state-of-the-art AI translation directly into the Loco Translate editor for WordPress plugins and themes.

On WordPress 7.0+, it seamlessly leverages the official, native **WordPress AI Client** (`wp_ai_client_prompt`) and connectors configured centrally in WordPress Core, requiring zero plugin-level API keys. For WordPress 6.x or specialized setups, it provides an **Advanced Direct Connection** mode supporting OpenRouter (Anthropic Claude, OpenAI GPT, Google Gemini, DeepSeek, Meta Llama), locally hosted or remote Ollama instances, or any custom OpenAI-compatible API endpoint.

= Key Features =

* **Native WordPress AI Client Support (WordPress 7.0+)**: Seamlessly uses the AI provider and credentials configured centrally in WordPress Core (**Settings → Connectors**) with zero plugin-level API keys required.
* **Advanced Direct Connection Mode**: Full support for OpenRouter, local or remote self-hosted Ollama instances, and custom OpenAI-compatible endpoints with idempotent settings retention.
* **Direct Loco Translate Integration**: Adds an "🤖 AI Translate" action panel to the Loco Translate PO file editor toolbar.
* **Deterministic ID-Based AI Protocol**: Uses stable entry IDs (`entry_X`) and strict JSON envelope validation to guarantee 1:1 translation mapping without drift or alignment offset.
* **Strict Token & Placeholder Validation**: Validates printf specifiers (`%s`, `%d`, `%1$s`, `%%`), variable templates (`{var}`, `{{var}}`), HTML tags, attributes (`href`, `src`), and HTML entities to prevent code corruption in translations.
* **Full Context Support (`msgctxt`)**: Preserves gettext context disambiguation across the entire parsing and translation pipeline.
* **Native Plural Forms**: Automatically requests and maps the exact number of plural forms (`nplurals`) defined for the target locale. Partial plural forms are safely detected and completed.
* **Smart Filtering & Cost Optimization**: Automatically skips non-translatable strings (numbers, URLs, pure placeholders, HTML tags) in memory without making redundant API requests.
* **Atomic File Saving & Compilation**: Writes PO files atomically using temporary files and filesystem locks, preserving file permissions and automatically compiling binary `.mo` files via WordPress POMO classes.
* **Real-Time Progress & Analytics**: Monitor batch progress, elapsed time, strings translated, and token usage in real time.
* **Direct & Secure Communication**: In WordPress AI Client mode, calls are dispatched via WordPress Core connectors. In Direct mode, calls are sent directly from your server to the configured endpoint without any intermediary proxy servers.
* **100% Free & Open Source**: No paywalls, no trial limits, no feature restrictions.

= Important Disclaimer & Recommendations =

* **Loco Translate Disclaimer**: Loco Translate is an independent project by Tim Whitlock. This plugin is an independent third-party add-on and is not affiliated with, sponsored, or endorsed by Loco Translate or its authors.
* **Backup First**: Always create a complete backup of your `.po` and `.mo` files before performing automated AI translations.
* **Review Translations**: AI-generated translations are automated suggestions. Site owners are advised to review translated strings for context, grammar, and tone before deploying them on public or production sites.
* **API Usage & Costs**: If using paid third-party APIs (such as OpenRouter or commercial providers), monitor your usage and set spending limits on your provider dashboard. Developers are not responsible for third-party billing charges.

== External Services ==

This plugin can connect to external AI services when an administrator configures a provider and explicitly initiates an operation that requires the provider, including translating strings, testing a connection, or loading available models.

Translation content is sent only to the provider selected and configured by the administrator (or configured centrally in WordPress Core Connectors). No data is ever transmitted automatically or in the background.

= 1. WordPress AI Client (WordPress 7.0+) =

When the site is running WordPress 7.0 or higher and WordPress AI Client mode is active (default for new installations), translation requests are delegated directly to the official WordPress AI Client API (`wp_ai_client_prompt()`).

* **Purpose**: Translating Gettext PO strings using the central AI provider configured in WordPress Core (**Settings → Connectors**).
* **Data Transmitted**: Source strings, plural variations, Gettext context (`msgctxt`), target language name, translation instructions, and optional model preferences.
* **Routing & Authentication**: WordPress Core handles authentication, credential storage, and transport routing directly to the administrator's chosen AI provider. No credentials are stored or handled by this plugin in WordPress AI Client mode.
* **When Data is Transmitted**: Exclusively upon explicit administrator action (clicking "AI Translate" in Loco Translate editor or "Check AI Availability" in settings).
* **Service Policies**: Refer to the privacy policy and terms of service of the specific AI provider configured in your WordPress **Settings → Connectors** screen.

= 2. OpenRouter (Direct Connection Mode) =

When OpenRouter is configured, HTTP requests required for AI translation are sent to the OpenRouter API.

* **Purpose**: Translating Gettext PO strings using cloud-hosted LLM models (e.g. Anthropic Claude, OpenAI GPT, Google Gemini, DeepSeek, Meta Llama) and querying the list of available models.
* **Data Transmitted**:
  * Source strings and plural variations to be translated
  * Translation context (`msgctxt`) and target language name
  * System translation prompt and instructions
  * Selected model identifier and temperature settings
  * Administrator-configured API key (transmitted via standard HTTP Authorization Bearer header)
  * Site URL (`HTTP-Referer` header) and Site Title (`X-Title` header) as requested by OpenRouter API conventions for usage attribution
* **Downstream Model Provider Routing**: OpenRouter operates as an AI API gateway and aggregator. When using OpenRouter, your translation prompts, source strings, and context are routed by OpenRouter to the specific AI model provider (such as Anthropic, OpenAI, Google, Meta, or Mistral) selected in your settings. Each upstream model provider processes data in accordance with their respective data and privacy policies as described in the OpenRouter Privacy Policy.
* **When Data is Transmitted**: Exclusively when an authenticated administrator clicks "AI Translate" in the Loco Translate editor, "Test Connection", or "Load Models" on the settings screen.
* **Provider**: OpenRouter, Inc.
* **Service Website**: https://openrouter.ai/
* **Terms of Service**: https://openrouter.ai/terms
* **Privacy Policy**: https://openrouter.ai/privacy

= 3. Ollama (Direct Connection Mode) =

When Ollama is selected, HTTP requests are sent to the endpoint specified by the administrator (defaulting to `http://localhost:11434`).

* **Purpose**: Translating strings using self-hosted open-source language models.
* **Data Transmitted**: Source strings, plural forms, Gettext context, target language, system prompt, and model options.
* **Local vs Remote Hosting**:
  * When using a locally hosted Ollama instance (`http://localhost:11434`), all data processing occurs entirely within the local server environment. No data is transmitted to any third-party cloud service.
  * If the administrator enters a remote Ollama server URL (e.g. on a private LAN or VPS), data is transmitted to that specific host configured by the administrator.
* **When Data is Transmitted**: Only upon explicit administrator action (running a translation batch, testing connection, or loading models).
* **Provider**: Self-hosted application by Ollama.
* **Service Website**: https://ollama.com/
* **Terms of Service**: https://ollama.com/terms
* **Privacy Policy**: https://ollama.com/privacy

= 4. OpenAI API (Direct Connection Mode) =

OpenAI API is an optional external service. It is used only when an administrator explicitly selects the OpenAI preset (https://api.openai.com/v1) or configures an OpenAI endpoint and provides an OpenAI API key.

* **Purpose**: Translating Gettext PO strings using official OpenAI models (such as GPT-4o, GPT-4o-mini) and querying the list of available OpenAI models.
* **Data Transmitted**: Source strings, plural variations, Gettext context (`msgctxt`), target language name, system translation prompt and instructions, selected model identifier, and temperature settings. The administrator's OpenAI API key is transmitted via standard HTTP Authorization Bearer header for direct authentication with OpenAI servers.
* **When Data is Transmitted**: Exclusively upon explicit administrator action (clicking "AI Translate" in Loco Translate, "Test Connection", or "Load Models" in the settings screen). No background requests, periodic polling, or automatic data collection occur.
* **Direct Communication**: All requests are dispatched directly from your WordPress server to OpenAI's official API servers. Error Web Agency does not operate any intermediary proxy servers and never intercepts, logs, or stores your API keys or translation data.
* **Provider**: OpenAI, Inc. / OpenAI Ireland Ltd.
* **Service Website**: https://openai.com/
* **Services Agreement**: https://openai.com/policies/services-agreement/
* **Service Terms**: https://openai.com/policies/service-terms/
* **Privacy Policy**: https://openai.com/policies/privacy-policy/
* **Enterprise Privacy & Data Handling**: https://openai.com/enterprise-privacy/

= 5. Custom OpenAI-Compatible Endpoints (Direct Connection Mode) =

When a custom endpoint is configured, requests are sent directly from your WordPress server to the endpoint URL specified by the administrator.

* **Purpose**: Allowing site administrators to connect to an administrator-configured OpenAI-compatible API service (e.g. self-hosted local proxies, corporate gateways, or other compliant AI providers).
* **Data Transmitted**: Source strings, plural forms, Gettext context (`msgctxt`), target language, system prompt parameters, model identifier, and configured API credentials.
* **When Data is Transmitted**: Only when an administrator explicitly initiates a translation batch, tests the connection, or queries the model list.
* **Provider & Privacy Policy**: Because the endpoint is configured by the site administrator, no uniform third-party policy applies. Administrators are responsible for reviewing the terms of service, privacy policy, and data handling practices of their selected endpoint provider. Error Web Agency has no access to or control over custom endpoints.

== Installation ==

1. Ensure the **Loco Translate** plugin is installed and activated.
2. Upload the `ewa-ai-string-assistant-for-loco-translate` folder to your `/wp-content/plugins/` directory, or install the ZIP file via **Plugins → Add New → Upload Plugin**.
3. Activate the plugin through the **Plugins** menu in WordPress.
4. Navigate to **Settings → EWA AI String Assistant** in the WordPress admin menu.
5. Choose your AI Connection Mode:
   * **WordPress AI Client (Recommended)**: Available on WordPress 7.0+. Uses credentials and connectors configured in **Settings → Connectors**.
   * **Advanced Direct Connection**: Configure OpenRouter, Ollama, or a custom OpenAI-compatible endpoint directly.
6. Go to **Loco Translate → Plugins** or **Loco Translate → Themes**, and open any PO translation file in the editor.
7. Click the **🤖 AI Translate** button in the editor toolbar, review the settings, and click **Start Translation**.

== Frequently Asked Questions ==

= Does this plugin require Loco Translate? =

Yes. EWA AI String Assistant for Loco Translate is an add-on that specifically extends Loco Translate. Loco Translate must be installed and active.

= Is an API key required to use the plugin? =

On WordPress 7.0+ with the native WordPress AI Client mode active, no plugin-level API key is required — credentials and connectors are managed centrally by WordPress Core (**Settings → Connectors**). In Direct Connection mode, an API key is required for OpenRouter or commercial OpenAI endpoints, but local Ollama instances require no API keys.

= Does this plugin send data to third parties automatically? =

No. No data is ever sent automatically or in the background. Data is sent to the configured AI provider only when an administrator explicitly initiates an action, such as testing the connection or translating strings.

= Does the plugin overwrite existing translations? =

No. By default, the plugin only translates untranslated strings. Existing translated strings in your PO file are preserved intact.

= How does the plugin handle plural forms? =

The plugin parses the PO file header for plural rules (`Plural-Forms: nplurals=...`) and sends both singular and plural source strings to the AI. The AI generates all required plural variations, which are validated against the target language's plural count before being written to the file.

= What happens if an API request fails? =

The plugin includes exponential backoff retry logic for transient errors (such as network hiccups or rate limits). If a permanent error occurs (e.g. 401 Invalid Key or 404 Model Not Found), the job halts cleanly and provides an actionable error message. Failed items are never falsely flagged as fuzzy.

= How are translations saved? =

Translations are written atomically to the `.po` file on your server using `WP_Filesystem` and standard locking mechanisms. The plugin then automatically compiles the binary `.mo` file using WordPress POMO classes, making the translations immediately available to WordPress.

= Can I use this plugin for commercial websites? =

Yes. The plugin is licensed under GPL v2 or later and can be used on any number of personal or commercial websites without restrictions.

= Who is responsible for backups and translation accuracy? =

The site administrator is solely responsible for creating and maintaining backups of translation files prior to using the plugin, and for reviewing generated translations for contextual correctness. The developers and Error Web Agency (EWA) assume no liability for machine translation errors, unintended file overwrites, data loss, or third-party API costs.

== Screenshots ==

1. Settings page with AI Connection Mode selector (WordPress AI Client vs Advanced Direct Connection), live readiness indicator, and direct provider settings.
2. AI Translate toolbar button and interactive modal panel within the Loco Translate PO file editor.

== Changelog ==

= 1.8.0 =
* Integrated native WordPress AI Client (wp_ai_client_prompt) as the recommended and default AI transport for WordPress 7.0+.
* Added centralized AI Connection Mode settings (WordPress AI Client vs Advanced Direct Connection).
* Implemented modular transport architecture (WP_AI_Client_Transport and Direct_AI_Transport under AI_Transport_Interface).
* Added real-time WordPress AI status check and direct link to WordPress Connectors settings.
* Preserved complete backward compatibility for WordPress 6.x and retained existing direct configurations upon upgrade.
* Updated privacy policy declarations and external service disclosures for WordPress Core AI routing.

= 1.7.0 =
* WordPress.org compliance and remediation release.
* Updated plugin name to EWA AI String Assistant for Loco Translate and canonical slug to `ewa-ai-string-assistant-for-loco-translate`.
* Confined administrative dependency notices to relevant screens (`plugins.php` and settings screen) with dismissal capability in strict compliance with Guideline 11.
* Fully namespaced and prefixed all WordPress registrations with 4+ character prefix `ewaas_` (`ewaas_*` AJAX actions, `ewaas-admin` / `ewaas-loco` script and style handles, nonces, and helper functions). Removed all legacy `ewa_` runtime identifiers.
* Implemented automatic one-time database migration from legacy `ewa_settings` to canonical `ewaas_settings` with schema tracking (`ewaas_schema_version`).
* Added comprehensive External Services documentation covering OpenRouter, Ollama, OpenAI API, and Custom OpenAI-compatible endpoints with direct links to official terms and privacy policies.
* Added WordPress Core Privacy Policy guide integration via `wp_add_privacy_policy_content()` disclosing header usage attribution.
* Evaluated WordPress 7.0 AI Client (`wp_ai_client_prompt`) and retained direct provider adapter to preserve WordPress 6.0+ support, OpenRouter, local Ollama, and administrator-defined endpoint workflows.
* Replaced NOWDOC with scanner-friendly array and implode structures in default prompt generation.
* Moved textdomain loading to `init` action hook.

= 1.6.1 =
* Implemented full `msgctxt` context support and JSON-encoded deduplication keys across the entire pipeline.
* Implemented deterministic ID-based AI request/response protocol (`entry_X`) and strict JSON envelope validation.
* Added `Translation_Validator` class for strict tokenization and validation of printf formats, variable templates, HTML tags/attributes, and HTML entities.
* Added plural form count validation against `nplurals` and Strategy B partial plural regeneration.
* Structured translation job state in `ewaas_job_{job_id}` transients with idempotency request protection.
* Stopped fuzzy flag misuse on API/network batch failures.
* Implemented machine-readable `WP_Error` classification with early abort on permanent API errors (401, 403, 404).
* Implemented atomic PO file saving with permission preservation and MO compilation error reporting via `WP_Filesystem`.
* Hardened canonical path validation against allowed WordPress language directories.
* Masked API key values in HTML DOM and added clear API key functionality.
* Added CLI automated test suite covering 32 pipeline assertions.

= 1.6.0 =
* Prepared the plugin for WordPress.org distribution.
* Standardized PHP requirement (7.4+) and WordPress requirement (6.0+).
* Refactored PHP codebase into namespaced architecture with global prefixing.
* Added automatic backward-compatible settings migration to `ewa_settings`.

= 1.0.0 =
* Initial release of the AI translation add-on for Loco Translate.

== Upgrade Notice ==

= 1.8.0 =
Upgrade to version 1.8.0 to use the native WordPress 7.0+ AI Client and Connectors with zero API key configuration, or continue using Advanced Direct Connection mode seamlessly.

= 1.7.0 =
Upgrade to version 1.7.0 for WordPress.org compliance enhancements, improved namespacing, full external service disclosures, and enhanced admin notice handling.

== Terms of Use & Disclaimer ==

This plugin is provided on an "AS IS" and "AS AVAILABLE" basis, without warranties of any kind, whether express, implied, or statutory, including without limitation warranties of merchantability, fitness for a particular purpose, and non-infringement.

Under no circumstances shall Error Web Agency (EWA), its developers, or contributors be held liable for any direct, indirect, incidental, special, consequential, or punitive damages, including but not limited to:
* Loss of data, corrupted translation files, or overwritten .po/.mo files.
* Inaccuracies, errors, offensive content, or hallucinations produced by third-party artificial intelligence models.
* Billing charges, subscription fees, or rate-limit overages incurred with third-party API providers (e.g. OpenRouter, OpenAI, or other providers).
* Business interruption, lost profits, or reputational harm arising out of the use or inability to use this plugin.

By installing and using this plugin, you explicitly acknowledge and agree that:
1. You are solely responsible for creating and maintaining independent backup copies of all translation files prior to running translations.
2. You will independently review and verify all machine-generated translations before using them in a live or production environment.
3. You control your own third-party API credentials, parameters, and spending limits.

== Credits ==

Developed by Error Web Agency.

Lead Developer: K2D.

Development Repository: https://github.com/error-agency/ewa-ai-string-assistant-for-loco-translate

This plugin integrates with Loco Translate, which is an independent project by Tim Whitlock and is not developed, affiliated with, or endorsed by Error Web Agency.
