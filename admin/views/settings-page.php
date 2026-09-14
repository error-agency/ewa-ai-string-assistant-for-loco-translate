<?php
use ErrorWebAgency\EwaAIStringAssistant\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

( function () {
	$ewaas_settings = Settings::instance()->get();
	$ewaas_provider = $ewaas_settings['provider'];
?>
<div class="wrap ewa-settings-wrap">
	<h1 class="ewa-page-title">
		<span class="dashicons dashicons-translation ewa-logo"></span>
		<?php esc_html_e( 'EWA AI String Assistant for Loco Translate', 'ewa-ai-string-assistant-for-loco-translate' ); ?>
		<span class="ewa-version">v<?php echo esc_html( defined( 'EWAAS_VERSION' ) ? EWAAS_VERSION : '1.7.0' ); ?></span>
	</h1>

	<?php settings_errors( 'ewaas_settings_group' ); ?>

	<!-- Transparency and Data Disclosure Notice -->
	<div class="notice notice-info inline" style="margin: 15px 0 20px; padding: 12px 15px; border-left-color: #2271b1; background: #fff;">
		<p style="margin: 0 0 6px; font-weight: 600; font-size: 13px;">
			<span class="dashicons dashicons-privacy" style="vertical-align: text-top; margin-right: 4px; color: #2271b1;"></span>
			<?php esc_html_e( 'AI Service Transparency & Data Transmission Disclosure', 'ewa-ai-string-assistant-for-loco-translate' ); ?>
		</p>
		<p style="margin: 0; font-size: 12px; color: #50575e; line-height: 1.5;">
			<?php esc_html_e( 'This plugin connects to external AI services (OpenRouter, a self-hosted/remote Ollama instance, or a custom OpenAI-compatible endpoint) ONLY when an administrator explicitly initiates a translation or connection test. Source strings, context, and translation instructions are sent directly from your server to your selected provider. No data is ever transmitted automatically or in the background, and no intermediary servers are used.', 'ewa-ai-string-assistant-for-loco-translate' ); ?>
		</p>
	</div>

	<div class="ewa-layout">

		<!-- ── MAIN SETTINGS ── -->
		<div class="ewa-main-col">
			<form method="post" action="options.php" id="ewa-settings-form">
				<?php settings_fields( 'ewaas_settings_group' ); ?>

				<!-- 1. AI Provider & Connection Card -->
				<div class="ewa-card">
					<h2 class="ewa-card-title">
						<span class="dashicons dashicons-cloud"></span>
						<?php esc_html_e( 'AI Provider & Connection', 'ewa-ai-string-assistant-for-loco-translate' ); ?>
					</h2>

					<div class="ewa-provider-tabs">
						<label class="ewa-provider-tab <?php echo 'openrouter' === $ewaas_provider ? 'active' : ''; ?>">
							<input type="radio" name="ewa_settings[provider]" value="openrouter"
								<?php checked( $ewaas_provider, 'openrouter' ); ?>>
							<span class="dashicons dashicons-cloud ewa-provider-icon"></span>
							<strong>OpenRouter</strong>
							<small><?php esc_html_e( 'Cloud API aggregator', 'ewa-ai-string-assistant-for-loco-translate' ); ?></small>
						</label>
						<label class="ewa-provider-tab <?php echo 'ollama' === $ewaas_provider ? 'active' : ''; ?>">
							<input type="radio" name="ewa_settings[provider]" value="ollama"
								<?php checked( $ewaas_provider, 'ollama' ); ?>>
							<span class="dashicons dashicons-admin-home ewa-provider-icon"></span>
							<strong>Ollama</strong>
							<small><?php esc_html_e( 'Local / self-hosted LLM', 'ewa-ai-string-assistant-for-loco-translate' ); ?></small>
						</label>
						<label class="ewa-provider-tab <?php echo 'custom' === $ewaas_provider ? 'active' : ''; ?>">
							<input type="radio" name="ewa_settings[provider]" value="custom"
								<?php checked( $ewaas_provider, 'custom' ); ?>>
							<span class="dashicons dashicons-admin-tools ewa-provider-icon"></span>
							<strong><?php esc_html_e( 'Custom Endpoint', 'ewa-ai-string-assistant-for-loco-translate' ); ?></strong>
							<small><?php esc_html_e( 'OpenAI-compatible API', 'ewa-ai-string-assistant-for-loco-translate' ); ?></small>
						</label>
					</div>

					<table class="form-table ewa-form-table">
						<tr>
							<th><?php esc_html_e( 'API Endpoint', 'ewa-ai-string-assistant-for-loco-translate' ); ?></th>
							<td>
								<input type="url" name="ewa_settings[api_endpoint]"
									value="<?php echo esc_attr( $ewaas_settings['api_endpoint'] ); ?>"
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
						<tr class="ewa-row-apikey" <?php echo 'ollama' === $ewaas_provider ? 'style="display:none"' : ''; ?>>
							<th><?php esc_html_e( 'API Key', 'ewa-ai-string-assistant-for-loco-translate' ); ?></th>
							<td>
								<input type="password" name="ewa_settings[api_key]"
									value=""
									placeholder="<?php echo ! empty( $ewaas_settings['api_key'] ) ? esc_attr__( 'API key saved — leave empty to keep', 'ewa-ai-string-assistant-for-loco-translate' ) : ''; ?>"
									class="regular-text" autocomplete="new-password">
								<?php if ( ! empty( $ewaas_settings['api_key'] ) ) : ?>
									<label style="margin-left:10px;">
										<input type="checkbox" name="ewa_settings[clear_api_key]" value="1">
										<?php esc_html_e( 'Clear saved API key', 'ewa-ai-string-assistant-for-loco-translate' ); ?>
									</label>
								<?php endif; ?>
								<p class="description">
									<?php esc_html_e( 'Leave empty if using a local Ollama endpoint without authentication.', 'ewa-ai-string-assistant-for-loco-translate' ); ?>
									<a href="https://openrouter.ai/keys" target="_blank" rel="noopener">
										<?php esc_html_e( 'Get OpenRouter key ↗', 'ewa-ai-string-assistant-for-loco-translate' ); ?>
									</a>
								</p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Connection Test', 'ewa-ai-string-assistant-for-loco-translate' ); ?></th>
							<td>
								<button type="button" id="ewa-test-connection" class="button button-secondary">
									<span class="dashicons dashicons-rest-api"></span>
									<?php esc_html_e( 'Test Connection', 'ewa-ai-string-assistant-for-loco-translate' ); ?>
								</button>
								<span id="ewa-test-result" class="ewa-test-result"></span>
							</td>
						</tr>
					</table>
				</div>

				<!-- 2. Model Selection Card -->
				<div class="ewa-card">
					<h2 class="ewa-card-title">
						<span class="dashicons dashicons-lightbulb"></span>
						<?php esc_html_e( 'Model Selection', 'ewa-ai-string-assistant-for-loco-translate' ); ?>
					</h2>

					<table class="form-table ewa-form-table">
						<tr>
							<th><?php esc_html_e( 'Active Model', 'ewa-ai-string-assistant-for-loco-translate' ); ?></th>
							<td>
								<div class="ewa-model-row">
									<input type="text" name="ewa_settings[model]" id="ewa-model-input"
										value="<?php echo esc_attr( $ewaas_settings['model'] ); ?>"
										class="regular-text"
										placeholder="openai/gpt-4o-mini">
									<button type="button" id="ewa-fetch-models" class="button">
										<span class="dashicons dashicons-update"></span>
										<?php esc_html_e( 'Load Models', 'ewa-ai-string-assistant-for-loco-translate' ); ?>
									</button>
								</div>
								<select id="ewa-model-select" style="display:none; margin-top:8px; width:100%; max-width:500px;">
									<option value=""><?php esc_html_e( '— choose a model —', 'ewa-ai-string-assistant-for-loco-translate' ); ?></option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Type model ID directly or click Load Models to fetch from provider.', 'ewa-ai-string-assistant-for-loco-translate' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<!-- 3. Progressive Disclosure: Advanced Settings Card -->
				<div class="ewa-card ewa-advanced-card">
					<details class="ewa-advanced-details">
						<summary class="ewa-advanced-summary">
							<span class="dashicons dashicons-admin-generic"></span>
							<strong><?php esc_html_e( 'Advanced Translation Settings', 'ewa-ai-string-assistant-for-loco-translate' ); ?></strong>
							<small>(<?php esc_html_e( 'Temperature, Batch size, System prompt', 'ewa-ai-string-assistant-for-loco-translate' ); ?>)</small>
							<span class="dashicons dashicons-arrow-down-alt2 ewa-chevron"></span>
						</summary>
						<div class="ewa-advanced-content">
							<table class="form-table ewa-form-table">
								<tr>
									<th><?php esc_html_e( 'Temperature', 'ewa-ai-string-assistant-for-loco-translate' ); ?></th>
									<td>
										<input type="number" name="ewa_settings[temperature]"
											value="<?php echo esc_attr( $ewaas_settings['temperature'] ); ?>"
											min="0" max="2" step="0.1" class="small-text">
										<p class="description">
											<?php esc_html_e( '0 = deterministic, 1 = creative. Recommended: 0.1–0.4 for translations.', 'ewa-ai-string-assistant-for-loco-translate' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Batch Size', 'ewa-ai-string-assistant-for-loco-translate' ); ?></th>
									<td>
										<input type="number" name="ewa_settings[batch_size]"
											value="<?php echo esc_attr( $ewaas_settings['batch_size'] ); ?>"
											min="5" max="100" class="small-text">
										<p class="description">
											<?php esc_html_e( 'Strings per API call. Default: 40. Range: 5–100.', 'ewa-ai-string-assistant-for-loco-translate' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Max Retries', 'ewa-ai-string-assistant-for-loco-translate' ); ?></th>
									<td>
										<input type="number" name="ewa_settings[max_retries]"
											value="<?php echo esc_attr( $ewaas_settings['max_retries'] ?? 3 ); ?>"
											min="0" max="10" class="small-text">
										<p class="description">
											<?php esc_html_e( 'Number of retries per batch on API failure before skipping. Default: 3.', 'ewa-ai-string-assistant-for-loco-translate' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'Skip Translated', 'ewa-ai-string-assistant-for-loco-translate' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="ewa_settings[skip_translated]" value="1"
												<?php checked( $ewaas_settings['skip_translated'], 1 ); ?>>
											<?php esc_html_e( 'Skip strings that already have a translation', 'ewa-ai-string-assistant-for-loco-translate' ); ?>
										</label>
									</td>
								</tr>
								<tr>
									<th><?php esc_html_e( 'System Prompt', 'ewa-ai-string-assistant-for-loco-translate' ); ?></th>
									<td>
										<textarea name="ewa_settings[system_prompt]" rows="6"
											class="large-text" placeholder="<?php esc_attr_e( 'Leave blank to use the default translation prompt.', 'ewa-ai-string-assistant-for-loco-translate' ); ?>"
										><?php echo esc_textarea( $ewaas_settings['system_prompt'] ); ?></textarea>
										<p class="description">
											<?php esc_html_e( 'Override the default prompt. Use {target_lang} for the language placeholder.', 'ewa-ai-string-assistant-for-loco-translate' ); ?>
										</p>
										<button type="button" id="ewa-show-default-prompt" class="button button-small">
											<?php esc_html_e( 'View default prompt', 'ewa-ai-string-assistant-for-loco-translate' ); ?>
										</button>
										<pre id="ewa-default-prompt-preview" style="display:none; background:#f6f7f7; padding:12px; border-radius:4px; white-space:pre-wrap; font-size:12px;"><?php echo esc_html( Settings::default_system_prompt( '{target_lang}' ) ); ?></pre>
									</td>
								</tr>
							</table>
						</div>
					</details>
				</div>

				<div class="ewa-actions">
					<?php submit_button( __( 'Save Settings', 'ewa-ai-string-assistant-for-loco-translate' ), 'primary large', 'submit', false ); ?>
				</div>

			</form>
		</div>

		<!-- ── SIDEBAR ── -->
		<div class="ewa-sidebar">

			<!-- How to Use Card -->
			<div class="ewa-card ewa-sidebar-card ewa-card-info">
				<h2 class="ewa-card-title">
					<span class="dashicons dashicons-info-outline"></span>
					<?php esc_html_e( 'How to Use in Loco Translate', 'ewa-ai-string-assistant-for-loco-translate' ); ?>
				</h2>
				<ol class="ewa-how-to">
					<li><?php esc_html_e( 'Go to Loco Translate → Plugins or Themes', 'ewa-ai-string-assistant-for-loco-translate' ); ?></li>
					<li><?php esc_html_e( 'Click Edit on a translation file', 'ewa-ai-string-assistant-for-loco-translate' ); ?></li>
					<li><?php esc_html_e( 'Click the AI Translate button in the toolbar', 'ewa-ai-string-assistant-for-loco-translate' ); ?></li>
					<li><?php esc_html_e( 'Translations are automatically saved to PO and MO files', 'ewa-ai-string-assistant-for-loco-translate' ); ?></li>
				</ol>
			</div>

			<!-- Backup & Disclaimer Card -->
			<div class="ewa-card ewa-sidebar-card">
				<h2 class="ewa-card-title">
					<span class="dashicons dashicons-shield"></span>
					<?php esc_html_e( 'Backup & Disclaimer', 'ewa-ai-string-assistant-for-loco-translate' ); ?>
				</h2>
				<p style="font-size:12px; color:#50575e; line-height:1.5; margin:0 0 10px;">
					<strong><?php esc_html_e( 'Always make a backup:', 'ewa-ai-string-assistant-for-loco-translate' ); ?></strong>
					<?php esc_html_e( 'Create a backup copy of your .po and .mo files before initiating automated translations.', 'ewa-ai-string-assistant-for-loco-translate' ); ?>
				</p>
				<p style="font-size:11px; color:#646970; line-height:1.4; margin:0;">
					<?php esc_html_e( 'This plugin is provided "as is" without warranty. Developers and Error Web Agency (EWA) assume no liability for translation inaccuracies, file overwrites, data loss, or API costs.', 'ewa-ai-string-assistant-for-loco-translate' ); ?>
				</p>
			</div>

		</div><!-- /.ewa-sidebar -->

	</div><!-- /.ewa-layout -->
</div>
<?php
} )();
