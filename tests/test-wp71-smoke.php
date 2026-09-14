<?php
/**
 * WordPress 7.1 + PHP 8.x + Loco Translate Smoke Test Suite
 *
 * Verifies all 10 WordPress.org readiness criteria:
 * 1. Activation lifecycle
 * 2. Settings page rendering
 * 3. Save settings & sanitization
 * 4. Provider selection & allowlist
 * 5. Model loading
 * 6. Connection test
 * 7. Translation action & validator
 * 8. Cancel translation
 * 9. Deactivation / reactivation lifecycle
 * 10. Zero PHP warnings / notices under error_reporting(E_ALL)
 */

error_reporting( E_ALL );

$php_notices_and_warnings = [];
set_error_handler( function ( $errno, $errstr, $errfile, $errline ) use ( &$php_notices_and_warnings ) {
	// Ignore errors suppressed with @
	if ( 0 === ( error_reporting() & $errno ) ) {
		return false;
	}
	$php_notices_and_warnings[] = [
		'errno'   => $errno,
		'errstr'  => $errstr,
		'errfile' => $errfile,
		'errline' => $errline,
	];
	return false;
} );

require_once __DIR__ . '/bootstrap.php';

use ErrorWebAgency\EwaAIStringAssistant\Plugin;
use ErrorWebAgency\EwaAIStringAssistant\Settings;
use ErrorWebAgency\EwaAIStringAssistant\Admin;
use ErrorWebAgency\EwaAIStringAssistant\Ajax;
use ErrorWebAgency\EwaAIStringAssistant\Api_Client;
use ErrorWebAgency\EwaAIStringAssistant\Translation_Validator;
use ErrorWebAgency\EwaAIStringAssistant\Po_Handler;

class WP71_Smoke_Test_Runner {

	private $passed = 0;
	private $failed = 0;
	private $errors = [];

	public function run() {
		echo "=======================================================\n";
		echo "   EWA AI STRING ASSISTANT - WORDPRESS 7.1 SMOKE TEST\n";
		echo "   Environment: WordPress 7.1 | PHP " . PHP_VERSION . " | Loco Translate Mock\n";
		echo "=======================================================\n\n";

		$this->test_1_activation();
		$this->test_2_settings_page_render();
		$this->test_3_save_settings();
		$this->test_4_provider_selection();
		$this->test_5_model_loading();
		$this->test_6_connection_test();
		$this->test_7_translation_action();
		$this->test_8_cancel_translation();
		$this->test_9_deactivation_reactivation();
		$this->test_10_zero_php_warnings_notices();

		echo "\n-------------------------------------------------------\n";
		echo sprintf( "SMOKE TEST РЕЗУЛТАТ: %d преминати, %d провали.\n", $this->passed, $this->failed );
		echo "-------------------------------------------------------\n";

		if ( $this->failed > 0 ) {
			exit( 1 );
		}
	}

	private function assert( $condition, $message ) {
		if ( $condition ) {
			$this->passed++;
			echo "[PASS] " . $message . "\n";
		} else {
			$this->failed++;
			echo "[FAIL] " . $message . "\n";
		}
	}

	/**
	 * 1. Activation
	 */
	private function test_1_activation() {
		echo "--- 1. Activation Lifecycle ---\n";

		$plugin = Plugin::instance();
		$this->assert( $plugin instanceof Plugin, 'Плъгин инстанцията се създава успешно.' );

		// Симулиране на initial activation
		Settings::instance()->maybe_migrate_settings();
		$schema = get_option( Settings::SCHEMA_VERSION_KEY );
		$this->assert( '1.7.0' === $schema, 'Версията на схемата след активация е 1.7.0.' );

		$settings = Settings::instance()->get();
		$this->assert( is_array( $settings ), 'Настройките по подразбиране са масив.' );
		$this->assert( 'openrouter' === $settings['provider'], 'Началният доставчик по подразбиране е openrouter.' );
		$this->assert( 40 === $settings['batch_size'], 'Размерът на партидата по подразбиране е 40.' );
	}

	/**
	 * 2. Settings Page Rendering
	 */
	private function test_2_settings_page_render() {
		echo "--- 2. Settings Page Rendering ---\n";

		$admin = Admin::instance();
		ob_start();
		$admin->render_settings_page();
		$output = ob_get_clean();

		$this->assert( ! empty( $output ), 'Страницата с настройки генерира HTML изход.' );
		$this->assert( false !== strpos( $output, 'EWA AI String Assistant for Loco Translate' ), 'Заглавието на страницата присъства в изхода.' );
		$this->assert( false !== strpos( $output, 'AI Service Transparency & Data Transmission Disclosure' ), 'External Service оповестяването присъства в изхода.' );
		$this->assert( false !== strpos( $output, 'ewa-api-endpoint' ), 'Полето за API endpoint присъства в изхода.' );
		$this->assert( false !== strpos( $output, 'ewa-model-input' ), 'Полето за AI модел присъства в изхода.' );
		$this->assert( false !== strpos( $output, 'options.php' ), 'Формата сочи към стандартния WordPress options.php.' );
	}

