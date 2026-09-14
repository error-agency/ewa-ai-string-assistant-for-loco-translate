<?php
namespace ErrorWebAgency\EwaAIStringAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Транспортен слой за официалния WordPress AI Client (WordPress 7.0+).
 * Използва wp_ai_client_prompt() и wp_supports_ai() за комуникация с централно
 * конфигурираните AI доставчици през Settings → Connectors.
 */
class WP_AI_Client_Transport implements AI_Transport_Interface {

	/**
	 * Връща структуриран статус за наличността на WordPress AI Client.
	 *
	 * Възможни резултати:
	 * - 'available'          : Наличен е AI клиент и е конфигуриран съвместим провайдър за текстова генерация.
	 * - 'ai_disabled'        : AI функционалностите са изключени на ниво среда (wp_supports_ai() === false).
	 * - 'client_unavailable' : Функцията wp_ai_client_prompt() не съществува (WordPress < 7.0).
	 * - 'no_text_provider'   : Няма конфигуриран или съвместим доставчик/модел за генерация на текст.
	 *
	 * @return string
	 */
	public static function get_status(): string {
		global $wp_version;
		if ( isset( $wp_version ) && version_compare( $wp_version, '7.0', '<' ) ) {
			return 'client_unavailable';
		}

		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return 'client_unavailable';
		}

		if ( function_exists( 'wp_supports_ai' ) && ! wp_supports_ai() ) {
			return 'ai_disabled';
		}

		try {
			$builder = wp_ai_client_prompt( 'Availability check' );
			if ( is_object( $builder ) && method_exists( $builder, 'is_supported_for_text_generation' ) ) {
				if ( ! $builder->is_supported_for_text_generation() ) {
					return 'no_text_provider';
				}
			}
		} catch ( \Throwable $e ) {
			return 'no_text_provider';
		}

		return 'available';
	}

	/**
	 * Проверява дали WordPress AI Client е напълно готов за работа.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return 'available' === self::get_status();
	}

	/**
	 * Изпълнява заявка за превод през WordPress AI Client.
	 *
	 * @param  string $prompt Потребителски промпт със структурираните за превод елементи.
	 * @param  array  $args   Параметри на заявката.
	 * @return array|\WP_Error
	 */
	public function generate( string $prompt, array $args = [] ) {
		$status = self::get_status();

		if ( 'available' !== $status ) {
			return $this->get_error_for_status( $status );
		}

		try {
			$builder = wp_ai_client_prompt( $prompt );

			// Системни инструкции за превод
			if ( ! empty( $args['system_prompt'] ) && method_exists( $builder, 'using_system_instruction' ) ) {
				$builder->using_system_instruction( (string) $args['system_prompt'] );
			}

			// Температура на генерация
			if ( isset( $args['temperature'] ) && method_exists( $builder, 'using_temperature' ) ) {
				$builder->using_temperature( (float) $args['temperature'] );
			}

			// Опционално предпочитание за модел
			$models = $args['models'] ?? [];
			if ( ! empty( $models ) && is_array( $models ) && method_exists( $builder, 'using_model_preference' ) ) {
				$builder->using_model_preference( ...$models );
			}

			// Извикване на публичния метод за генериране на текст
			if ( method_exists( $builder, 'generate_text' ) ) {
				$result = $builder->generate_text();
			} elseif ( method_exists( $builder, 'generate_text_result' ) ) {
				$text_result = $builder->generate_text_result();
				if ( is_wp_error( $text_result ) ) {
					$result = $text_result;
				} elseif ( is_object( $text_result ) && method_exists( $text_result, 'get_text' ) ) {
					$result = $text_result->get_text();
				} else {
					$result = (string) $text_result;
				}
			} elseif ( method_exists( $builder, 'generate' ) ) {
				$result = $builder->generate();
			} else {
				return new \WP_Error(
					'wp_ai_client_unsupported_builder',
					__( 'WordPress AI Client builder does not support text generation.', 'ewa-ai-string-assistant-for-loco-translate' ),
					[ 'http_status' => 500, 'retryable' => false ]
				);
			}

			if ( is_wp_error( $result ) ) {
				return new \WP_Error(
					'wp_ai_client_generation_error',
					sprintf(
						/* translators: %s: Error message from WordPress AI Client */
						__( 'The configured WordPress AI provider could not generate the translation: %s', 'ewa-ai-string-assistant-for-loco-translate' ),
						$result->get_error_message()
					),
					[ 'http_status' => 500, 'retryable' => true ]
				);
			}

			if ( ! is_string( $result ) || '' === trim( $result ) ) {
				return new \WP_Error(
					'empty_response',
					__( 'AI model returned an empty response.', 'ewa-ai-string-assistant-for-loco-translate' ),
					[ 'http_status' => 200, 'retryable' => true ]
				);
			}

			return [
				'text'   => $result,
				'_usage' => [
					'prompt'     => 0,
					'completion' => 0,
					'total'      => 0,
				],
			];

		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'wp_ai_client_exception',
				sprintf(
					/* translators: %s: Exception message */
					__( 'WordPress AI Client encountered an unexpected error: %s', 'ewa-ai-string-assistant-for-loco-translate' ),
					$e->getMessage()
				),
				[ 'http_status' => 500, 'retryable' => true ]
			);
		}
	}

	/**
	 * Превежда статусния код в съответно Actionable WP_Error съобщение.
	 *
	 * @param  string $status Статусен код от get_status().
	 * @return \WP_Error
	 */
	private function get_error_for_status( string $status ): \WP_Error {
		switch ( $status ) {
			case 'ai_disabled':
				return new \WP_Error(
					'ai_disabled',
					__( 'AI features are disabled in this WordPress environment.', 'ewa-ai-string-assistant-for-loco-translate' ),
					[ 'http_status' => 403, 'retryable' => false ]
				);

			case 'client_unavailable':
				return new \WP_Error(
					'client_unavailable',
					__( 'The WordPress AI Client is not available on this site. Please ensure you are running WordPress 7.0+ or switch to Advanced Direct Connection.', 'ewa-ai-string-assistant-for-loco-translate' ),
					[ 'http_status' => 404, 'retryable' => false ]
				);

			case 'no_text_provider':
			default:
				return new \WP_Error(
					'no_text_provider',
					__( 'No compatible text-generation AI provider is configured in WordPress. Configure an AI provider under Settings → Connectors or select Advanced Direct Connection.', 'ewa-ai-string-assistant-for-loco-translate' ),
					[ 'http_status' => 400, 'retryable' => false ]
				);
		}
	}
}
