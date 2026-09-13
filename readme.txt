=== EWA AI Translator for Loco Translate ===
Contributors: errorwebagency
Tags: translation, ai, localization, gettext, loco translate
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 1.6.1
Requires PHP: 7.4
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-assisted translation for Loco Translate using OpenRouter, Ollama, or a custom OpenAI-compatible endpoint.

== Description ==

EWA AI Translator for Loco Translate integrates state-of-the-art AI translation directly into the Loco Translate editor for WordPress plugins and themes.

Translate untranslated Gettext PO strings in seconds using top AI models via OpenRouter (Anthropic Claude, OpenAI GPT, Google Gemini, DeepSeek, Meta Llama), a locally hosted or remote Ollama instance, or any custom OpenAI-compatible API endpoint.

= Key Features =

* **Direct Loco Translate Integration**: Seamlessly adds an "🤖 AI Translate" action panel to the Loco Translate PO file editor toolbar.
* **Deterministic ID-Based AI Protocol**: Uses stable entry IDs (`entry_X`) and strict JSON envelope validation to guarantee 1:1 translation mapping without drift or alignment offset.
* **Strict Token & Placeholder Validation**: Validates printf specifiers (`%s`, `%d`, `%1$s`, `%%`), variable templates (`{var}`, `{{var}}`), HTML tags, attributes (`href`, `src`), and HTML entities to prevent code corruption in translations.
* **Full Context Support (`msgctxt`)**: Preserves gettext context disambiguation across the entire parsing and translation pipeline.
* **Native Plural Forms**: Automatically requests and maps the exact number of plural forms (`nplurals`) defined for the target locale. Partial plural forms are safely detected and completed.
* **Smart Filtering & Cost Optimization**: Automatically skips non-translatable strings (numbers, URLs, pure placeholders, HTML tags) in memory without making redundant API requests.
* **Atomic File Saving & Compilation**: Writes PO files atomically using temporary files and filesystem locks, preserving file permissions and automatically compiling binary `.mo` files via WordPress POMO classes.
* **Multiple AI Providers**:
  * **OpenRouter**: Access hundreds of cutting-edge models (e.g. Claude 3.5 Sonnet, GPT-4o, DeepSeek V3, Llama 3.3) with your own API key.
  * **Ollama**: Connect to local or remote self-hosted LLMs with zero API costs.
  * **Custom Endpoints**: Connect to any OpenAI-compatible API endpoint.
* **Real-Time Progress & Analytics**: Monitor batch progress, elapsed time, strings translated, and token usage in real time.
* **Privacy First**: Direct connection from your WordPress server to your chosen AI provider. No intermediary proxy servers or data harvesting.
* **100% Free & Open Source**: No paywalls, no trial limits, no feature restrictions.

= Important Disclaimer & Recommendations =

* **Backup First**: Always create a complete backup of your `.po` and `.mo` files before performing automated AI translations.
* **Review Translations**: AI-generated translations are automated suggestions. Site owners are advised to review translated strings for context, grammar, and tone before deploying them on public or production sites.
* **API Usage & Costs**: If using paid third-party APIs (such as OpenRouter), monitor your usage and set spending limits on your provider dashboard. Developers are not responsible for third-party billing charges.

== Third-Party Services ==

This plugin can connect to external AI services when an administrator configures a provider and initiates an operation that requires the provider, including loading models, testing a connection, or translating strings.

Translation content is sent only to the provider selected and configured by the administrator.

= OpenRouter =

When OpenRouter is configured, HTTP requests required for AI translation are sent to OpenRouter.

Data sent may include:
* Source strings and plural forms to be translated
* Target language name and locale information
* System prompt and translation instructions
* Selected model identifier and temperature settings
* API key (transmitted via standard HTTP Authorization Bearer header)
* Site URL (`HTTP-Referer` header) and Site Title (`X-Title` header) as requested by OpenRouter API conventions

Service documentation and policies:
* Website: https://openrouter.ai/
* Terms of Service: https://openrouter.ai/terms
* Privacy Policy: https://openrouter.ai/privacy

= Ollama =

When Ollama is configured, HTTP requests are sent to the endpoint specified by the administrator (defaulting to `http://localhost:11434`).

Data sent may include source strings, plural forms, target language information, system prompt, selected model name, and model options.

A locally hosted Ollama instance processes data on the local server environment. A remote Ollama endpoint transmits data to that remote server.

Service documentation and policies:
* Website: https://ollama.com/
* Privacy Policy: https://ollama.com/privacy

= Custom OpenAI-compatible endpoints =

When a custom endpoint is configured, requests are sent to the endpoint specified by the administrator.

Data sent includes source strings, target language, prompt parameters, model identifier, and configured API credentials.