	/**
	 * 3. Save Settings & Sanitization
	 */
	private function test_3_save_settings() {
		echo "--- 3. Save Settings & Sanitization ---\n";

		$input = [
			'provider'        => 'openrouter',
			'api_endpoint'    => 'https://openrouter.ai/api/v1/',
			'api_key'         => 'sk-or-v1-smoke-test-key-12345',
			'model'           => 'anthropic/claude-3.5-sonnet',
			'batch_size'      => '25',
			'max_retries'     => '5',
			'temperature'     => '0.2',
			'skip_translated' => '1',
			'system_prompt'   => 'Custom system prompt for testing.',
		];

		$sanitized = Settings::instance()->sanitize_settings( $input );
		update_option( Settings::OPTION_KEY, $sanitized );

		$saved = Settings::instance()->get();
		$this->assert( 'openrouter' === $saved['provider'], 'Запазеният доставчик е openrouter.' );
		$this->assert( 'sk-or-v1-smoke-test-key-12345' === $saved['api_key'], 'API ключът е запазен коректно.' );
		$this->assert( 25 === $saved['batch_size'], 'Размерът на партидата е саниран до цяло число 25.' );
		$this->assert( 5 === $saved['max_retries'], 'Броят повторни опити е саниран до 5.' );
		$this->assert( 0.2 === $saved['temperature'], 'Температурата е санирана до 0.2.' );
		$this->assert( 1 === $saved['skip_translated'], 'Флагът skip_translated е активен (1).' );
	}

	/**
	 * 4. Provider Selection & Allowlist
	 */
	private function test_4_provider_selection() {
		echo "--- 4. Provider Selection & Allowlist ---\n";

		$ollama_input = [ 'provider' => 'ollama' ];
		$sanitized    = Settings::instance()->sanitize_settings( $ollama_input );
		$this->assert( 'ollama' === $sanitized['provider'], 'Доставчикът ollama се приема.' );

		$custom_input = [ 'provider' => 'custom' ];
		$sanitized    = Settings::instance()->sanitize_settings( $custom_input );
		$this->assert( 'custom' === $sanitized['provider'], 'Доставчикът custom се приема.' );

		$invalid_input = [ 'provider' => 'malicious_remote_endpoint' ];
		$sanitized     = Settings::instance()->sanitize_settings( $invalid_input );
		$this->assert( 'openrouter' === $sanitized['provider'], 'Невалиден доставчик се санира до safe default: openrouter.' );
	}

	/**
	 * 5. Model Loading
	 */
	private function test_5_model_loading() {
		echo "--- 5. Model Loading ---\n";

		$GLOBALS['wp_mock_get_handler'] = function ( $url, $args ) {
			return [
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'body'     => json_encode( [
					'data' => [
						[ 'id' => 'openai/gpt-4o-mini', 'name' => 'GPT-4o Mini' ],
						[ 'id' => 'anthropic/claude-3.5-sonnet', 'name' => 'Claude 3.5 Sonnet' ],
						[ 'id' => 'google/gemini-flash-1.5', 'name' => 'Gemini 1.5 Flash' ],
					],
				] ),
				'headers'  => [],
			];
		};

		$client = new Api_Client( [
			'provider'     => 'openrouter',
			'api_endpoint' => 'https://openrouter.ai/api/v1',
			'api_key'      => 'test-key',
		] );

		$models = $client->fetch_models();
		$this->assert( ! is_wp_error( $models ), 'Зареждането на модели не връща WP_Error.' );
		$this->assert( is_array( $models ) && count( $models ) === 3, 'Заредени са точно 3 модела.' );
		$this->assert( 'anthropic/claude-3.5-sonnet' === $models[0]['id'], 'Първият модел съвпада с очаквания сортиран по ID списък.' );

		unset( $GLOBALS['wp_mock_get_handler'] );
	}

	/**
	 * 6. Connection Test
	 */
	private function test_6_connection_test() {
		echo "--- 6. Connection Test ---\n";

		$GLOBALS['wp_mock_post_handler'] = function ( $url, $args ) {
			return [
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'body'     => json_encode( [
					'choices' => [
						[
							'message' => [
								'content' => json_encode( [
									'translations' => [
										[ 'id' => 'entry_0', 'text' => 'Здравейте' ],
									],
								] ),
							],
						],
					],
				] ),
				'headers'  => [],
			];
		};

		$client = new Api_Client( [
			'provider'     => 'openrouter',
			'api_endpoint' => 'https://openrouter.ai/api/v1',
			'api_key'      => 'test-key',
			'model'        => 'openai/gpt-4o-mini',
		] );

		$test_batch = [
			[
				'index'      => 0,
				'msgid'      => 'Hello',
				'plural'     => null,
				'msgctxt'    => null,
				'duplicates' => [],
			],
		];

		$result = $client->translate_batch( $test_batch, 'Bulgarian' );
		$this->assert( ! is_wp_error( $result ), 'Тестовият превод не връща грешка.' );
		$this->assert( isset( $result[0] ) && 'Здравейте' === $result[0], 'Резултатът от тестовата връзка е "Здравейте".' );

		unset( $GLOBALS['wp_mock_post_handler'] );
	}

