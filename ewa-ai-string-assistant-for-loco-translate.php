<?php
/**
 * Plugin Name:       EWA AI String Assistant for Loco Translate
 * Plugin URI:        https://github.com/error-agency/ewa-ai-string-assistant-for-loco-translate
 * Description:       Adds AI-assisted string translation to Loco Translate using OpenRouter, Ollama, or a custom OpenAI-compatible endpoint.
 * Version:           1.7.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Error Web Agency
 * Author URI:        https://error.bg/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ewa-ai-string-assistant-for-loco-translate
 * Domain Path:       /languages
 * Requires Plugins:  loco-translate
 */

/**
 * Copyright (C) 2026 Error Web Agency (EWA)
 *
 * Lead Developer: K2D
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * See the GNU General Public License for more details.
 */

namespace ErrorWebAgency\EwaAIStringAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EWAAS_VERSION', '1.7.0' );
define( 'EWAAS_PATH', plugin_dir_path( __FILE__ ) );
define( 'EWAAS_URL', plugin_dir_url( __FILE__ ) );
define( 'EWAAS_BASENAME', plugin_basename( __FILE__ ) );

// Backward-compatible constant aliases.
if ( ! defined( 'EWA_AI_TRANSLATOR_FOR_LOCO_TRANSLATE_VERSION' ) ) {
	define( 'EWA_AI_TRANSLATOR_FOR_LOCO_TRANSLATE_VERSION', EWAAS_VERSION );
}
if ( ! defined( 'EWA_AI_TRANSLATOR_FOR_LOCO_TRANSLATE_PATH' ) ) {
	define( 'EWA_AI_TRANSLATOR_FOR_LOCO_TRANSLATE_PATH', EWAAS_PATH );
}
if ( ! defined( 'EWA_AI_TRANSLATOR_FOR_LOCO_TRANSLATE_URL' ) ) {
	define( 'EWA_AI_TRANSLATOR_FOR_LOCO_TRANSLATE_URL', EWAAS_URL );
}
if ( ! defined( 'EWA_AI_TRANSLATOR_FOR_LOCO_TRANSLATE_BASENAME' ) ) {
	define( 'EWA_AI_TRANSLATOR_FOR_LOCO_TRANSLATE_BASENAME', EWAAS_BASENAME );
}

final class Plugin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	private function includes() {
		require_once EWAAS_PATH . 'includes/class-settings.php';
		require_once EWAAS_PATH . 'includes/class-translation-validator.php';
		require_once EWAAS_PATH . 'includes/class-api-client.php';
		require_once EWAAS_PATH . 'includes/class-po-handler.php';
		require_once EWAAS_PATH . 'includes/class-ajax.php';
		require_once EWAAS_PATH . 'admin/class-admin.php';
	}

	private function init_hooks() {
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_action( 'plugins_loaded', [ $this, 'check_requirements' ] );

		Settings::instance();
		Ajax::instance();
		Admin::instance();
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'ewa-ai-string-assistant-for-loco-translate',
			false,
			dirname( EWAAS_BASENAME ) . '/languages'
		);
	}

	public function check_requirements() {
		// Only display admin notice on plugins.php or our settings page to comply with WordPress.org Guideline 11.
		add_action( 'admin_notices', [ $this, 'render_admin_notices' ] );
	}

	public function render_admin_notices() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen_id = $screen ? $screen->id : '';

		$allowed_screens = [
			'plugins',
			'settings_page_ewa-ai-string-assistant-for-loco-translate',
		];

		if ( ! in_array( $screen_id, $allowed_screens, true ) ) {
			return;
		}

		// 1. PHP Version
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>';
			echo '<strong>' . esc_html__( 'EWA AI String Assistant for Loco Translate', 'ewa-ai-string-assistant-for-loco-translate' ) . ':</strong> ';
			/* translators: %s: Current PHP version. */
			printf( esc_html__( 'This plugin requires PHP version 7.4 or higher. Your server is running version %s.', 'ewa-ai-string-assistant-for-loco-translate' ), esc_html( PHP_VERSION ) );
			echo '</p></div>';
		}

		// 2. WordPress Version
		global $wp_version;
		if ( version_compare( $wp_version, '6.0', '<' ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>';
			echo '<strong>' . esc_html__( 'EWA AI String Assistant for Loco Translate', 'ewa-ai-string-assistant-for-loco-translate' ) . ':</strong> ';
			/* translators: %s: Current WordPress version. */
			printf( esc_html__( 'This plugin requires WordPress version 6.0 or higher. You are running version %s.', 'ewa-ai-string-assistant-for-loco-translate' ), esc_html( $GLOBALS['wp_version'] ) );
			echo '</p></div>';
		}

		// 3. Loco Translate Requirement
		if ( ! class_exists( '\Loco_data_Settings' ) && ! class_exists( 'Loco_data_Settings' ) ) {
			echo '<div class="notice notice-warning is-dismissible"><p>';
			echo '<strong>' . esc_html__( 'EWA AI String Assistant for Loco Translate', 'ewa-ai-string-assistant-for-loco-translate' ) . ':</strong> ';
			esc_html_e( 'Loco Translate plugin is required for this plugin to work.', 'ewa-ai-string-assistant-for-loco-translate' );
			echo '</p></div>';
		}
	}
}

function ewaas_plugin() {
	return Plugin::instance();
}
ewaas_plugin();

// Backward compatibility alias.
if ( ! function_exists( 'ewa_ai_translator_plugin' ) ) {
	function ewa_ai_translator_plugin() {
		return ewaas_plugin();
	}
}
