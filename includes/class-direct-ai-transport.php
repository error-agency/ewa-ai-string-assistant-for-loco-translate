<?php
namespace ErrorWebAgency\EwaAIStringAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Директен HTTP транспортен слой за външни AI доставчици (OpenRouter, Ollama, Custom OpenAI-compatible).
 * Поддържа се за съвместимост с WordPress < 7.0 и специализирани потребителски конфигурации.
 */
class Direct_AI_Transport implements AI_Transport_Interface {

	private $settings;
	private $overrides;

	public function __construct( array $overrides = [] ) {
		$this->settings  = Settings::instance();
		$this->overrides = array_filter( $overrides, function ( $val ) {
			return null !== $val && '' !== $val;
		} );
	}

	public function get_setting( $key, $default = null ) {
		if ( isset( $this->overrides[ $key ] ) ) {
			return $this->overrides[ $key ];
		}
		return $this->settings->get( $key, $default );
	}

	/**
	 * Изгражда правилен URL адрес за API крайни точки,
	 * предпазвайки от дублиране на пътища като /chat/completions/chat/completions (404).
	 *
	 * @param  string $suffix Очаквана крайна точка.
	 * @return string
	 */
	public function build_endpoint_url( string $suffix ): string {
		$raw = trim( (string) $this->get_setting( 'api_endpoint' ) );
		$raw = rtrim( $raw, '/' );

		// Почистване на предварително включени известни суфикси
		$raw = preg_replace( '#/(chat/completions|api/chat|api/tags|models)$#i', '', $raw );

		return $raw . '/' . ltrim( $suffix, '/' );
	}

	/**
	 * Проверява дали директният транспорт е конфигуриран.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		$provider = $this->get_setting( 'provider' );
		$endpoint = $this->get_setting( 'api_endpoint' );
		$api_key  = $this->get_setting( 'api_key' );

		if ( empty( $endpoint ) ) {
			return false;
		}

		if ( 'ollama' !== $provider && empty( $api_key ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Изпълнява директна HTTP заявка за генериране на превод.
	 *
	 * @param  string $prompt Потребителски промпт със структурираните за превод елементи.
	 * @param  array  $args   Параметри (system_prompt, temperature, etc.).
	 * @return array|\WP_Error Масив с 'text' и '_usage' или \WP_Error.
	 */
	public function generate( string $prompt, array $args = [] ) {
		$provider = $this->get_setting( 'provider', 'openrouter' );

		switch ( $provider ) {
			case 'ollama':
				return $this->call_ollama( $prompt, $args );
			case 'openrouter':
			default:
				return $this->call_openai_compatible( $prompt, $args );
		}
	}

