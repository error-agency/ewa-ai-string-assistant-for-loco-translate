<?php
namespace ErrorWebAgency\EwaAIStringAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Клиент за връзка с AI провайдъри (OpenRouter, Ollama, OpenAI-compatible).
 * Поддържа детерминирано съпоставяне по ID, структурирани заявки и машина-читами грешки.
 */
class Api_Client {

	private $settings;
	private $overrides = [];

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
	public function build_endpoint_url( string $suffix ) {
		$raw = trim( (string) $this->get_setting( 'api_endpoint' ) );
		$raw = rtrim( $raw, '/' );

		// Почистване на предварително включени известни суфикси
		$raw = preg_replace( '#/(chat/completions|api/chat|api/tags|models)$#i', '', $raw );

		return $raw . '/' . ltrim( $suffix, '/' );
	}

	/**
	 * Превежда партида от записи чрез избрания AI провайдър.
	 *
	 * @param  array  $batch_items        Елементи от Po_Handler::get_untranslated().
	 * @param  string $target_lang        Целеви език.
	 * @param  int    $nplurals           Брой множествени форми.
	 * @param  string $correction_prompt Инструкция за корекция при повторен опит.
	 * @param  float|null $temp_override Временна температура.
	 * @return array|\WP_Error            Масив [ index => translated_value|array ] + '_usage'
	 */
	public function translate_batch( array $batch_items, string $target_lang, int $nplurals = 2, string $correction_prompt = '', $temp_override = null ) {
		if ( empty( $batch_items ) ) {
			return [];
		}

		$provider = $this->get_setting( 'provider' );

		switch ( $provider ) {
			case 'ollama':
				return $this->call_ollama( $batch_items, $target_lang, $nplurals, $correction_prompt, $temp_override );
			case 'openrouter':
			default:
				return $this->call_openai_compatible( $batch_items, $target_lang, $nplurals, $correction_prompt, $temp_override );
		}
	}

	/**
	 * Подготвя структурираните заявки с ID по оригинален PO индекс.
	 *
	 * @param  array $batch_items
	 * @return array [ 'payload' => array, 'map' => array ]
	 */
	private function prepare_payload( array $batch_items ) {
		$payload = [];
		$map     = [];

		foreach ( $batch_items as $item ) {
			$id_str         = 'entry_' . $item['index'];
			$map[ $id_str ] = $item;

			$unit = [ 'id' => $id_str ];
			if ( ! empty( $item['msgctxt'] ) ) {
				$unit['context'] = $item['msgctxt'];
			}

			if ( null !== $item['plural'] ) {
				$unit['singular'] = $item['msgid'];
				$unit['plural']   = $item['plural'];
			} else {
				$unit['text'] = $item['msgid'];
			}

			$payload[] = $unit;
		}

		return [
			'payload' => $payload,
			'map'     => $map,
		];
	}

