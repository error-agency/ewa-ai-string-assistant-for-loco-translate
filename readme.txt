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

EWA AI Translator for Loco Translate adds AI-assisted translation tools to the Loco Translate editor for WordPress plugin and theme translation files.

The plugin works with untranslated strings in PO files, processes translations in batches, preserves existing translations, supports plural forms, and automatically updates translation files and compiles MO files.

Loco Translate must be installed and active.

An AI provider must be configured before AI-assisted translation can be used.

= Supported providers =

* OpenRouter (cloud API aggregator)
* Ollama (local or remote self-hosted LLM)
* Custom OpenAI-compatible API endpoints

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
* Site URL (HTTP-Referer header) and Site Title (X-Title header) as requested by OpenRouter API conventions

Service documentation and policies:
* Website: https://openrouter.ai/
* Terms of Service: https://openrouter.ai/terms
* Privacy Policy: https://openrouter.ai/privacy

= Ollama =

When Ollama is configured, HTTP requests are sent to the endpoint specified by the administrator (defaulting to http://localhost:11434).

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

1. Install and activate the Loco Translate plugin.
2. Install and activate EWA AI Translator for Loco Translate.
3. Navigate to Settings → EWA AI Translator.
4. Select your preferred AI provider (OpenRouter, Ollama, or Custom Endpoint) and enter your credentials.
5. Open any plugin or theme translation file in Loco Translate.
6. Click the "🤖 AI Translate" button in the editor toolbar.

== Frequently Asked Questions ==

= Does this plugin require Loco Translate? =

Yes. This plugin extends Loco Translate and requires the `loco-translate` plugin to be installed and active.

= Does the plugin send data to third parties automatically? =

No data is sent automatically in the background. Data is sent to third-party AI services only when an administrator configures a provider and initiates an operation such as loading models, testing a connection, or running translation.

= Is an API key required? =

OpenRouter and custom endpoints generally require an API key. A locally running Ollama instance operates without an API key.

= Are AI-generated translations guaranteed to be accurate? =

AI-generated translations provide a baseline and should be reviewed for context and accuracy before being deployed into production.

== Screenshots ==

1. Settings page to configure AI provider (OpenRouter, Ollama, Custom Endpoint), API key, model selection, temperature, and batch size.
2. AI Translate integration inside the Loco Translate PO file editor toolbar with real-time translation progress.

== Changelog ==

= 1.6.1 =
* Implemented full msgctxt context support and JSON-encoded deduplication keys.
* Implemented deterministic ID-based AI request/response protocol (`entry_154`) and envelope validation.
* Added Translation_Validator for strict tokenization of printf formats, variable templates, HTML tags/attributes, and entities.
* Added plural form count validation against nplurals and Strategy B partial plural regeneration.
* Structured translation job state in ewa_job_{job_id} transients with idempotency request protection.
* Stopped fuzzy flag misuse on API/network batch failures.
* Implemented machine-readable WP_Error classification with early abort on permanent API errors (401, 403, 404).
* Implemented atomic PO file saving with permission preservation and MO compilation error reporting.
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

== Credits ==

Developed by Error Web Agency (EWA).

Lead Developer: K2D.

This plugin integrates with Loco Translate, which is an independent project and is not developed or maintained by Error Web Agency (EWA).