	/**
	 * Извикване на OpenAI-съвместими крайни точки (OpenRouter, Custom, OpenAI).
	 */
	private function call_openai_compatible( string $prompt, array $args ) {
		$endpoint = $this->build_endpoint_url( 'chat/completions' );
		$api_key  = $this->get_setting( 'api_key' );
		$model    = $this->get_setting( 'model' );
		$temp     = isset( $args['temperature'] ) ? (float) $args['temperature'] : (float) $this->get_setting( 'temperature', 0.3 );

		$messages = [];
		if ( ! empty( $args['system_prompt'] ) ) {
			$messages[] = [ 'role' => 'system', 'content' => (string) $args['system_prompt'] ];
		}
		$messages[] = [ 'role' => 'user', 'content' => $prompt ];

		$body_arr = [
			'model'       => $model,
			'temperature' => $temp,
			'messages'    => $messages,
		];

		// Capability-aware JSON format
		$provider = $this->get_setting( 'provider' );
		if ( 'openrouter' === $provider || 'custom' === $provider ) {
			$body_arr['response_format'] = [ 'type' => 'json_object' ];
		}

		$body    = wp_json_encode( $body_arr );
		$headers = [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $api_key,
		];

		if ( strpos( (string) $this->get_setting( 'api_endpoint' ), 'openrouter' ) !== false ) {
			$headers['HTTP-Referer'] = home_url();
			$headers['X-Title']      = get_bloginfo( 'name' );
		}

		$response = wp_remote_post( $endpoint, [
			'timeout' => 120,
			'headers' => $headers,
			'body'    => $body,
		] );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'network_error',
				sprintf(
					/* translators: %s: API network error message */
					__( 'Network error during API request: %s', 'ewa-ai-string-assistant-for-loco-translate' ),
					$response->get_error_message()
				),
				[ 'http_status' => 0, 'retryable' => true ]
			);
		}

		$code    = wp_remote_retrieve_response_code( $response );
		$headers = wp_remote_retrieve_headers( $response );
		$raw     = wp_remote_retrieve_body( $response );
		$data    = json_decode( $raw, true );

		$retry_after = 0;
		if ( isset( $headers['retry-after'] ) ) {
			$retry_after = (int) $headers['retry-after'];
		}

		if ( $code !== 200 ) {
			$msg       = $data['error']['message'] ?? ( 'HTTP ' . $code );
			$retryable = ( $code >= 500 || 429 === $code || 408 === $code );

			if ( in_array( $code, [ 401, 403, 404 ], true ) ) {
				$retryable = false;
			}

			return new \WP_Error(
				'api_error',
				sprintf(
					/* translators: 1: HTTP status code, 2: API error message */
					__( 'API error %1$d: %2$s', 'ewa-ai-string-assistant-for-loco-translate' ),
					$code,
					$msg
				),
				[
					'http_status' => $code,
					'retryable'   => $retryable,
					'retry_after' => $retry_after,
				]
			);
		}

		$content = $data['choices'][0]['message']['content'] ?? '';

		if ( empty( $content ) ) {
			return new \WP_Error(
				'empty_response',
				__( 'AI model returned an empty response.', 'ewa-ai-string-assistant-for-loco-translate' ),
				[ 'http_status' => 200, 'retryable' => true ]
			);
		}

		$usage = $data['usage'] ?? [];

		return [
			'text'   => $content,
			'_usage' => [
				'prompt'     => (int) ( $usage['prompt_tokens']     ?? 0 ),
				'completion' => (int) ( $usage['completion_tokens'] ?? 0 ),
				'total'      => (int) ( $usage['total_tokens']      ?? 0 ),
			],
		];
	}

	/**
	 * Извикване на Ollama крайна точка.
	 */
	private function call_ollama( string $prompt, array $args ) {
		$endpoint = $this->build_endpoint_url( 'api/chat' );
		$model    = $this->get_setting( 'model' );
		$temp     = isset( $args['temperature'] ) ? (float) $args['temperature'] : (float) $this->get_setting( 'temperature', 0.3 );

		$messages = [];
		if ( ! empty( $args['system_prompt'] ) ) {
			$messages[] = [ 'role' => 'system', 'content' => (string) $args['system_prompt'] ];
		}
		$messages[] = [ 'role' => 'user', 'content' => $prompt ];

		$body_arr = [
			'model'    => $model,
			'stream'   => false,
			'format'   => 'json',
			'options'  => [ 'temperature' => $temp ],
			'messages' => $messages,
		];

		$response = wp_remote_post( $endpoint, [
			'timeout' => 180,
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( $body_arr ),
		] );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'network_error',
				sprintf(
					/* translators: %s: Ollama error message */
					__( 'Connection error with Ollama: %s', 'ewa-ai-string-assistant-for-loco-translate' ),
					$response->get_error_message()
				),
				[ 'http_status' => 0, 'retryable' => true ]
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code !== 200 || empty( $data['message']['content'] ) ) {
			$retryable = ( $code >= 500 || 429 === $code || 408 === $code || 0 === $code );
			return new \WP_Error(
				'ollama_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Ollama API error (HTTP %d)', 'ewa-ai-string-assistant-for-loco-translate' ),
					$code
				),
				[ 'http_status' => $code, 'retryable' => $retryable ]
			);
		}

		return [
			'text'   => $data['message']['content'],
			'_usage' => [
				'prompt'     => (int) ( $data['prompt_eval_count'] ?? 0 ),
				'completion' => (int) ( $data['eval_count']        ?? 0 ),
				'total'      => (int) ( ( $data['prompt_eval_count'] ?? 0 ) + ( $data['eval_count'] ?? 0 ) ),
			],
		];
	}

	/**
	 * Зарежда наличните модели директно от конфигурирания доставчик.
	 *
	 * @return array|\WP_Error
	 */
	public function fetch_models() {
		$provider = $this->get_setting( 'provider' );
		$api_key  = $this->get_setting( 'api_key' );

		if ( 'ollama' === $provider ) {
			$url      = $this->build_endpoint_url( 'api/tags' );
			$response = wp_remote_get( $url, [ 'timeout' => 15 ] );

			if ( is_wp_error( $response ) ) {
				return new \WP_Error(
					'network_error',
					sprintf(
						/* translators: %s: error message */
						__( 'Error loading models from Ollama: %s', 'ewa-ai-string-assistant-for-loco-translate' ),
						$response->get_error_message()
					)
				);
			}

			$data   = json_decode( wp_remote_retrieve_body( $response ), true );
			$models = [];
			foreach ( ( $data['models'] ?? [] ) as $m ) {
				$models[] = [ 'id' => $m['name'], 'name' => $m['name'] ];
			}
			return $models;
		}

		// OpenRouter / OpenAI compatible
		$url      = $this->build_endpoint_url( 'models' );
		$response = wp_remote_get( $url, [
			'timeout' => 20,
			'headers' => [
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			],
		] );

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'network_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Error loading models: %s', 'ewa-ai-string-assistant-for-loco-translate' ),
					$response->get_error_message()
				)
			);
		}

		$data   = json_decode( wp_remote_retrieve_body( $response ), true );
		$raw    = $data['data'] ?? [];
		$models = [];

		foreach ( $raw as $m ) {
			$id = $m['id'] ?? '';
			if ( empty( $id ) ) {
				continue;
			}
			$arch = $m['architecture']['modality'] ?? '';
			if ( $arch && ! in_array( $arch, [ 'text->text', 'text+image->text', '' ], true ) ) {
				continue;
			}
			$models[] = [
				'id'          => $id,
				'name'        => $m['name'] ?? $id,
				'context'     => $m['context_length'] ?? null,
				'pricing_in'  => $m['pricing']['prompt'] ?? null,
				'pricing_out' => $m['pricing']['completion'] ?? null,
			];
		}

		usort( $models, function ( $a, $b ) {
			return strcmp( $a['id'], $b['id'] );
		} );

		return $models;
	}
}