Administrators are responsible for reviewing the terms and privacy policy of their chosen custom endpoint provider.

== Installation ==

1. Ensure the **Loco Translate** plugin is installed and activated.
2. Upload the `ewa-ai-translator-for-loco-translate` folder to your `/wp-content/plugins/` directory, or install the ZIP file via **Plugins → Add New → Upload Plugin**.
3. Activate the plugin through the **Plugins** menu in WordPress.
4. Navigate to **Settings → EWA AI Translator** in the WordPress admin menu.
5. Select your AI provider (**OpenRouter**, **Ollama**, or **Custom Endpoint**) and enter your API credentials or endpoint URL.
6. Select your preferred model and adjust translation parameters (temperature, batch size).
7. Go to **Loco Translate → Plugins** or **Loco Translate → Themes**, and open any PO translation file in the editor.
8. Click the **🤖 AI Translate** button in the editor toolbar, review the settings, and click **Start Translation**.

== Frequently Asked Questions ==

= Does this plugin require Loco Translate? =

Yes. EWA AI Translator for Loco Translate is an add-on that specifically extends Loco Translate. Loco Translate must be installed and active.

= Is an API key required to use the plugin? =

An API key is required when using OpenRouter or most commercial OpenAI-compatible providers. However, if you use a locally hosted Ollama instance, no API key is required and all translations run locally and free of charge.

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

1. Settings page to configure AI provider (OpenRouter, Ollama, Custom Endpoint), API credentials, model selection, temperature, and batch limits.
2. AI Translate toolbar button and interactive modal panel within the Loco Translate PO file editor.

== Changelog ==

= 1.6.1 =
* Implemented full `msgctxt` context support and JSON-encoded deduplication keys across the entire pipeline.
* Implemented deterministic ID-based AI request/response protocol (`entry_X`) and strict JSON envelope validation.
* Added `Translation_Validator` class for strict tokenization and validation of printf formats, variable templates, HTML tags/attributes, and HTML entities.
* Added plural form count validation against `nplurals` and Strategy B partial plural regeneration.
* Structured translation job state in `ewa_job_{job_id}` transients with idempotency request protection.
* Stopped fuzzy flag misuse on API/network batch failures.
* Implemented machine-readable `WP_Error` classification with early abort on permanent API errors (401, 403, 404).
* Implemented atomic PO file saving with permission preservation and MO compilation error reporting via `WP_Filesystem`.
* Hardened canonical path validation against allowed WordPress language directories.
* Masked API key values in HTML DOM and added clear API key functionality.
* Added CLI automated test suite covering 32 pipeline assertions.

= 1.6.0 =
* Prepared the plugin for WordPress.org distribution.
* Rebranded the plugin to EWA AI Translator for Loco Translate by Error Web Agency (EWA).
* Standardized canonical slug (`ewa-ai-translator-for-loco-translate`) and text domain.
* Standardized PHP requirement (7.4+) and WordPress requirement (6.0+).
* Refactored PHP codebase into the `ErrorWebAgency\LocoAITranslator` namespace with `ewa_` global prefixing.
* Added automatic backward-compatible settings migration to `ewa_settings`.
* Updated third-party service disclosures and WordPress.org metadata.

= 1.5.3 =
* Verified compliance of all newly added methods and variables with WordPress Coding Standards.
* Improved security escaping and sanitization routines across all admin inputs.

= 1.5.2 =
* Deduplicated identical untranslated strings in memory to minimize AI token usage.
* Added automatic memory-bypass for non-translatable strings (numbers, URLs, pure placeholders).
* Implemented dynamic character-based batching (up to 3,000 characters) for optimal payload sizes.
* Shortened system prompt for provider-level prompt caching compatibility.

= 1.5.1 =
* Added full support for translating plural forms (`msgid_plural`).
* Added transient-based caching of parsed PO entries during active translation jobs.

= 1.5.0 =
* Fixed translation loop offset calculation to always slice untranslated entries from index 0.
* Added token usage tracking surfacing prompt and completion token counts.
* Added per-batch logging and live summary strip (time, strings, tokens).

= 1.1.0 =
* Improved Loco Translate editor toolbar panel injection.
* Ensured full PHP 7.4 compatibility across all string manipulation functions.

= 1.0.0 =
* Initial release of the AI translation add-on for Loco Translate.

== Upgrade Notice ==

= 1.6.1 =
Upgrade to version 1.6.1 for enhanced translation validation, deterministic AI protocol, full msgctxt context support, and atomic WP_Filesystem operations.

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

Developed by Error Web Agency (EWA).

Lead Developer: K2D.

Development Repository: https://github.com/error-agency/ewa-ai-translator-for-loco-translate

This plugin integrates with Loco Translate, which is an independent project and is not developed or maintained by Error Web Agency (EWA).
