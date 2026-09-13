# EWA AI Translator for Loco Translate

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net/)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](LICENSE)
[![Version](https://img.shields.io/badge/Version-1.6.1-orange.svg)](CHANGELOG.md)

**EWA AI Translator for Loco Translate** is a powerful, production-grade WordPress add-on for [Loco Translate](https://wordpress.org/plugins/loco-translate/). It introduces seamless AI-assisted translation directly into the Loco Translate editor using **OpenRouter**, **Ollama**, or any custom **OpenAI-compatible** endpoint.

Developed by **[Error Web Agency (EWA)](https://error.bg/)**.

---

## 🚀 Key Features

* **Direct Loco Translate Integration**  
  Injects a clean **"🤖 AI Translate"** toolbar button and interactive modal panel directly into plugin and theme `.po` editor pages.
* **Deterministic ID-Based AI Protocol**  
  Eliminates alignment drift and missing translations by assigning unique, stable IDs (`entry_X`) to each source string and validating the structured JSON envelope response from the AI.
* **Strict Token & Placeholder Validation Engine**  
  Protects your site from broken code and syntax errors. Validates `printf` specifiers (`%s`, `%d`, `%1$s`, `%%`), variable placeholders (`{name}`, `{{name}}`), HTML tags, attributes (`href`, `src`), and HTML entities (`&nbsp;`, `&hellip;`).
* **Full Context Support (`msgctxt`)**  
  Preserves gettext context disambiguation across the entire parsing and translation pipeline, preventing context collision.
* **Native Plural Form Handling**  
  Automatically requests and translates the exact number of plural forms (`nplurals`) required by the target language. Safely regenerates partial or incomplete plural entries.
* **Smart In-Memory Filtering & Cost Optimization**  
  Skips non-translatable items (numbers, URLs, pure placeholders, HTML tags) in memory without calling the AI API, saving API costs and execution time. Deduplicates identical source strings within the same batch.
* **Atomic File Saving & Automatic `.mo` Compilation**  
  Writes `.po` files atomically using temporary files and filesystem locks via `WP_Filesystem`, preserving file permissions. Automatically compiles binary `.mo` files using WordPress core POMO classes.
* **Multiple AI Providers**:
  * **OpenRouter**: Access state-of-the-art models (Anthropic Claude 3.5 Sonnet, OpenAI GPT-4o, DeepSeek V3, Meta Llama 3.3, Google Gemini, and hundreds more).
  * **Ollama**: Connect to local or remote self-hosted LLMs for 100% free, private, offline translations.
  * **Custom Endpoints**: Connect to any custom OpenAI-compatible API endpoint.
* **Real-Time Progress & Token Metrics**  
  Monitor batch execution, elapsed time, strings translated, and prompt/completion token usage in real time.
* **Privacy & Security First**  
  Direct client-to-provider communication. No intermediary servers, no tracking, and zero code execution. API keys are stored securely and masked in the admin UI.

---

## 📋 Requirements

* **WordPress**: 6.0 or higher
* **PHP**: 7.4 or higher
* **Loco Translate**: Active installation of [loco-translate](https://wordpress.org/plugins/loco-translate/)
* **AI Provider Credentials**:
  * An [OpenRouter](https://openrouter.ai/) API key, or
  * A running [Ollama](https://ollama.com/) instance (local or remote), or
  * Any OpenAI-compatible endpoint credentials

---

## 📦 Installation

### From WordPress Admin
1. Go to **Plugins → Add New → Upload Plugin**.
2. Upload the `ewa-ai-translator-for-loco-translate.zip` file.
3. Click **Install Now** and then **Activate**.

### Manual Installation
1. Upload the `ewa-ai-translator-for-loco-translate` directory to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.

---

## ⚙️ Configuration

1. Navigate to **Settings → EWA AI Translator** in the WordPress admin panel.
2. Choose your preferred AI Provider:
   * **OpenRouter**: Enter your API key and click **Load Models** to select from available models.
   * **Ollama**: Enter your Ollama endpoint URL (default: `http://localhost:11434`) and select your installed model.
   * **Custom Endpoint**: Enter your endpoint URL, model name, and optional API key.
3. Configure translation parameters:
   * **Temperature**: Adjust creativity (recommended: `0.1` - `0.3` for accurate translations).
   * **Batch Size**: Number of strings per request (recommended: `10` - `25`).
   * **Character Limit**: Maximum source characters per batch (default: `3000`).
4. Click **Save Settings**.

---

## 💡 How to Use

1. Navigate to **Loco Translate → Plugins** or **Loco Translate → Themes**.
2. Select the plugin or theme you want to translate and click on the target language.
3. In the PO file editor toolbar, click the **🤖 AI Translate** button.
4. Review the translation settings and click **Start Translation**.
5. Watch real-time translation progress, batch status, and token usage.
6. Once complete, your `.po` file is saved and the `.mo` file is automatically compiled and ready to use.

---

## 🔒 Security & WordPress Coding Standards

* **Plugin Check (PCP) Compliant**: Strict adherence to official WordPress.org Plugin Directory guidelines.
* **WP_Filesystem Operations**: All disk modifications use the official WordPress filesystem API.
* **Sanitization & Escaping**: All inputs are sanitized using `sanitize_text_field( wp_unslash( ... ) )` and outputs are properly escaped.
* **CSRF & Nonce Protection**: All AJAX endpoints require `check_ajax_referer()` verification and `manage_options` capability.
* **Canonical Path Validation**: Path traversal protection validates that PO files reside within standard WordPress language directories.
* **Strict Licensing**: Distributed under GNU GPL v2 or later with zero trialware, paywalls, or feature locks.

---

## 🌐 Third-Party Services & Data Disclosures

This plugin connects to external AI services only when an administrator configures a provider and initiates an action (such as loading models, testing a connection, or running translation):

* **OpenRouter**: Transmits source strings, target language, system prompt, model ID, API key, site URL (`HTTP-Referer`), and site title (`X-Title`). See [OpenRouter Terms](https://openrouter.ai/terms) and [Privacy Policy](https://openrouter.ai/privacy).
* **Ollama**: Transmits translation requests to the configured local or remote Ollama server. See [Ollama Privacy](https://ollama.com/privacy).
* **Custom Endpoints**: Transmits translation requests directly to the user-specified endpoint.

---

## 🧪 Automated Testing

The repository includes a dedicated automated test suite for the translation pipeline:

```bash
# Run the test suite
php tests/test-pipeline.php
```

All 32 pipeline assertions cover:
* Deduplication and context keys
* Printf placeholder extraction and validation
* Variable templates and HTML tag integrity
* Plural form handling and Strategy B regeneration
* PO atomic saving and parsing

---

## 📄 License & Credits

* **License**: [GNU General Public License v2 or later](LICENSE)
* **Author**: [Error Web Agency (EWA)](https://error.bg/)
* **Lead Developer**: K2D
* **Repository**: [github.com/error-agency/ewa-ai-translator-for-loco-translate](https://github.com/error-agency/ewa-ai-translator-for-loco-translate)

*Disclaimer: This plugin is an independent third-party add-on and is not affiliated with or endorsed by Loco Translate or Tim Whitlock.*