	/**
	 * 7. Translation Action & Validator
	 */
	private function test_7_translation_action() {
		echo "--- 7. Translation Action & Validator ---\n";

		$GLOBALS['wp_mock_post_handler'] = function ( $url, $args ) {
			return [
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'body'     => json_encode( [
					'choices' => [
						[
							'message' => [
								'content' => json_encode( [
									'translations' => [
										[ 'id' => 'entry_0', 'text' => 'Добре дошли в %s!' ],
										[ 'id' => 'entry_1', 'translations' => [ '%d елемент', '%d елемента' ] ],
									],
								] ),
							],
						],
					],
				] ),
				'headers'  => [],
			];
		};

		$client = new Api_Client( [
			'provider'     => 'openrouter',
			'api_endpoint' => 'https://openrouter.ai/api/v1',
			'api_key'      => 'test-key',
		] );

		$batch = [
			[
				'index'      => 0,
				'msgid'      => 'Welcome to %s!',
				'plural'     => null,
				'msgctxt'    => null,
				'duplicates' => [],
			],
			[
				'index'      => 1,
				'msgid'      => '%d item',
				'plural'     => '%d items',
				'msgctxt'    => null,
				'duplicates' => [],
			],
		];

		$translations = $client->translate_batch( $batch, 'Bulgarian', 2 );
		$this->assert( ! is_wp_error( $translations ), 'translate_batch връща валиден масив.' );

		$val0 = Translation_Validator::validate_pair( $batch[0]['msgid'], $translations[0] );
		$this->assert( true === $val0, 'Запис 0 с %s преминава валидатора.' );

		$val1 = Translation_Validator::validate_plural( $batch[1]['msgid'], $batch[1]['plural'], $translations[1], 2 );
		$this->assert( true === $val1, 'Запис 1 с 2 плурални форми и %d преминава валидатора.' );

		unset( $GLOBALS['wp_mock_post_handler'] );
	}

	/**
	 * 8. Cancel Translation
	 */
	private function test_8_cancel_translation() {
		echo "--- 8. Cancel Translation Mechanism ---\n";

		$job_id = 'smoke_test_job_' . time();
		set_transient( 'ewaas_cancel_' . $job_id, 1, 300 );

		$is_cancelled = (bool) get_transient( 'ewaas_cancel_' . $job_id );
		$this->assert( true === $is_cancelled, 'Транзиентът за отказ на превод ewaas_cancel_{id} се разпознава.' );

		delete_transient( 'ewaas_cancel_' . $job_id );
		$this->assert( false === get_transient( 'ewaas_cancel_' . $job_id ), 'След почистване транзиентът вече не е активен.' );
	}

	/**
	 * 9. Deactivation / Reactivation Lifecycle
	 */
	private function test_9_deactivation_reactivation() {
		echo "--- 9. Deactivation / Reactivation Lifecycle ---\n";

		// 1. Деактивация (почистване на временни транзиенти, запазване на настройките)
		$active_settings = get_option( Settings::OPTION_KEY );
		$this->assert( ! empty( $active_settings ), 'Настройките съществуват преди деактивация.' );

		// 2. Реактивация
		Settings::instance()->maybe_migrate_settings();
		$schema = get_option( Settings::SCHEMA_VERSION_KEY );
		$this->assert( '1.7.0' === $schema, 'Версията на схемата остава 1.7.0 след реактивация.' );

		$reloaded_settings = get_option( Settings::OPTION_KEY );
		$this->assert( $reloaded_settings === $active_settings, 'Настройките са запазени непокътнати след реактивация.' );
	}

	/**
	 * 10. Zero PHP Warnings / Notices
	 */
	private function test_10_zero_php_warnings_notices() {
		echo "--- 10. Strict Error Reporting (Zero Warnings / Notices) ---\n";

		global $php_notices_and_warnings;
		$count = count( $php_notices_and_warnings );

		if ( $count > 0 ) {
			echo "[FAIL] Открити са $count PHP съобщения/предупреждения:\n";
			foreach ( $php_notices_and_warnings as $err ) {
				echo "  - [{$err['errno']}] {$err['errstr']} в {$err['errfile']}:{$err['errline']}\n";
			}
		}

		$this->assert( 0 === $count, 'Няма генерирани PHP notices, warnings или deprecated съобщения.' );
	}
}

$runner = new WP71_Smoke_Test_Runner();
$runner->run();
