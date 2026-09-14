<?php
namespace ErrorWebAgency\EwaAIStringAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ajax {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// New prefixed AJAX hooks.
		add_action( 'wp_ajax_ewaas_get_po_info', [ $this, 'get_po_info' ] );
		add_action( 'wp_ajax_ewaas_translate_file', [ $this, 'translate_file' ] );
		add_action( 'wp_ajax_ewaas_cancel_job', [ $this, 'cancel_job' ] );
		add_action( 'wp_ajax_ewaas_fetch_models', [ $this, 'fetch_models' ] );
		add_action( 'wp_ajax_ewaas_test_connection', [ $this, 'test_connection' ] );

		// Backward-compatible AJAX hooks for smooth transition.
		add_action( 'wp_ajax_ewa_get_po_info', [ $this, 'get_po_info' ] );
		add_action( 'wp_ajax_ewa_translate_file', [ $this, 'translate_file' ] );
		add_action( 'wp_ajax_ewa_cancel_job', [ $this, 'cancel_job' ] );
		add_action( 'wp_ajax_ewa_fetch_models', [ $this, 'fetch_models' ] );
		add_action( 'wp_ajax_ewa_test_connection', [ $this, 'test_connection' ] );
	}

	private function verify_security() {
		if ( ! check_ajax_referer( 'ewaas_nonce', 'nonce', false ) && ! check_ajax_referer( 'ewa_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Невалидна сесия (nonce).' ], 403 );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Недостатъчни права.' ], 403 );
		}
	}

	public function get_po_info() {
		$this->verify_security();

		$raw_path = isset( $_POST['po_path'] ) ? sanitize_text_field( wp_unslash( $_POST['po_path'] ) ) : '';
		$po_path  = $this->validate_po_path( $raw_path );
		if ( is_wp_error( $po_path ) ) {
			wp_send_json_error( [ 'message' => $po_path->get_error_message() ] );
		}

		$entries = Po_Handler::parse( $po_path );
		if ( is_wp_error( $entries ) ) {
			wp_send_json_error( [ 'message' => $entries->get_error_message() ] );
		}

		$settings        = Settings::instance();
		$skip_translated = (bool) $settings->get( 'skip_translated', true );
		$untranslated    = Po_Handler::get_untranslated( $entries, $skip_translated );
		$total           = count( $entries );
		$total_untrans   = count( $untranslated );
		$detected_locale = Po_Handler::get_locale( $entries, $po_path );

		wp_send_json_success( [
			'path'            => $po_path,
			'total_entries'   => $total,
			'untranslated'    => $total_untrans,
			'detected_locale' => $detected_locale,
		] );
	}

	public function translate_file() {
		$this->verify_security();

		if ( ! ini_get( 'safe_mode' ) && function_exists( 'set_time_limit' ) ) {
			// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Extended execution time for long-running batch AI translation.
			@set_time_limit( 300 );
		}

		$raw_path    = isset( $_POST['po_path'] ) ? sanitize_text_field( wp_unslash( $_POST['po_path'] ) ) : '';
		$po_path     = $this->validate_po_path( $raw_path );
		$target_lang = sanitize_text_field( wp_unslash( $_POST['target_lang'] ?? 'Bulgarian' ) );
		$job_id      = sanitize_key( $_POST['job_id'] ?? '' );
		$request_id  = sanitize_key( $_POST['request_id'] ?? '' );

		if ( is_wp_error( $po_path ) ) {
			wp_send_json_error( [ 'message' => $po_path->get_error_message() ] );
		}

		if ( empty( $job_id ) ) {
			$job_id = 'ewaas_' . uniqid();
		}

		// ── Специфично състояние на работата (Job Transient State) ───────────
		$transient_key = 'ewaas_job_' . $job_id;
		$job_state     = get_transient( $transient_key );

		if ( ! is_array( $job_state ) ) {
			// Check legacy transient key as fallback
			$job_state = get_transient( 'ewa_job_' . $job_id );
		}

		if ( ! is_array( $job_state ) ) {
			$entries         = Po_Handler::parse( $po_path );
			if ( is_wp_error( $entries ) ) {
				wp_send_json_error( [ 'message' => $entries->get_error_message() ] );
			}
			$settings        = Settings::instance();
			$skip_translated = (bool) $settings->get( 'skip_translated', true );
			$untranslated    = Po_Handler::get_untranslated( $entries, $skip_translated );

			$job_state = [
				'po_path'            => $po_path,
				'file_mtime'         => file_exists( $po_path ) ? filemtime( $po_path ) : 0,
				'target_lang'        => $target_lang,
				'skip_translated'    => $skip_translated,
				'total_initial'      => count( $untranslated ),
				'translated_indices' => [],
				'failed_indices'     => [],
				'processed_indices'  => [],
				'batch_sequence'     => 0,
				'last_request_id'    => '',
				'last_response'      => null,
				'cancelled'          => false,
				'started_at'         => time(),
			];
			set_transient( $transient_key, $job_state, 2 * HOUR_IN_SECONDS );
		}

		// Идемпотентност: Проверка дали този request_id вече е бил обработен
		if ( ! empty( $request_id ) && $job_state['last_request_id'] === $request_id && ! empty( $job_state['last_response'] ) ) {
			wp_send_json_success( $job_state['last_response'] );
		}

		// Проверка за отмяна
		if ( ! empty( $job_state['cancelled'] ) || get_transient( 'ewaas_cancel_' . $job_id ) || get_transient( 'ewa_cancel_' . $job_id ) ) {
			delete_transient( $transient_key );
			delete_transient( 'ewa_job_' . $job_id );
			delete_transient( 'ewaas_cancel_' . $job_id );
			delete_transient( 'ewa_cancel_' . $job_id );
			wp_send_json_success( [
				'done'      => true,
				'cancelled' => true,
				'message'   => 'Преводът бе отменен от потребителя.',
			] );
		}

		// Зареждане на PO файла
		$entries = Po_Handler::parse( $po_path );
		if ( is_wp_error( $entries ) ) {
			wp_send_json_error( [ 'message' => $entries->get_error_message() ] );
		}

		$settings        = Settings::instance();
		$skip_translated = $job_state['skip_translated'];
		$batch_sz        = max( 1, (int) $settings->get( 'batch_size', 40 ) );
		$max_retries     = max( 0, (int) $settings->get( 'max_retries', 3 ) );
		$all_untrans     = Po_Handler::get_untranslated( $entries, $skip_translated );

		// Филтриране на вече обработените в текущата работа записи
		$processed_map = array_flip( $job_state['processed_indices'] );
		$untranslated  = [];
		foreach ( $all_untrans as $item ) {
			if ( ! isset( $processed_map[ $item['index'] ] ) ) {
				$untranslated[] = $item;
			}
		}

		// ── Авто-превод на непреводими низове ─────────────────────────────────
		$auto_translate_map = [];
		$auto_processed     = [];
		foreach ( $untranslated as $item ) {
			$is_non_trans = false;
			if ( $item['plural'] !== null ) {
				if ( Po_Handler::is_non_translatable( $item['msgid'] ) || Po_Handler::is_non_translatable( $item['plural'] ) ) {
					$is_non_trans = true;
				}
			} else {
				if ( Po_Handler::is_non_translatable( $item['msgid'] ) ) {
					$is_non_trans = true;
				}
			}

			if ( $is_non_trans ) {
				$nplurals = Po_Handler::get_nplurals( $entries );
				if ( $item['plural'] !== null ) {
					$plural_vals    = [];
					$plural_vals[0] = $item['msgid'];
					for ( $k = 1; $k < $nplurals; $k++ ) {
						$plural_vals[ $k ] = $item['plural'];
					}
					$auto_translate_map[ $item['index'] ] = $plural_vals;
				} else {
					$auto_translate_map[ $item['index'] ] = $item['msgid'];
				}
				$auto_processed[] = $item['index'];

				if ( ! empty( $item['duplicates'] ) ) {
					foreach ( $item['duplicates'] as $dup_idx ) {
						$auto_translate_map[ $dup_idx ] = $auto_translate_map[ $item['index'] ];
						$auto_processed[]               = $dup_idx;
					}
				}
			}
		}

		if ( ! empty( $auto_translate_map ) ) {
			$entries   = Po_Handler::apply_translations( $entries, $auto_translate_map );
			$save_res  = Po_Handler::save( $po_path, $entries );
			if ( is_wp_error( $save_res ) ) {
				wp_send_json_error( [ 'message' => $save_res->get_error_message() ] );
			}

			$job_state['translated_indices'] = array_merge( $job_state['translated_indices'], $auto_processed );
			$job_state['processed_indices']  = array_merge( $job_state['processed_indices'], $auto_processed );
			$job_state['file_mtime']         = filemtime( $po_path );
			set_transient( $transient_key, $job_state, 2 * HOUR_IN_SECONDS );

			// Обновяване на списъка с оставащи
			$all_untrans   = Po_Handler::get_untranslated( $entries, $skip_translated );
			$processed_map = array_flip( $job_state['processed_indices'] );
			$untranslated  = [];
			foreach ( $all_untrans as $item ) {
				if ( ! isset( $processed_map[ $item['index'] ] ) ) {
					$untranslated[] = $item;
				}
			}
		}

		$remaining_now = count( $untranslated );
		$total_initial = max( 1, $job_state['total_initial'] );

		if ( 0 === $remaining_now ) {
			delete_transient( $transient_key );
			delete_transient( 'ewa_job_' . $job_id );
			$response = [
				'done'       => true,
				'message'    => 'Всички низове са преведени успешно.',
				'translated' => count( array_unique( $job_state['translated_indices'] ) ),
				'skipped'    => count( array_unique( $job_state['failed_indices'] ) ),
				'total'      => $total_initial,
				'percent'    => 100,
				'remaining'  => 0,
			];
			wp_send_json_success( $response );
		}

		// ── Изчисляване на партида по символи и овърхед ───────────────────────
		$batch              = [];
		$current_char_count = 0;
		$max_chars          = 3000;

		foreach ( $untranslated as $item ) {
			if ( count( $batch ) >= $batch_sz ) {
				break;
			}

			$item_len = strlen( $item['msgid'] ) +
						( $item['plural'] !== null ? strlen( $item['plural'] ) : 0 ) +
						( $item['msgctxt'] !== null ? strlen( $item['msgctxt'] ) : 0 ) + 80; // JSON structure overhead

			if ( ! empty( $batch ) && ( $current_char_count + $item_len > $max_chars ) ) {
				break;
			}

			$batch[]             = $item;
			$current_char_count += $item_len;
		}

		$client         = new Api_Client();
		$nplurals       = Po_Handler::get_nplurals( $entries );
		$batch_start_ts = microtime( true );

		// ── Цикъл за повторни опити (Retry Loop) ──────────────────────────────
		$result             = null;
		$last_error_msg     = '';
		$attempt            = 0;
		$token_usage        = [ 'prompt' => 0, 'completion' => 0, 'total' => 0 ];
		$correction_prompt  = '';
		$temp_override      = null;

		while ( $attempt <= $max_retries ) {
			if ( $attempt > 0 ) {
				$sleep_sec = min( (int) pow( 2, $attempt - 1 ), 8 ) + wp_rand( 0, 1000 ) / 1000;
				usleep( (int) ( $sleep_sec * 1000000 ) );
			}

			$try = $client->translate_batch( $batch, $target_lang, $nplurals, $correction_prompt, $temp_override );

			if ( ! is_wp_error( $try ) ) {
				$result = $try;
				if ( isset( $result['_usage'] ) ) {
					$token_usage = $result['_usage'];
					unset( $result['_usage'] );
				}
				break;
			}

			$err_data       = $try->get_error_data();
			$last_error_msg = $try->get_error_message();
			$is_retryable   = $err_data['retryable'] ?? true;

			// Неповтаряеми грешки (напр. 401 Unauthorized, 403 Forbidden, 404) спират веднага
			if ( ! $is_retryable ) {
				wp_send_json_error( [
					'message'   => 'Фатална грешка от AI провайдъра: ' . $last_error_msg,
					'retryable' => false,
				] );
			}

			// Ако валидацията се е провалила, подаваме инструкция за корекция на следващия опит
			if ( 'validation_failed' === $try->get_error_code() ) {
				$correction_prompt = $last_error_msg;
				$temp_override     = 0.0; // Нулева температура за по-строг спазване
			}

			// Уважаване на Retry-After хедъра при 429
			if ( ! empty( $err_data['retry_after'] ) && $err_data['retry_after'] > 0 ) {
				sleep( min( (int) $err_data['retry_after'], 15 ) );
			}

			$attempt++;
		}

		$batch_ms = (int) round( ( microtime( true ) - $batch_start_ts ) * 1000 );

		// ── Прилагане на преводите или отбелязване на неуспех ─────────────────
		$batch_processed_indices = [];
		$batch_translated_count  = 0;
		$batch_failed_count      = 0;
		$save_warning            = '';

		if ( null === $result ) {
			// Партидата е пропаднала трайно след всички retries.
			// Отбелязваме в job_state БЕЗ да добавяме fuzzy флаг в PO файла!
			foreach ( $batch as $item ) {
				$batch_processed_indices[] = $item['index'];
				$job_state['failed_indices'][] = $item['index'];
				$batch_failed_count++;

				if ( ! empty( $item['duplicates'] ) ) {
					foreach ( $item['duplicates'] as $dup_idx ) {
						$batch_processed_indices[] = $dup_idx;
						$job_state['failed_indices'][] = $dup_idx;
						$batch_failed_count++;
					}
				}
			}
		} else {
			$translation_map = [];
			foreach ( $batch as $item ) {
				$po_idx     = $item['index'];
				$translated = $result[ $po_idx ] ?? null;

				if ( null !== $translated ) {
					$translation_map[ $po_idx ] = $translated;
					$batch_processed_indices[]  = $po_idx;
					$job_state['translated_indices'][] = $po_idx;
					$batch_translated_count++;

					if ( ! empty( $item['duplicates'] ) ) {
						foreach ( $item['duplicates'] as $dup_idx ) {
							$translation_map[ $dup_idx ] = $translated;
							$batch_processed_indices[]  = $dup_idx;
							$job_state['translated_indices'][] = $dup_idx;
							$batch_translated_count++;
						}
					}
				} else {
					$batch_processed_indices[]     = $po_idx;
					$job_state['failed_indices'][] = $po_idx;
					$batch_failed_count++;
				}
			}

			if ( ! empty( $translation_map ) ) {
				$entries   = Po_Handler::apply_translations( $entries, $translation_map );
				$saved_res = Po_Handler::save( $po_path, $entries );

				if ( is_wp_error( $saved_res ) ) {
					// При предупреждение за MO компилация
					if ( 'mo_compile_warning' === $saved_res->get_error_code() ) {
						$save_warning = $saved_res->get_error_message();
					} else {
						wp_send_json_error( [ 'message' => $saved_res->get_error_message() ] );
					}
				}
			}
		}

		// Обновяване на Job State
		$job_state['processed_indices']  = array_values( array_unique( array_merge( $job_state['processed_indices'], $batch_processed_indices ) ) );
		$job_state['translated_indices'] = array_values( array_unique( $job_state['translated_indices'] ) );
		$job_state['failed_indices']     = array_values( array_unique( $job_state['failed_indices'] ) );
		$job_state['batch_sequence']++;
		$job_state['file_mtime'] = file_exists( $po_path ) ? filemtime( $po_path ) : 0;

		$all_untrans   = Po_Handler::get_untranslated( $entries, $skip_translated );
		$processed_map = array_flip( $job_state['processed_indices'] );
		$remaining_cnt = 0;
		foreach ( $all_untrans as $item ) {
			if ( ! isset( $processed_map[ $item['index'] ] ) ) {
				$remaining_cnt++;
			}
		}

		$translated_total = count( $job_state['translated_indices'] );
		$skipped_total    = count( $job_state['failed_indices'] );
		$percent          = min( 100, (int) round( ( ( $translated_total + $skipped_total ) / $total_initial ) * 100 ) );
		$is_done          = ( 0 === $remaining_cnt );

		$source_strings = [];
		foreach ( $batch as $b_item ) {
			$source_strings[] = $b_item['msgid'];
		}

		$response = [
			'done'              => $is_done,
			'batch_index'       => $job_state['batch_sequence'],
			'translated'        => $translated_total,
			'skipped'           => $skipped_total,
			'total'             => $total_initial,
			'remaining'         => $remaining_cnt,
			'percent'           => $percent,
			'batch_count'       => count( $batch ),
			'batch_ms'          => $batch_ms,
			'batch_preview'     => array_slice( $source_strings, 0, 3 ),
			'tokens_prompt'     => $token_usage['prompt'],
			'tokens_completion' => $token_usage['completion'],
			'tokens_total'      => $token_usage['total'],
			'save_warning'      => $save_warning,
		];

		if ( ! empty( $request_id ) ) {
			$job_state['last_request_id'] = $request_id;
			$job_state['last_response']   = $response;
		}

		if ( $is_done ) {
			delete_transient( $transient_key );
			delete_transient( 'ewa_job_' . $job_id );
		} else {
			set_transient( $transient_key, $job_state, 2 * HOUR_IN_SECONDS );
		}

		wp_send_json_success( $response );
	}

	public function cancel_job() {
		$this->verify_security();

		$job_id = sanitize_key( $_POST['job_id'] ?? '' );
		if ( empty( $job_id ) ) {
			wp_send_json_error( [ 'message' => 'Не е предоставен job_id.' ] );
		}

		set_transient( 'ewaas_cancel_' . $job_id, 1, 10 * MINUTE_IN_SECONDS );
		set_transient( 'ewa_cancel_' . $job_id, 1, 10 * MINUTE_IN_SECONDS );
		delete_transient( 'ewaas_job_' . $job_id );
		delete_transient( 'ewa_job_' . $job_id );

		wp_send_json_success( [ 'message' => 'Сигналът за отмяна е изпратен.' ] );
	}

	public function fetch_models() {
		$this->verify_security();

		$overrides = [];
		if ( ! empty( $_POST['provider'] ) ) {
			$overrides['provider'] = sanitize_text_field( wp_unslash( $_POST['provider'] ) );
		}
		if ( ! empty( $_POST['api_endpoint'] ) ) {
			$overrides['api_endpoint'] = sanitize_url( wp_unslash( $_POST['api_endpoint'] ) );
		}
		if ( isset( $_POST['api_key'] ) ) {
			$raw_api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ) );
			if ( '' !== $raw_api_key ) {
				$overrides['api_key'] = $raw_api_key;
			}
		}

		$client = new Api_Client( $overrides );
		$models = $client->fetch_models();

		if ( is_wp_error( $models ) ) {
			wp_send_json_error( [ 'message' => $models->get_error_message() ] );
		}

		wp_send_json_success( [ 'models' => $models ] );
	}

	public function test_connection() {
		$this->verify_security();

		$overrides = [];
		if ( ! empty( $_POST['provider'] ) ) {
			$overrides['provider'] = sanitize_text_field( wp_unslash( $_POST['provider'] ) );
		}
		if ( ! empty( $_POST['api_endpoint'] ) ) {
			$overrides['api_endpoint'] = sanitize_url( wp_unslash( $_POST['api_endpoint'] ) );
		}
		if ( isset( $_POST['api_key'] ) ) {
			$raw_api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ) );
			if ( '' !== $raw_api_key ) {
				$overrides['api_key'] = $raw_api_key;
			}
		}
		if ( ! empty( $_POST['model'] ) ) {
			$overrides['model'] = sanitize_text_field( wp_unslash( $_POST['model'] ) );
		}

		$test_batch = [
			[
				'index'      => 0,
				'msgid'      => 'Hello',
				'plural'     => null,
				'msgctxt'    => null,
				'duplicates' => [],
			],
		];

		$client = new Api_Client( $overrides );
		$result = $client->translate_batch( $test_batch, 'Bulgarian' );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		$out = $result[0] ?? '(празно)';

		wp_send_json_success( [
			'message'     => 'Връзката е успешна!',
			'test_input'  => 'Hello',
			'test_output' => is_array( $out ) ? implode( ' / ', $out ) : $out,
		] );
	}

	/**
	 * Строга валидация на PO пътя спрямо канонични позволени директории.
	 *
	 * @param  string $raw
	 * @return string|\WP_Error
	 */
	private function validate_po_path( $raw ) {
		$path = trim( $raw );

		if ( empty( $path ) ) {
			return new \WP_Error( 'empty_path', 'Не е предоставен път до .po файл.' );
		}

		if ( strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) !== 'po' ) {
			return new \WP_Error( 'not_po', 'Файлът трябва да има разширение .po.' );
		}

		$path = wp_normalize_path( $path );

		if ( path_is_absolute( $path ) ) {
			return $this->verify_po_path( $path );
		}

		// Опит за разрешаване спрямо позволените канонични корени
		$rel   = ltrim( $path, '/' );
		$roots = $this->get_allowed_roots();

		foreach ( $roots as $root ) {
			$candidate = wp_normalize_path( $root . '/' . $rel );
			$result    = $this->verify_po_path( $candidate );
			if ( ! is_wp_error( $result ) ) {
				return $result;
			}
		}

		return new \WP_Error(
			'not_found',
			sprintf( 'Файлът не бе открит: %s. Проверете дали съществува и е достъпен.', esc_html( basename( $path ) ) )
		);
	}

	/**
	 * Проверява дали пътят е канонично намиращ се в позволените WordPress/Loco корени.
	 *
	 * @param  string $path
	 * @return string|\WP_Error
	 */
	private function verify_po_path( $path ) {
		$real_path = realpath( $path );
		if ( ! $real_path || ! file_exists( $real_path ) ) {
			return new \WP_Error( 'not_found', 'Файлът не съществува: ' . esc_html( basename( $path ) ) );
		}

		if ( strtolower( pathinfo( $real_path, PATHINFO_EXTENSION ) ) !== 'po' ) {
			return new \WP_Error( 'not_po', 'Файлът не е с разширение .po.' );
		}

		if ( ! is_readable( $real_path ) ) {
			return new \WP_Error( 'not_readable', 'Файлът не е достъпен за четене.' );
		}

		$real_path_norm = wp_normalize_path( $real_path );
		$roots          = $this->get_allowed_roots();
		$is_allowed     = false;

		foreach ( $roots as $root ) {
			$real_root = realpath( $root );
			if ( ! $real_root ) {
				continue;
			}
			$real_root_norm = wp_normalize_path( trailingslashit( $real_root ) );
			if ( 0 === strpos( $real_path_norm, $real_root_norm ) || $real_path_norm === wp_normalize_path( $real_root ) ) {
				$is_allowed = true;
				break;
			}
		}

		if ( ! $is_allowed ) {
			return new \WP_Error( 'outside_allowed_roots', 'Файлът се намира извън разрешените WordPress директории за превод.' );
		}

		return $real_path_norm;
	}

	/**
	 * Връща масив от канонични позволени коренни директории.
	 *
	 * @return array
	 */
	private function get_allowed_roots() {
		$roots = [
			wp_normalize_path( WP_CONTENT_DIR ),
			wp_normalize_path( WP_PLUGIN_DIR ),
			wp_normalize_path( WP_CONTENT_DIR . '/languages' ),
			wp_normalize_path( WP_CONTENT_DIR . '/languages/loco/plugins' ),
			wp_normalize_path( WP_CONTENT_DIR . '/languages/loco/themes' ),
			wp_normalize_path( get_theme_root() ),
		];

		if ( defined( 'WP_LANG_DIR' ) ) {
			$roots[] = wp_normalize_path( WP_LANG_DIR );
		}

		return array_unique( array_filter( $roots ) );
	}
}
