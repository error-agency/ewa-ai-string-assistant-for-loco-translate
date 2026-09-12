<?php
use ErrorWebAgency\LocoAITranslator\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$settings = Settings::instance()->get();
$provider = $settings['provider'];
?>
<div class="wrap ewa-settings-wrap">
	<h1 class="ewa-page-title">
		<span class="ewa-logo">🤖</span>
		<?php esc_html_e( 'EWA AI Translator for Loco Translate', 'ewa-ai-translator-for-loco-translate' ); ?>
		<span class="ewa-version">v<?php echo esc_html( EWA_VERSION ); ?></span>
	</h1>

	<?php settings_errors( 'ewa_settings_group' ); ?>

	<div class="ewa-layout">

		<!-- ── MAIN SETTINGS ── -->
		<div class="ewa-main-col">
			<form method="post" action="options.php" id="ewa-settings-form">
				<?php settings_fields( 'ewa_settings_group' ); ?>

				<!-- Provider Card -->
				<div class="ewa-card">
					<h2 class="ewa-card-title">⚡ <?php esc_html_e( 'Provider', 'ewa-ai-translator-for-loco-translate' ); ?></h2>

					<div class="ewa-provider-tabs">
						<label class="ewa-provider-tab <?php echo 'openrouter' === $provider ? 'active' : ''; ?>">
							<input type="radio" name="ewa_settings[provider]" value="openrouter"
								<?php checked( $provider, 'openrouter' ); ?>>
							<span class="ewa-provider-icon">🌐</span>
							<strong>OpenRouter</strong>
							<small><?php esc_html_e( 'Cloud API aggregator', 'ewa-ai-translator-for-loco-translate' ); ?></small>
						</label>
						<label class="ewa-provider-tab <?php echo 'ollama' === $provider ? 'active' : ''; ?>">
							<input type="radio" name="ewa_settings[provider]" value="ollama"
								<?php checked( $provider, 'ollama' ); ?>>
							<span class="ewa-provider-icon">🏠</span>
							<strong>Ollama</strong>
							<small><?php esc_html_e( 'Local / self-hosted LLM', 'ewa-ai-translator-for-loco-translate' ); ?></small>
						</label>
						<label class="ewa-provider-tab <?php echo 'custom' === $provider ? 'active' : ''; ?>">
							<input type="radio" name="ewa_settings[provider]" value="custom"
								<?php checked( $provider, 'custom' ); ?>>
							<span class="ewa-provider-icon">🔧</span>
							<strong><?php esc_html_e( 'Custom Endpoint', 'ewa-ai-translator-for-loco-translate' ); ?></strong>
							<small><?php esc_html_e( 'OpenAI-compatible API', 'ewa-ai-translator-for-loco-translate' ); ?></small>
						</label>
					</div>

					<table class="form-table ewa-form-table">
						<tr>
							<th><?php esc_html_e( 'API Endpoint', 'ewa-ai-translator-for-loco-translate' ); ?></th>
							<td>
								<input type="url" name="ewa_settings[api_endpoint]"
									value="<?php echo esc_attr( $settings['api_endpoint'] ); ?>"
									class="regular-text" id="ewa-api-endpoint"
									placeholder="https://openrouter.ai/api/v1">
								<div class="ewa-presets">
									<button type="button" class="button button-small ewa-preset"
										data-value="https://openrouter.ai/api/v1">
										OpenRouter
									</button>
									<button type="button" class="button button-small ewa-preset"
										data-value="http://localhost:11434">
										Ollama local
									</button>
									<button type="button" class="button button-small ewa-preset"
										data-value="https://api.openai.com/v1">
										OpenAI
									</button>
								</div>
							</td>
						</tr>
						<tr class="ewa-row-apikey" <?php echo 'ollama' === $provider ? 'style="display:none"' : ''; ?>>
							<th><?php esc_html_e( 'API Key', 'ewa-ai-translator-for-loco-translate' ); ?></th>
							<td>
								<input type="password" name="ewa_settings[api_key]"
									value=""
									placeholder="<?php echo ! empty( $settings['api_key'] ) ? esc_attr__( 'API key saved — leave empty to keep', 'ewa-ai-translator-for-loco-translate' ) : ''; ?>"
									class="regular-text" autocomplete="new-password">
								<?php if ( ! empty( $settings['api_key'] ) ) : ?>
									<label style="margin-left:10px;">
										<input type="checkbox" name="ewa_settings[clear_api_key]" value="1">
										<?php esc_html_e( 'Clear saved API key', 'ewa-ai-translator-for-loco-translate' ); ?>
									</label>
								<?php endif; ?>
								<p class="description">
									<?php esc_html_e( 'Leave empty if using a local Ollama endpoint without authentication.', 'ewa-ai-translator-for-loco-translate' ); ?>
									<a href="https://openrouter.ai/keys" target="_blank" rel="noopener">
										<?php esc_html_e( 'Get OpenRouter key ↗', 'ewa-ai-translator-for-loco-translate' ); ?>
									</a>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Model Card -->
				<div class="ewa-card">
					<h2 class="ewa-card-title">🧠 <?php esc_html_e( 'Model', 'ewa-ai-translator-for-loco-translate' ); ?></h2>

					<table class="form-table ewa-form-table">
						<tr>
							<th><?php esc_html_e( 'Model ID', 'ewa-ai-translator-for-loco-translate' ); ?></th>
							<td>
								<div class="ewa-model-row">
									<input type="text" name="ewa_settings[model]" id="ewa-model-input"
										value="<?php echo esc_attr( $settings['model'] ); ?>"
										class="regular-text"
										placeholder="openai/gpt-4o-mini">
									<button type="button" id="ewa-fetch-models" class="button">
										<?php esc_html_e( '↻ Load Models', 'ewa-ai-translator-for-loco-translate' ); ?>
									</button>
								</div>
								<select id="ewa-model-select" style="display:none; margin-top:8px; width:100%; max-width:500px;">
									<option value=""><?php esc_html_e( '— choose a model —', 'ewa-ai-translator-for-loco-translate' ); ?></option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Type model ID directly or click Load Models to fetch from provider.', 'ewa-ai-translator-for-loco-translate' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Temperature', 'ewa-ai-translator-for-loco-translate' ); ?></th>
							<td>
								<input type="number" name="ewa_settings[temperature]"
									value="<?php echo esc_attr( $settings['temperature'] ); ?>"
									min="0" max="2" step="0.1" class="small-text">
								<p class="description">
									<?php esc_html_e( '0 = deterministic, 1 = creative. Recommended: 0.1–0.4 for translations.', 'ewa-ai-translator-for-loco-translate' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Batch Size', 'ewa-ai-translator-for-loco-translate' ); ?></th>
							<td>
								<input type="number" name="ewa_settings[batch_size]"
									value="<?php echo esc_attr( $settings['batch_size'] ); ?>"
									min="5" max="100" class="small-text">
								<p class="description">
									<?php esc_html_e( 'Strings per API call. Default: 40. Range: 5–100.', 'ewa-ai-translator-for-loco-translate' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Max Retries', 'ewa-ai-translator-for-loco-translate' ); ?></th>
							<td>
								<input type="number" name="ewa_settings[max_retries]"
									value="<?php echo esc_attr( $settings['max_retries'] ?? 3 ); ?>"
									min="0" max="10" class="small-text">
								<p class="description">
									<?php esc_html_e( 'Number of retries per batch on API failure before skipping. Default: 3.', 'ewa-ai-translator-for-loco-translate' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Translation Behaviour -->
				<div class="ewa-card">
					<h2 class="ewa-card-title">⚙️ <?php esc_html_e( 'Translation Behaviour', 'ewa-ai-translator-for-loco-translate' ); ?></h2>

					<table class="form-table ewa-form-table">
						<tr>
							<th><?php esc_html_e( 'Skip Translated', 'ewa-ai-translator-for-loco-translate' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="ewa_settings[skip_translated]" value="1"
										<?php checked( $settings['skip_translated'], 1 ); ?>>
									<?php esc_html_e( 'Skip strings that already have a translation', 'ewa-ai-translator-for-loco-translate' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'System Prompt', 'ewa-ai-translator-for-loco-translate' ); ?></th>
							<td>
								<textarea name="ewa_settings[system_prompt]" rows="6"
									class="large-text" placeholder="<?php esc_attr_e( 'Leave blank to use the default translation prompt.', 'ewa-ai-translator-for-loco-translate' ); ?>"
								><?php echo esc_textarea( $settings['system_prompt'] ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'Override the default prompt. Use {target_lang} for the language placeholder.', 'ewa-ai-translator-for-loco-translate' ); ?>
								</p>
								<button type="button" id="ewa-show-default-prompt" class="button button-small">
									<?php esc_html_e( 'View default prompt', 'ewa-ai-translator-for-loco-translate' ); ?>
								</button>
								<pre id="ewa-default-prompt-preview" style="display:none; background:#f6f7f7; padding:12px; border-radius:4px; white-space:pre-wrap; font-size:12px;"><?php echo esc_html( Settings::default_system_prompt( '{target_lang}' ) ); ?></pre>
							</td>
						</tr>
					</table>
				</div>

				<div class="ewa-actions">
					<?php submit_button( __( 'Save Settings', 'ewa-ai-translator-for-loco-translate' ), 'primary large', 'submit', false ); ?>
					<button type="button" id="ewa-test-connection" class="button button-large">
						🔌 <?php esc_html_e( 'Test Connection', 'ewa-ai-translator-for-loco-translate' ); ?>
					</button>
					<span id="ewa-test-result" class="ewa-test-result"></span>
				</div>

			</form>
		</div>

		<!-- ── SIDEBAR ── -->
		<div class="ewa-sidebar">

			<!-- How to Use Card -->
			<div class="ewa-card ewa-sidebar-card ewa-card-info">
				<h2 class="ewa-card-title">💡 <?php esc_html_e( 'How to Use in Loco Translate', 'ewa-ai-translator-for-loco-translate' ); ?></h2>
				<ol class="ewa-how-to">
					<li><?php esc_html_e( 'Go to Loco Translate → Plugins or Themes', 'ewa-ai-translator-for-loco-translate' ); ?></li>
					<li><?php esc_html_e( 'Click Edit on a translation file', 'ewa-ai-translator-for-loco-translate' ); ?></li>
					<li><?php esc_html_e( 'Click the "🤖 AI Translate" button in the toolbar', 'ewa-ai-translator-for-loco-translate' ); ?></li>
					<li><?php esc_html_e( 'Translations are automatically saved to PO and MO files', 'ewa-ai-translator-for-loco-translate' ); ?></li>
				</ol>
			</div>

		</div><!-- /.ewa-sidebar -->

	</div><!-- /.ewa-layout -->
</div>
