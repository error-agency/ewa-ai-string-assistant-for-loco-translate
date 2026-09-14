<?php
namespace ErrorWebAgency\EwaAIStringAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Фабрика и мениджър за избор на активен AI транспортен слой.
 * Определя дали преводите да се изпълняват през WordPress AI Client
 * или през директния HTTP адаптер според настройките и възможностите на средата.
 */
class AI_Transport_Manager {

	/**
	 * Връща активния AI транспортен инстанс.
	 *
	 * @param  array $overrides Опционални параметри за презаписване (например при тест на връзка).
	 * @return AI_Transport_Interface
	 */
	public static function get_active_transport( array $overrides = [] ): AI_Transport_Interface {
		// Ако са подадени явни direct параметри (провайдър, директен endpoint или ключ), ползваме Direct_AI_Transport
		if ( ! empty( $overrides['provider'] ) || ! empty( $overrides['api_endpoint'] ) || ! empty( $overrides['api_key'] ) ) {
			return new Direct_AI_Transport( $overrides );
		}

		$settings = Settings::instance();
		$mode     = $overrides['ai_transport'] ?? $settings->get( 'ai_transport', 'direct' );

		if ( 'wordpress' === $mode && self::is_wp_ai_client_supported() ) {
			return new WP_AI_Client_Transport();
		}

		return new Direct_AI_Transport( $overrides );
	}

	/**
	 * Проверява дали текущата WordPress среда поддържа WordPress AI Client.
	 *
	 * @return bool
	 */
	public static function is_wp_ai_client_supported(): bool {
		return 'client_unavailable' !== WP_AI_Client_Transport::get_status();
	}
}
