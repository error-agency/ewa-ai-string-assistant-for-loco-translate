<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package ErrorWebAgency\EwaAIStringAssistant
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

( function () {
	$delete_options = static function () {
		delete_option( 'ewaas_settings' );
		delete_option( 'ewaas_schema_version' );
		delete_option( 'ewa_settings' );
		delete_option( 'ewa_schema_version' );
		delete_option( 'error_lait_settings' );
		delete_option( 'error_lait_schema_version' );
		delete_option( 'lat_settings' );
	};

	if ( is_multisite() ) {
		$sites = get_sites( [ 'number' => 0 ] );
		if ( ! empty( $sites ) ) {
			foreach ( $sites as $site ) {
				switch_to_blog( (int) $site->blog_id );
				$delete_options();
				restore_current_blog();
			}
		}
	} else {
		$delete_options();
	}
} )();