	/**
	 * OpenRouter / OpenAI-compatible endpoint call.
	 */
	private function call_openai_compatible( array $batch_items, string $target_lang, int $nplurals, string $correction_prompt, $temp_override ) {
		$endpoint   = $this->build_endpoint_url( 'chat/completions' );
		$api_key    = $this->get_setting( 'api_key' );
		$model      = $this->get_setting( 'model' );
		$temp       = null !== $temp_override ? (float) $temp_override : (float) $this->get_setting( 'temperature', 0.3 );
		$sys_prompt = $this->get_setting( 'system_prompt' );

		if ( empty( $sys_prompt ) ) {
			$sys_prompt = Settings::default_system_prompt( $target_lang, $nplurals );
		}

		if ( ! empty( $correction_prompt ) ) {
			$sys_prompt .= "\nIMPORTANT CORRECTION FOR THIS RETRY: " . $correction_prompt;
		}

		$prepared     = $this->prepare_payload( $batch_items );
		$user_content = 'Translate the following items to ' . $target_lang . ":\n" .
						wp_json_encode( $prepared['payload'], JSON_UNESCAPED_UNICODE );

		$body_arr = [
			'model'       => $model,
			'temperature' => $temp,
			'messages'    => [
				[ 'role' => 'system', 'content' => $sys_prompt ],
				[ 'role' => 'user',   'content' => $user_content ],
			],
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

		if ( strpos( $this->get_setting( 'api_endpoint' ), 'openrouter' ) !== false ) {
			$headers['HTTP-Referer'] = home_url();
			$headers['X-Title']      = get_bloginfo( 'name' );
		}

		$response = wp_remote_post( $endpoint, [
			'timeout' => 120,
			'headers' => $headers,
			'body'    => $body,
		] );

		return $this->parse_openai_response( $response, $prepared['map'], $nplurals );
	}

	/**
	 * Ollama call.
	 */
	private function call_ollama( array $batch_items, string $target_lang, int $nplurals, string $correction_prompt, $temp_override ) {
		$endpoint   = $this->build_endpoint_url( 'api/chat' );
		$model      = $this->get_setting( 'model' );
		$temp       = null !== $temp_override ? (float) $temp_override : (float) $this->get_setting( 'temperature', 0.3 );
		$sys_prompt = $this->get_setting( 'system_prompt' );

		if ( empty( $sys_prompt ) ) {
			$sys_prompt = Settings::default_system_prompt( $target_lang, $nplurals );
		}

		if ( ! empty( $correction_prompt ) ) {
			$sys_prompt .= "\nIMPORTANT CORRECTION FOR THIS RETRY: " . $correction_prompt;
		}

		$prepared     = $this->prepare_payload( $batch_items );
		$user_content = 'Translate the following items to ' . $target_lang . ":\n" .
						wp_json_encode( $prepared['payload'], JSON_UNESCAPED_UNICODE );

		$body_arr = [
			'model'    => $model,
			'stream'   => false,
			'format'   => 'json',
			'options'  => [ 'temperature' => $temp ],
			'messages' => [
				[ 'role' => 'system', 'content' => $sys_prompt ],
				[ 'role' => 'user',   'content' => $user_content ],
			],
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

		return $this->parse_raw_content( $data['message']['content'], $prepared['map'], $nplurals );
	}

	/**
	 * Парсира и валидира отговора от OpenAI-съвместими API-та.
	 */
	private function parse_openai_response( $response, array $item_map, int $nplurals ) {
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

		$parsed = $this->parse_raw_content( $content, $item_map, $nplurals );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$usage           = $data['usage'] ?? [];
		$parsed['_usage'] = [
			'prompt'     => (int) ( $usage['prompt_tokens']     ?? 0 ),
			'completion' => (int) ( $usage['completion_tokens'] ?? 0 ),
			'total'      => (int) ( $usage['total_tokens']      ?? 0 ),
		];

		return $parsed;
	}

	/**
	 * Извлича, валидира и съпоставя детерминирано JSON отговора по ID.
	 */
	private function parse_raw_content( string $content, array $item_map, int $nplurals ) {
		$content = preg_replace( '/^```(?:json)?\s*/m', '', $content );
		$content = preg_replace( '/\s*```$/m', '', $content );
		$content = trim( $content );

		$decoded = json_decode( $content, true );

		$items_arr = null;
		if ( is_array( $decoded ) ) {
			if ( isset( $decoded['translations'] ) && is_array( $decoded['translations'] ) ) {
				$items_arr = $decoded['translations'];
			} elseif ( isset( $decoded[0] ) ) {
				$items_arr = $decoded;
			}
		}

		if ( null === $items_arr ) {
			return new \WP_Error(
				'invalid_json_structure',
				__( 'The response from the AI does not contain a valid JSON object or translations array.', 'ewa-ai-string-assistant-for-loco-translate' ),
				[ 'http_status' => 200, 'retryable' => true ]
			);
		}

		$expected_ids = array_keys( $item_map );
		$returned_ids = [];
		$result_map   = [];

		foreach ( $items_arr as $row ) {
			if ( ! is_array( $row ) || empty( $row['id'] ) ) {
				return new \WP_Error(
					'missing_item_id',
					__( 'An item in the AI response is missing a valid "id" field.', 'ewa-ai-string-assistant-for-loco-translate' ),
					[ 'http_status' => 200, 'retryable' => true ]
				);
			}

			$id = (string) $row['id'];

			if ( isset( $returned_ids[ $id ] ) ) {
				return new \WP_Error(
					'duplicate_item_id',
					sprintf(
						/* translators: %s: item ID */
						__( 'AI response contains duplicate ID: "%s".', 'ewa-ai-string-assistant-for-loco-translate' ),
						$id
					),
					[ 'http_status' => 200, 'retryable' => true ]
				);
			}

			if ( ! isset( $item_map[ $id ] ) ) {
				return new \WP_Error(
					'unknown_item_id',
					sprintf(
						/* translators: %s: item ID */
						__( 'AI response contains unknown ID: "%s".', 'ewa-ai-string-assistant-for-loco-translate' ),
						$id
					),
					[ 'http_status' => 200, 'retryable' => true ]
				);
			}

			$returned_ids[ $id ] = true;
			$orig_item           = $item_map[ $id ];
			$po_index            = $orig_item['index'];

			if ( null !== $orig_item['plural'] ) {
				$val = $row['translations'] ?? ( $row['translation'] ?? null );
				if ( ! is_array( $val ) ) {
					return new \WP_Error(
						'invalid_plural_type',
						sprintf(
							/* translators: %s: item ID */
							__( 'For plural entry "%s", a single string was returned instead of an array.', 'ewa-ai-string-assistant-for-loco-translate' ),
							$id
						),
						[ 'http_status' => 200, 'retryable' => true ]
					);
				}

				$plural_check = Translation_Validator::validate_plural(
					$orig_item['msgid'],
					$orig_item['plural'],
					$val,
					$nplurals
				);

				if ( is_wp_error( $plural_check ) ) {
					return new \WP_Error(
						'validation_failed',
						sprintf(
							/* translators: 1: item ID, 2: validation error message */
							__( 'Validation of plural translation for "%1$s" failed: %2$s', 'ewa-ai-string-assistant-for-loco-translate' ),
							$id,
							$plural_check->get_error_message()
						),
						[ 'http_status' => 200, 'retryable' => true, 'failed_id' => $id ]
					);
				}

				$result_map[ $po_index ] = array_values( $val );

			} else {
				$val = $row['translation'] ?? ( $row['text'] ?? null );
				if ( ! is_string( $val ) || '' === trim( $val ) ) {
					return new \WP_Error(
						'invalid_singular_type',
						sprintf(
							/* translators: %s: item ID */
							__( 'For entry "%s", an invalid translation was returned.', 'ewa-ai-string-assistant-for-loco-translate' ),
							$id
						),
						[ 'http_status' => 200, 'retryable' => true ]
					);
				}

				$pair_check = Translation_Validator::validate_pair( $orig_item['msgid'], $val );
				if ( is_wp_error( $pair_check ) ) {
					return new \WP_Error(
						'validation_failed',
						sprintf(
							/* translators: 1: item ID, 2: validation error message */
							__( 'Validation of translation for "%1$s" failed: %2$s', 'ewa-ai-string-assistant-for-loco-translate' ),
							$id,
							$pair_check->get_error_message()
						),
						[ 'http_status' => 200, 'retryable' => true, 'failed_id' => $id ]
					);
				}

				$result_map[ $po_index ] = $val;
			}
		}

		foreach ( $expected_ids as $req_id ) {
			if ( ! isset( $returned_ids[ $req_id ] ) ) {
				return new \WP_Error(
					'missing_requested_id',
					sprintf(
						/* translators: %s: expected item ID */
						__( 'AI response is missing expected ID: "%s".', 'ewa-ai-string-assistant-for-loco-translate' ),
						$req_id
					),
					[ 'http_status' => 200, 'retryable' => true ]
				);
			}
		}

		return $result_map;
	}

	/**
	 * Зарежда наличните модели от провайдъра.
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

		usort( $models, fn( $a, $b ) => strcmp( $a['id'], $b['id'] ) );

		return $models;
	}
}
