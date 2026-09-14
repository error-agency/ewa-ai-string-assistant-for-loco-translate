<?php
namespace ErrorWebAgency\EwaAIStringAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_filter( 'plugin_action_links_' . EWAAS_BASENAME, [ $this, 'plugin_action_links' ] );
		add_action( 'admin_init', [ $this, 'add_privacy_policy_content' ] );
	}

	public function register_menu() {
		add_options_page(
			__( 'EWA AI String Assistant for Loco Translate', 'ewa-ai-string-assistant-for-loco-translate' ),
			__( 'EWA AI String Assistant', 'ewa-ai-string-assistant-for-loco-translate' ),
			'manage_options',
			'ewa-ai-string-assistant-for-loco-translate',
			[ $this, 'render_settings_page' ]
		);
	}

	public function enqueue_assets( $hook ) {
		$css_ver   = file_exists( EWAAS_PATH . 'assets/css/ewa-admin.css' ) ? EWAAS_VERSION . '.' . filemtime( EWAAS_PATH . 'assets/css/ewa-admin.css' ) : EWAAS_VERSION;
		$js_editor = file_exists( EWAAS_PATH . 'assets/js/ewa-loco-editor.js' ) ? EWAAS_VERSION . '.' . filemtime( EWAAS_PATH . 'assets/js/ewa-loco-editor.js' ) : EWAAS_VERSION;
		$js_admin  = file_exists( EWAAS_PATH . 'assets/js/ewa-admin.js' ) ? EWAAS_VERSION . '.' . filemtime( EWAAS_PATH . 'assets/js/ewa-admin.js' ) : EWAAS_VERSION;

		// Settings page
		if ( 'settings_page_ewa-ai-string-assistant-for-loco-translate' === $hook ) {
			wp_enqueue_style(
				'ewaas-admin',
				EWAAS_URL . 'assets/css/ewa-admin.css',
				[ 'dashicons' ],
				$css_ver
			);
			wp_enqueue_script(
				'ewaas-admin',
				EWAAS_URL . 'assets/js/ewa-admin.js',
				[ 'jquery' ],
				$js_admin,
				true
			);
			$js_data = $this->get_js_data();
			wp_localize_script( 'ewaas-admin', 'ewaasAdmin', $js_data );
		}

		// Inject into Loco Translate editor pages.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check to determine admin screen for script enqueuing.
		$current_page   = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check to determine admin screen for script enqueuing.
		$current_action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		$loco_hooks = [
			'loco-translate_page_loco-plugin',
			'loco-translate_page_loco-theme',
			'loco-translate_page_loco-plugin-file-edit',
			'loco-translate_page_loco-theme-file-edit',
			'loco-plugin_page_loco-plugin-file-edit',
			'loco-theme_page_loco-theme-file-edit',
		];

		$is_loco_editor = (
			in_array( $hook, $loco_hooks, true ) ||
			(
				false !== strpos( $current_page, 'loco' ) &&
				'file-edit' === $current_action
			) ||
			(
				false !== strpos( $current_page, 'loco' ) &&
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check to determine admin screen for script enqueuing.
				! empty( $_GET['path'] )
			)
		);

		if ( $is_loco_editor ) {
			wp_enqueue_style(
				'ewaas-loco',
				EWAAS_URL . 'assets/css/ewa-admin.css',
				[ 'dashicons' ],
				$css_ver
			);
			wp_enqueue_script(
				'ewaas-loco',
				EWAAS_URL . 'assets/js/ewa-loco-editor.js',
				[ 'jquery' ],
				$js_editor,
				true
			);
			$loco_data = $this->get_loco_js_data();
			wp_localize_script( 'ewaas-loco', 'ewaasLoco', $loco_data );
		}
	}

	private function get_js_data() {
		$settings = Settings::instance();
		return [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'ewaas_nonce' ),
			'model'     => $settings->get( 'model' ),
			'provider'  => $settings->get( 'provider' ),
			'batchSize' => $settings->get( 'batch_size' ),
			'i18n'      => [
				'translating'  => __( 'Translating…', 'ewa-ai-string-assistant-for-loco-translate' ),
				'done'         => __( 'Done!', 'ewa-ai-string-assistant-for-loco-translate' ),
				'error'        => __( 'Error', 'ewa-ai-string-assistant-for-loco-translate' ),
				'noStrings'    => __( 'No untranslated strings found.', 'ewa-ai-string-assistant-for-loco-translate' ),
				'confirm'      => __( 'This will translate untranslated strings using AI. Please ensure you have a backup of your translation files before proceeding. Continue?', 'ewa-ai-string-assistant-for-loco-translate' ),
				'btnTranslate' => __( 'AI Translate', 'ewa-ai-string-assistant-for-loco-translate' ),
				'btnStop'      => __( 'Stop', 'ewa-ai-string-assistant-for-loco-translate' ),
			],
		];
	}

	private function get_loco_js_data() {
		$base     = $this->get_js_data();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only retrieval of edited PO file path in Loco editor.
		$raw_path = isset( $_GET['path'] ) ? sanitize_text_field( wp_unslash( $_GET['path'] ) ) : '';

		$detected_locale = '';
		if ( $raw_path ) {
			$basename = pathinfo( $raw_path, PATHINFO_FILENAME );
			if ( preg_match( '/[-_]([a-z]{2,3}_[A-Z]{2,3})$/', $basename, $m ) ) {
				$detected_locale = $m[1];
			}
		}

		$base['poPath']         = $raw_path;
		$base['detectedLocale'] = $detected_locale;

		return $base;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		require_once EWAAS_PATH . 'admin/views/settings-page.php';
	}

	public function plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'options-general.php?page=ewa-ai-string-assistant-for-loco-translate' ),
			__( 'Settings', 'ewa-ai-string-assistant-for-loco-translate' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			'<p class="privacy-policy-tutorial">%s</p>' .
			'<strong class="privacy-policy-tutorial">%s</strong>' .
			'<p>%s</p>',
			esc_html__( 'Suggested text for your website privacy policy regarding translation features:', 'ewa-ai-string-assistant-for-loco-translate' ),
			esc_html__( 'EWA AI String Assistant for Loco Translate', 'ewa-ai-string-assistant-for-loco-translate' ),
			esc_html__( 'When administrators use the AI translation features in Loco Translate, source strings and translation parameters are sent directly to the AI service provider chosen by the administrator (OpenRouter, an administrator-configured OpenAI endpoint, a self-hosted or remote Ollama instance, or a custom OpenAI-compatible endpoint). When using OpenRouter, your site URL and site title are also transmitted in standard HTTP request headers for usage attribution. No visitor personal data or frontend tracking information is collected or transmitted.', 'ewa-ai-string-assistant-for-loco-translate' )
		);

		wp_add_privacy_policy_content(
			__( 'EWA AI String Assistant for Loco Translate', 'ewa-ai-string-assistant-for-loco-translate' ),
			wp_kses_post( $content )
		);
	}
}
