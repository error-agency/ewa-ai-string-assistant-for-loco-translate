<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package ErrorWebAgency\LocoAITranslator
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete plugin options for a single site.
 */
function ewa_delete_plugin_options() {
	delete_option( 'ewa_settings' );
	delete_option( 'ewa_schema_version' );
	delete_option( 'error_lait_settings' );
	delete_option( 'error_lait_schema_version' );
	delete_option( 'lat_settings' );
}

// Handle multisite network uninstall vs single site.
if ( is_multisite() ) {
	$ewa_sites = get_sites( [ 'number' => 0 ] );
	if ( ! empty( $ewa_sites ) ) {
		foreach ( $ewa_sites as $ewa_site ) {
			switch_to_blog( (int) $ewa_site->blog_id );
			ewa_delete_plugin_options();
			restore_current_blog();
		}
	}
} else {
	ewa_delete_plugin_options();
}
