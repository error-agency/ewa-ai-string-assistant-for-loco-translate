<?php
/**
 * Automated Verification Suite for WordPress 7.0+ AI Client Integration.
 * Scenarios A through I + No Silent Fallback verification.
 */

require_once __DIR__ . '/bootstrap.php';

use ErrorWebAgency\EwaAIStringAssistant\AI_Transport_Interface;
use ErrorWebAgency\EwaAIStringAssistant\WP_AI_Client_Transport;
use ErrorWebAgency\EwaAIStringAssistant\Direct_AI_Transport;
use ErrorWebAgency\EwaAIStringAssistant\AI_Transport_Manager;
use ErrorWebAgency\EwaAIStringAssistant\Api_Client;
use ErrorWebAgency\EwaAIStringAssistant\Settings;

class WP_AI_Client_Test_Runner {

	private $passed = 0;
	private $failed = 0;

	public function run() {
		echo "=======================================================\n";
		echo "   EWA AI STRING ASSISTANT - WP 7 AI CLIENT TEST SUITE\n";
		echo "=======================================================\n\n";

		$this->test_scenario_a();
		$this->test_scenario_b();
		$this->test_scenario_c();
		$this->test_scenario_d();
		$this->test_scenario_e();
		$this->test_scenario_f();
		$this->test_scenario_g();
		$this->test_scenario_h();
		$this->test_scenario_i();
		$this->test_no_silent_fallback();

		echo "\n-------------------------------------------------------\n";
		echo sprintf( "РЕЗУЛТАТ: %d преминати, %d провали.\n", $this->passed, $this->failed );
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

	private function reset_environment() {
		$GLOBALS['wp_options'] = [];
		$GLOBALS['wp_mock_supports_ai'] = true;
		$GLOBALS['wp_mock_ai_supported_for_text'] = true;
		unset( $GLOBALS['wp_mock_ai_generate_exception'] );
		unset( $GLOBALS['wp_mock_ai_generate_error'] );
		unset( $GLOBALS['wp_mock_ai_generate_handler'] );
		unset( $GLOBALS['wp_mock_post_handler'] );
		unset( $GLOBALS['wp_mock_get_handler'] );
		$GLOBALS['wp_version'] = '7.1';
	}

	/**
	 * Scenario A: WP 7.1 + Core AI provider configured.
	 * Zero EWA API keys needed. Generates translations via wp_ai_client_prompt.
	 */
	private function test_scenario_a() {
		echo "--- Scenario A: WP 7.1 + Core Provider Available (0 API keys) ---\n";
		$this->reset_environment();

		$called_wp_ai = false;
		$GLOBALS['wp_mock_ai_generate_handler'] = function( $prompt, $builder ) use ( &$called_wp_ai ) {
			$called_wp_ai = true;
			return json_encode( [
				'translations' => [
					[ 'id' => 'entry_0', 'translation' => 'Здравей, свят!' ]
				]
			] );
		};

		// Set transport to wordpress, empty API key
		$GLOBALS['wp_options'][ Settings::OPTION_KEY ] = [
			'ai_transport' => 'wordpress',
			'api_key'      => '',
		];

		$transport = AI_Transport_Manager::get_active_transport();
		$this->assert( $transport instanceof WP_AI_Client_Transport, 'Активният транспорт трябва да е WP_AI_Client_Transport' );
		$this->assert( $transport->is_available(), 'WP_AI_Client_Transport трябва да е наличен' );

		$api = new Api_Client( [ 'ai_transport' => 'wordpress', 'api_key' => '' ] );
		$batch = [
			[ 'index' => 0, 'msgid' => 'Hello, world!', 'plural' => null, 'msgctxt' => null, 'duplicates' => [] ]
		];
		$res = $api->translate_batch( $batch, 'bg_BG' );

		$this->assert( ! is_wp_error( $res ), 'translate_batch не трябва да връща грешка' );
		$this->assert( $called_wp_ai, 'Генерирането трябва да извика wp_ai_client_prompt handler' );
		$this->assert( isset( $res[0] ) && $res[0] === 'Здравей, свят!', 'Преводът трябва да бъде върнат коректно без API ключ в плъгина' );
	}

	/**
	 * Scenario B: WP 7.1 + No compatible provider configured in Core.
	 * Controlled error, zero direct fallback calls.
	 */
	private function test_scenario_b() {
		echo "\n--- Scenario B: WP 7.1 + No Compatible Core Provider ---\n";
		$this->reset_environment();

		$GLOBALS['wp_mock_ai_supported_for_text'] = false;
		$direct_http_called = false;
		$GLOBALS['wp_mock_post_handler'] = function() use ( &$direct_http_called ) {
			$direct_http_called = true;
			return [ 'response' => [ 'code' => 200 ], 'body' => '{}' ];
		};

		$GLOBALS['wp_options'][ Settings::OPTION_KEY ] = [
			'ai_transport' => 'wordpress',
			'api_key'      => 'sk-direct-secret',
		];

		$transport = AI_Transport_Manager::get_active_transport();
		$this->assert( ! $transport->is_available(), 'WP_AI_Client_Transport не трябва да е наличен при липса на text provider' );

		$res = $transport->generate( 'Translate test' );
		$this->assert( is_wp_error( $res ), 'Трябва да се върне контролирана WP_Error' );
		$this->assert( $res->get_error_code() === 'no_text_provider', 'Грешката трябва да е с код no_text_provider' );
		$this->assert( ! $direct_http_called, 'Не трябва да има тих fallback към директния HTTP транспорт' );
	}

	/**
	 * Scenario C: WP 7.1 + AI Disabled in environment.
	 * Controlled error, zero direct fallback calls.
	 */
	private function test_scenario_c() {
		echo "\n--- Scenario C: WP 7.1 + AI Disabled in Environment ---\n";
		$this->reset_environment();

		$GLOBALS['wp_mock_supports_ai'] = false;
		$direct_http_called = false;
		$GLOBALS['wp_mock_post_handler'] = function() use ( &$direct_http_called ) {
			$direct_http_called = true;
			return [ 'response' => [ 'code' => 200 ], 'body' => '{}' ];
		};

		$GLOBALS['wp_options'][ Settings::OPTION_KEY ] = [
			'ai_transport' => 'wordpress',
			'api_key'      => 'sk-direct-secret',
		];

		$transport = AI_Transport_Manager::get_active_transport();
		$this->assert( ! $transport->is_available(), 'WP_AI_Client_Transport не трябва да е наличен при изключено AI в Core' );

		$res = $transport->generate( 'Translate test' );
		$this->assert( is_wp_error( $res ), 'Трябва да се върне контролирана WP_Error' );
		$this->assert( $res->get_error_code() === 'ai_disabled', 'Грешката трябва да е с код ai_disabled' );
		$this->assert( ! $direct_http_called, 'Не трябва да има тих fallback към директния HTTP транспорт' );
	}

	/**
	 * Scenario D: WP 7.1 + Direct OpenRouter connection.
	 */
	private function test_scenario_d() {
		echo "\n--- Scenario D: WP 7.1 + Direct OpenRouter Connection ---\n";
		$this->reset_environment();

		$direct_called = false;
		$GLOBALS['wp_mock_post_handler'] = function( $url, $args ) use ( &$direct_called ) {
			$direct_called = true;
			return [
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'body'     => json_encode( [
					'choices' => [
						[ 'message' => [ 'content' => json_encode( [
							'translations' => [
								[ 'id' => 'entry_0', 'translation' => 'Отворено' ]
							]
						] ) ] ]
					]
				] ),
				'headers'  => [],
			];
		};

		$api = new Api_Client( [
			'ai_transport' => 'direct',
			'provider'     => 'openrouter',
			'api_key'      => 'sk-or-test-key-12345',
			'model'        => 'openai/gpt-4o-mini',
		] );

		$batch = [
			[ 'index' => 0, 'msgid' => 'Open', 'plural' => null, 'msgctxt' => null, 'duplicates' => [] ]
		];
		$res = $api->translate_batch( $batch, 'bg_BG' );

		$this->assert( $direct_called, 'Директният HTTP post handler трябва да бъде извикан за OpenRouter' );
		$this->assert( ! is_wp_error( $res ), 'Преводът с OpenRouter не трябва да връща грешка' );
		$this->assert( isset( $res[0] ) && $res[0] === 'Отворено', 'Резултатът от OpenRouter трябва да бъде отразен коректно' );
	}

	/**
	 * Scenario E: WP 7.1 + Direct Ollama connection.
	 */
	private function test_scenario_e() {
		echo "\n--- Scenario E: WP 7.1 + Direct Ollama Connection ---\n";
		$this->reset_environment();

		$direct_called = false;
		$request_url = '';
		$GLOBALS['wp_mock_post_handler'] = function( $url, $args ) use ( &$direct_called, &$request_url ) {
			$direct_called = true;
			$request_url = $url;
			return [
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'body'     => json_encode( [
					'message' => [
						'content' => json_encode( [
							'translations' => [
								[ 'id' => 'entry_0', 'translation' => 'Локален превод' ]
							]
						] )
					]
				] ),
				'headers'  => [],
			];
		};

		$api = new Api_Client( [
			'ai_transport' => 'direct',
			'provider'     => 'ollama',
			'api_endpoint' => 'http://localhost:11434',
			'model'        => 'llama3:latest',
		] );

		$batch = [
			[ 'index' => 0, 'msgid' => 'Local translation', 'plural' => null, 'msgctxt' => null, 'duplicates' => [] ]
		];
		$res = $api->translate_batch( $batch, 'bg_BG' );

		$this->assert( $direct_called, 'Директният HTTP post handler трябва да бъде извикан за Ollama' );
		$this->assert( strpos( $request_url, 'http://localhost:11434/api/chat' ) !== false, 'URL адресът за Ollama трябва да сочи към /api/chat' );
		$this->assert( ! is_wp_error( $res ), 'Преводът с Ollama не трябва да връща грешка' );
		$this->assert( isset( $res[0] ) && $res[0] === 'Локален превод', 'Резултатът от Ollama трябва да бъде отразен коректно' );
	}

	/**
	 * Scenario F: WP 7.1 + Direct Custom Endpoint.
	 */
	private function test_scenario_f() {
		echo "\n--- Scenario F: WP 7.1 + Direct Custom Endpoint ---\n";
		$this->reset_environment();

		$direct_called = false;
		$request_url = '';
		$GLOBALS['wp_mock_post_handler'] = function( $url, $args ) use ( &$direct_called, &$request_url ) {
			$direct_called = true;
			$request_url = $url;
			return [
				'response' => [ 'code' => 200, 'message' => 'OK' ],
				'body'     => json_encode( [
					'choices' => [
						[ 'message' => [ 'content' => json_encode( [
							'translations' => [
								[ 'id' => 'entry_0', 'translation' => 'Специфичен превод' ]
							]
						] ) ] ]
					]
				] ),
				'headers'  => [],
			];
		};

		$api = new Api_Client( [
			'ai_transport' => 'direct',
			'provider'     => 'custom',
			'api_endpoint' => 'https://my-custom-ai.internal/v1',
			'api_key'      => 'custom-sk-123',
			'model'        => 'custom-model-x',
		] );

		$batch = [
			[ 'index' => 0, 'msgid' => 'Custom text', 'plural' => null, 'msgctxt' => null, 'duplicates' => [] ]
		];
		$res = $api->translate_batch( $batch, 'bg_BG' );

		$this->assert( $direct_called, 'Директният HTTP post handler трябва да бъде извикан за Custom Endpoint' );
		$this->assert( strpos( $request_url, 'https://my-custom-ai.internal/v1/chat/completions' ) !== false, 'URL адресът трябва да сочи към /chat/completions на персонализирания endpoint' );
		$this->assert( ! is_wp_error( $res ), 'Преводът с Custom Endpoint не трябва да връща грешка' );
		$this->assert( isset( $res[0] ) && $res[0] === 'Специфичен превод', 'Резултатът от Custom Endpoint трябва да бъде отразен коректно' );
	}

	/**
	 * Scenario G: WP 6.x Compatibility (when wp_ai_client_prompt is unavailable).
	 */
	private function test_scenario_g() {
		echo "\n--- Scenario G: WP 6.x Compatibility ---\n";
		$this->reset_environment();

		$GLOBALS['wp_version'] = '6.5';
		// Even if wp_ai_client_prompt function exists in PHP runtime, is_wp_ai_client_supported checks WP version >= 7.0
		$this->assert( ! AI_Transport_Manager::is_wp_ai_client_supported(), 'WordPress AI Client не трябва да се поддържа при WP < 7.0' );

		// Direct transport should be selected automatically as fallback
		$transport = AI_Transport_Manager::get_active_transport( [ 'ai_transport' => 'wordpress' ] );
		$this->assert( $transport instanceof Direct_AI_Transport, 'При WP 6.x активният транспорт трябва да е Direct_AI_Transport' );
	}

	/**
	 * Scenario H: Upgrade Test (existing install retains 'direct' mode).
	 */
	private function test_scenario_h() {
		echo "\n--- Scenario H: Upgrade Test (Preserve Direct Mode) ---\n";
		$this->reset_environment();

		$GLOBALS['wp_options'][ Settings::OPTION_KEY ] = [
			'provider'     => 'openrouter',
			'api_key'      => 'sk-legacy-upgrade-key',
			'model'        => 'anthropic/claude-3.5-sonnet',
			'api_endpoint' => 'https://openrouter.ai/api/v1',
			'temperature'  => 0.2,
			'batch_size'   => 25,
		];
		$GLOBALS['wp_options'][ Settings::SCHEMA_VERSION_KEY ] = '1.7.0';

		$settings = Settings::instance();
		$settings->maybe_migrate_settings();

		$all = get_option( Settings::OPTION_KEY );
		$this->assert( isset( $all['ai_transport'] ) && $all['ai_transport'] === 'direct', 'При ъпгрейд на съществуваща инсталация ai_transport ТРЯБВА да остане direct' );
		$this->assert( $all['api_key'] === 'sk-legacy-upgrade-key', 'API ключът трябва да е запазен' );
		$this->assert( get_option( Settings::SCHEMA_VERSION_KEY ) === Settings::SCHEMA_VERSION, 'Схемата трябва да бъде актуализирана до 1.8.0' );
	}

	/**
	 * Scenario I: Fresh Install Test.
	 * WP 7.1 gets 'wordpress', WP 6.5 gets 'direct'.
	 */
	private function test_scenario_i() {
		echo "\n--- Scenario I: Fresh Install Defaults ---\n";
		$this->reset_environment();

		// WP 7.1 fresh install
		$GLOBALS['wp_version'] = '7.1';
		$settings = Settings::instance();
		$defaults_wp7 = $settings->get_defaults();
		$this->assert( $defaults_wp7['ai_transport'] === 'wordpress', 'Нова инсталация на WP 7.1+ трябва да има по подразбиране ai_transport = wordpress' );

		// WP 6.5 fresh install
		$GLOBALS['wp_version'] = '6.5';
		$defaults_wp6 = $settings->get_defaults();
		$this->assert( $defaults_wp6['ai_transport'] === 'direct', 'Нова инсталация на WP 6.5 трябва да има по подразбиране ai_transport = direct' );
	}

	/**
	 * No Silent Fallback Verification:
	 * When in 'wordpress' transport mode and Core AI throws an exception or returns WP_Error,
	 * it MUST return a WP_Error to the caller and NEVER silently call direct endpoints.
	 */
	private function test_no_silent_fallback() {
		echo "\n--- No Silent Fallback Verification ---\n";
		$this->reset_environment();

		$direct_http_called = false;
		$GLOBALS['wp_mock_post_handler'] = function() use ( &$direct_http_called ) {
			$direct_http_called = true;
			return [ 'response' => [ 'code' => 200 ], 'body' => '{}' ];
		};

		// 1. Exception in Core AI generate_text()
		$GLOBALS['wp_mock_ai_generate_exception'] = 'Core AI network timeout';
		$transport = new WP_AI_Client_Transport();
		$res = $transport->generate( 'Prompt' );

		$this->assert( is_wp_error( $res ), 'При изключение в Core AI трябва да се върне WP_Error' );
		$this->assert( $res->get_error_code() === 'wp_ai_client_exception', 'Кодът на грешката трябва да е wp_ai_client_exception' );
		$this->assert( ! $direct_http_called, 'При изключение в Core AI НЕ трябва да има тих fallback към direct HTTP' );

		// 2. WP_Error in Core AI generate_text()
		$GLOBALS['wp_mock_ai_generate_error'] = 'Provider quota exceeded';
		unset( $GLOBALS['wp_mock_ai_generate_exception'] );
		$res2 = $transport->generate( 'Prompt' );

		$this->assert( is_wp_error( $res2 ), 'При WP_Error от Core AI трябва да се върне WP_Error' );
		$this->assert( ! $direct_http_called, 'При WP_Error от Core AI НЕ трябва да има тих fallback към direct HTTP' );
	}
}

$runner = new WP_AI_Client_Test_Runner();
$runner->run();
