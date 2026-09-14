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

		$preferred = [];
		$pref_str  = (string) $this->get_setting( 'preferred_models', '' );
		if ( '' !== $pref_str ) {
			$preferred = array_filter( array_map( 'trim', explode( ',', $pref_str ) ) );
		}

		$temp = null !== $temp_override ? (float) $temp_override : (float) $this->get_setting( 'temperature', 0.3 );

		// Делегиране към активния AI транспортен слой
		$transport = AI_Transport_Manager::get_active_transport( $this->overrides );

		$transport_res = $transport->generate( $user_content, [
			'system_prompt' => $sys_prompt,
			'temperature'   => $temp,
			'models'        => $preferred,
			'target_lang'   => $target_lang,
		] );

		if ( is_wp_error( $transport_res ) ) {
			return $transport_res;
		}

		$raw_content = $transport_res['text'] ?? '';
		if ( empty( $raw_content ) ) {
			return new \WP_Error(
				'empty_response',
				__( 'AI model returned an empty response.', 'ewa-ai-string-assistant-for-loco-translate' ),
				[ 'http_status' => 200, 'retryable' => true ]
			);
		}

		$parsed = $this->parse_raw_content( $raw_content, $prepared['map'], $nplurals );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$usage           = $transport_res['_usage'] ?? [];
		$parsed['_usage'] = [
			'prompt'     => (int) ( $usage['prompt']     ?? 0 ),
			'completion' => (int) ( $usage['completion'] ?? 0 ),
			'total'      => (int) ( $usage['total']      ?? 0 ),
		];

		return $parsed;
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
			$idx            = $item['index'] ?? 0;
			$id_str         = 'entry_' . $idx;
			$map[ $id_str ] = $item;

			$unit = [ 'id' => $id_str ];
			if ( ! empty( $item['msgctxt'] ) ) {
				$unit['context'] = $item['msgctxt'];
			}

			if ( ! empty( $item['plural'] ) ) {
				$unit['singular'] = $item['msgid'];
				$unit['plural']   = $item['plural'];
			} else {
				$unit['text'] = $item['msgid'] ?? '';
			}

			$payload[] = $unit;
		}

		return [
			'payload' => $payload,
			'map'     => $map,
		];
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
			$po_index            = $orig_item['index'] ?? 0;

			if ( ! empty( $orig_item['plural'] ) ) {
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
		$transport = AI_Transport_Manager::get_active_transport( $this->overrides );
		if ( $transport instanceof Direct_AI_Transport ) {
			return $transport->fetch_models();
		}

		return new \WP_Error(
			'wp_ai_client_no_direct_models',
			__( 'Model selection is managed centrally in WordPress Settings → Connectors.', 'ewa-ai-string-assistant-for-loco-translate' )
		);
	}
}
