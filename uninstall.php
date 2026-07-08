<?php
/**
 * Fired when the user clicks the "Delete" link for the CampaignBay plugin.
 *
 * @link       https://campaignbay.github.io
 * @since      1.0.0
 * @package    WPAB_CampaignBay
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

define( 'CAMPAIGNBAY_OPTION_NAME', 'campaignbay' );
define( 'CAMPAIGNBAY_TEXT_DOMAIN', 'campaignbay' );

wpab_cb_run_uninstall();

/**
 * The main controller function for the uninstallation process.
 *
 * @since 1.1.0
 */
function wpab_cb_run_uninstall() {
	// Get settings to check the "Delete All Data" flag.
	$options = get_option( CAMPAIGNBAY_OPTION_NAME );

	// The Gatekeeper Check: Only proceed if user opted in.
	if ( ! empty( $options['advanced_deleteAllOnUninstall'] ) && true === $options['advanced_deleteAllOnUninstall'] ) {
		wpab_cb_drop_custom_tables();
		wpab_cb_delete_plugin_options();
		wpab_cb_cleanup_user_data();
		wpab_cb_remove_capabilities();
	}
}

/**
 * Drop Custom Database Tables
 *
 * @since 1.1.0
 */
function wpab_cb_drop_custom_tables() {
	global $wpdb;

	$tables = array(
		$wpdb->prefix . CAMPAIGNBAY_TEXT_DOMAIN . '_campaigns',
		$wpdb->prefix . CAMPAIGNBAY_TEXT_DOMAIN . '_logs',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
}

/**
 * Delete Plugin Options
 *
 * @since 1.1.0
 */
function wpab_cb_delete_plugin_options() {
	delete_option( CAMPAIGNBAY_OPTION_NAME );

	// If you have transients, delete them here too
	// delete_transient( 'campaignbay_some_cache' );
}

/**
 * Cleanup User Meta Data
 *
 * @since 1.1.0
 */
function wpab_cb_cleanup_user_data() {
	global $wpdb;

	$meta_keys = array(
		'_campaignbay_has_seen_guide',
		'_campaignbay_onboarding_first_campaign',
	);

	$placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );

	$query = $wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ( $placeholders )",
		$meta_keys
	);

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	$wpdb->query( $query );
}

/**
 * Remove Custom Capabilities
 *
 * @since 1.1.0
 */
function wpab_cb_remove_capabilities() {
	$editable_roles = get_editable_roles();

	foreach ( $editable_roles as $role_name => $role_info ) {
		$role = get_role( $role_name );
		if ( $role && $role->has_cap( 'manage_campaignbay' ) ) {
			$role->remove_cap( 'manage_campaignbay' );
		}
	}
}
