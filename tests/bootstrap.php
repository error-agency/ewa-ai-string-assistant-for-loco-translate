<?php
/**
 * Bootstrap for local PHP CLI testing of EWA AI String Assistant for Loco Translate (Error Web Agency).
 * Stubs required WordPress functions when running outside of WordPress core test suite.
 */

if ( ! defined( 'DOING_TESTS' ) ) {
	define( 'DOING_TESTS', true );
}
if ( ! isset( $GLOBALS['wp_version'] ) ) {
	$GLOBALS['wp_version'] = '7.1';
}
if ( ! class_exists( 'Loco_data_Settings' ) ) {
	class Loco_data_Settings {}
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', __DIR__ . '/fixtures/wp-content' );
}
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', __DIR__ . '/fixtures/wp-content/plugins' );
}

$GLOBALS['wp_options'] = [];

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}
}

if ( ! function_exists( 'register_setting' ) ) {
	function register_setting( $group, $name, $args = [] ) {}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return $GLOBALS['wp_options'][ $name ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['wp_options'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( $name, $value = '', $deprecated = '', $autoload = 'yes' ) {
		$GLOBALS['wp_options'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $transient, $value, $expiration = 0 ) {
		$GLOBALS['wp_options'][ '_transient_' . $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $transient ) {
		return $GLOBALS['wp_options'][ '_transient_' . $transient ] ?? false;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $transient ) {
		unset( $GLOBALS['wp_options'][ '_transient_' . $transient ] );
		return true;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return ( $thing instanceof WP_Error );
	}
}

if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( $path ) {
		$path = str_replace( '\\', '/', $path );
		$path = preg_replace( '|/+|', '/', $path );
		return $path;
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return rtrim( $string, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = [] ) {
		if ( is_object( $args ) ) {
			$r = get_object_vars( $args );
		} elseif ( is_array( $args ) ) {
			$r = $args;
		} else {
			$r = [];
		}
		if ( is_array( $defaults ) ) {
			return array_merge( $defaults, $r );
		}
		return $r;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $key ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return trim( (string) $url );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'sanitize_url' ) ) {
	function sanitize_url( $url ) {
		return esc_url_raw( $url );
	}
}

if ( ! function_exists( 'path_is_absolute' ) ) {
	function path_is_absolute( $path ) {
		if ( '' === $path ) {
			return false;
		}
		if ( '/' === $path[0] || '\\' === $path[0] ) {
			return true;
		}
		if ( strlen( $path ) > 2 && ctype_alpha( $path[0] ) && ':' === $path[1] ) {
			return true;
		}
		return false;
	}
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
	function wp_send_json_success( $data = null, $status_code = null, $options = 0 ) {
		$response = [ 'success' => true ];
		if ( isset( $data ) ) {
			$response['data'] = $data;
		}
		echo json_encode( $response, $options );
		if ( ! defined( 'DOING_TESTS' ) ) {
			exit;
		}
	}
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
	function wp_send_json_error( $data = null, $status_code = null, $options = 0 ) {
		$response = [ 'success' => false ];
		if ( isset( $data ) ) {
			$response['data'] = $data;
		}
		echo json_encode( $response, $options );
		if ( ! defined( 'DOING_TESTS' ) ) {
			exit;
		}
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( intval( $maybeint ) );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url() {
		return 'http://example.org';
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) {
		return 'Test Site';
	}
}

if ( ! function_exists( 'get_theme_root' ) ) {
	function get_theme_root() {
		return WP_CONTENT_DIR . '/themes';
	}
}

if ( ! function_exists( 'wp_rand' ) ) {
	function wp_rand( $min = 0, $max = 0 ) {
		return rand( $min, $max );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string, $remove_breaks = false ) {
		$string = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $string );
		$string = strip_tags( $string );
		return trim( $string );
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	function wp_delete_file( $file ) {
		if ( file_exists( $file ) ) {
			return @unlink( $file );
		}
		return false;
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return dirname( $file ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'http://example.org/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = false ) {
		return true;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap ) {
		return true;
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo esc_html__( $text, $domain );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo esc_attr__( $text, $domain );
	}
}

if ( ! function_exists( '_e' ) ) {
	function _e( $text, $domain = 'default' ) {
		echo __( $text, $domain );
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, $echo = true ) {
		$result = ( (string) $checked === (string) $current ) ? ' checked="checked"' : '';
		if ( $echo ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, $echo = true ) {
		$result = ( (string) $selected === (string) $current ) ? ' selected="selected"' : '';
		if ( $echo ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'settings_errors' ) ) {
	function settings_errors( $setting = '', $sanitize = false, $hide_on_update = false ) {}
}

if ( ! function_exists( 'settings_fields' ) ) {
	function settings_fields( $option_group ) {
		echo '<input type="hidden" name="option_page" value="' . esc_attr( $option_group ) . '" />';
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null ) {
		$button = '<input type="submit" name="' . esc_attr( $name ) . '" class="button button-' . esc_attr( $type ) . '" value="' . esc_attr( $text ?: 'Save Changes' ) . '" />';
		if ( $wrap ) {
			$button = '<p class="submit">' . $button . '</p>';
		}
		echo $button;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '', $scheme = 'admin' ) {
		return 'http://example.org/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		return 'test_nonce_ewaas';
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		return true;
	}
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
	function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) {
		return true;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
	}
}

if ( ! function_exists( 'add_options_page' ) ) {
	function add_options_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null ) {
		return 'settings_page_' . $menu_slug;
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src = '', $deps = [], $ver = false, $in_footer = false ) {}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, $src = '', $deps = [], $ver = false, $media = 'all' ) {}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( $handle, $object_name, $l10n ) {}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( $url, $args = [] ) {
		if ( isset( $GLOBALS['wp_mock_post_handler'] ) && is_callable( $GLOBALS['wp_mock_post_handler'] ) ) {
			return call_user_func( $GLOBALS['wp_mock_post_handler'], $url, $args );
		}
		return [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => '{"choices":[{"message":{"content":"{\"translations\":[]}"}}]}',
			'headers'  => [],
		];
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = [] ) {
		if ( isset( $GLOBALS['wp_mock_get_handler'] ) && is_callable( $GLOBALS['wp_mock_get_handler'] ) ) {
			return call_user_func( $GLOBALS['wp_mock_get_handler'], $url, $args );
		}
		return [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => '{"data":[{"id":"openai/gpt-4o-mini","name":"GPT-4o Mini"}]}',
			'headers'  => [],
		];
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		if ( is_wp_error( $response ) || ! is_array( $response ) || ! isset( $response['response']['code'] ) ) {
			return '';
		}
		return (int) $response['response']['code'];
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		if ( is_wp_error( $response ) || ! is_array( $response ) || ! isset( $response['body'] ) ) {
			return '';
		}
		return (string) $response['body'];
	}
}

if ( ! function_exists( 'wp_remote_retrieve_headers' ) ) {
	function wp_remote_retrieve_headers( $response ) {
		if ( is_wp_error( $response ) || ! is_array( $response ) || ! isset( $response['headers'] ) ) {
			return [];
		}
		return $response['headers'];
	}
}

if ( ! function_exists( 'wp_supports_ai' ) ) {
	function wp_supports_ai() {
		return $GLOBALS['wp_mock_supports_ai'] ?? true;
	}
}

if ( ! class_exists( 'Mock_WP_AI_Prompt_Builder' ) ) {
	class Mock_WP_AI_Prompt_Builder {
		public $instruction = '';
		public $temp        = null;
		public $models      = [];
		public $prompt      = '';

		public function system_instruction( $inst ) {
			$this->instruction = $inst;
			return $this;
		}

		public function temperature( $t ) {
			$this->temp = $t;
			return $this;
		}

		public function preferred_models( $m ) {
			$this->models = $m;
			return $this;
		}

		public function is_supported_for_text_generation() {
			return $GLOBALS['wp_mock_ai_supported_for_text'] ?? true;
		}

		public function generate_text() {
			if ( isset( $GLOBALS['wp_mock_ai_generate_exception'] ) ) {
				throw new \Exception( $GLOBALS['wp_mock_ai_generate_exception'] );
			}
			if ( isset( $GLOBALS['wp_mock_ai_generate_error'] ) ) {
				return new WP_Error( 'ai_client_error', $GLOBALS['wp_mock_ai_generate_error'] );
			}
			if ( isset( $GLOBALS['wp_mock_ai_generate_handler'] ) && is_callable( $GLOBALS['wp_mock_ai_generate_handler'] ) ) {
				return call_user_func( $GLOBALS['wp_mock_ai_generate_handler'], $this->prompt, $this );
			}
			return '{"translations":[]}';
		}
	}
}

if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
	function wp_ai_client_prompt( $prompt = '' ) {
		if ( isset( $GLOBALS['wp_mock_ai_prompt_exception'] ) ) {
			throw new \Exception( $GLOBALS['wp_mock_ai_prompt_exception'] );
		}
		$builder = new Mock_WP_AI_Prompt_Builder();
		$builder->prompt = $prompt;
		return $builder;
	}
}

// Require plugin files
require_once __DIR__ . '/../ewa-ai-string-assistant-for-loco-translate.php';
require_once __DIR__ . '/../includes/class-ai-transport-interface.php';
require_once __DIR__ . '/../includes/class-wp-ai-client-transport.php';
require_once __DIR__ . '/../includes/class-direct-ai-transport.php';
require_once __DIR__ . '/../includes/class-ai-transport-manager.php';
require_once __DIR__ . '/../includes/class-settings.php';
require_once __DIR__ . '/../includes/class-translation-validator.php';
require_once __DIR__ . '/../includes/class-api-client.php';
require_once __DIR__ . '/../includes/class-po-handler.php';
require_once __DIR__ . '/../includes/class-ajax.php';
require_once __DIR__ . '/../admin/class-admin.php';
