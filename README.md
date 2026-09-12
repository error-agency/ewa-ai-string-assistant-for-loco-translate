# EWA AI Translator for Loco Translate

**EWA AI Translator for Loco Translate** is an add-on for the [Loco Translate](https://wordpress.org/plugins/loco-translate/) WordPress plugin. It adds AI-assisted translation capabilities directly into the Loco Translate editor using OpenRouter, Ollama, or custom OpenAI-compatible endpoints.

## Features

- **Loco Translate Integration**: Injects an "🤖 AI Translate" toolbar panel into plugin and theme PO file editor pages.
- **Provider Support**:
  - **OpenRouter**: Access cloud AI models via API key.
  - **Ollama**: Connect to local or remote self-hosted Ollama instances.
  - **Custom Endpoints**: Connect to any OpenAI-compatible API endpoint.
- **Character-Based Batching**: Groups strings into optimized character batches to manage API payload sizes.
- **Retry Logic & Error Handling**: Exponential backoff retries for transient API failures.
- **Plural & Context Support**: Preserves PO file structure, msgid_plural forms, HTML tags, and placeholders.
- **Automatic Saving**: Saves updated translations directly to `.po` files and compiles `.mo` files using WordPress POMO classes.
- **Progress Tracking & Token Usage**: Displays real-time translation progress, string ticker, and token usage metrics.

## Requirements

- **WordPress**: 6.0 or higher
- **PHP**: 7.4 or higher
- **Loco Translate**: Active installation of `loco-translate`
- **AI Provider Account**: OpenRouter API key, running Ollama instance, or OpenAI-compatible endpoint credentials

## Installation

1. Upload the `ewa-ai-translator-for-loco-translate` folder to your `/wp-content/plugins/` directory.
2. Activate the plugin through **Plugins** in WordPress.
3. Navigate to **Settings → EWA AI Translator** to configure your provider and credentials.
4. Open a translation file in **Loco Translate** (Plugins or Themes).
5. Click **🤖 AI Translate** in the editor panel to initiate translation.

## Third-Party Services & Data Disclosures

This plugin connects to external AI services only when an administrator configures a provider and triggers an action (such as loading models, testing a connection, or translating strings):

- **OpenRouter**: Transmits source strings, target locale, prompt, model ID, API key, site URL (`HTTP-Referer`), and site title (`X-Title`). See [OpenRouter Terms](https://openrouter.ai/terms) and [Privacy Policy](https://openrouter.ai/privacy).
- **Ollama**: Transmits translation data to the configured Ollama endpoint (local or remote). See [Ollama Privacy](https://ollama.com/privacy).
- **Custom Endpoints**: Transmits data to the endpoint specified by the administrator.

## Development Repository

- GitHub Repository: [error-agency/ewa-ai-translator-for-loco-translate](https://github.com/error-agency/ewa-ai-translator-for-loco-translate)

## License

Released under the terms of the [GNU General Public License v2 or later](LICENSE).

## Credits

- **Developer / Publisher**: [Error Web Agency (EWA)](https://error.bg/)
- **Lead Developer**: K2D
- **Integration Target**: [Loco Translate](https://wordpress.org/plugins/loco-translate/) (separate project, not affiliated with Error Web Agency (EWA))
