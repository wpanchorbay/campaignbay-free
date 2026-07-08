<?php

namespace WpabCampaignBay\Core;

use WpabCampaignBay\Core\Common;
use WpabCampaignBay\Data\DbManager;
use WpabCampaignBay\Engine\CampaignManager;

/**
 * Fired during plugin activation
 *
 * @link       https://wpanchorbay.com
 * @since      1.0.0
 *
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBay/includes
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBay/includes
 * @author     WP Anchor Bay <wpanchorbay@gmail.com>
 */
class Activator {


	/**
	 * The main activation method.
	 *
	 * This is fired when the plugin is activated.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public static function activate() {

		CampaignManager::get_instance()->clear_cache();

		// Set up the default options if they don't exist.
		if ( ! get_option( CAMPAIGNBAY_OPTION_NAME ) ) {
			Common::get_instance()->update_settings( Common::get_instance()->get_default_settings() );
		}

		// Create custom database tables.
		self::create_custom_tables();

		// Flush rewrite rules to recognize the CPT.
		flush_rewrite_rules();

		// Secure the log directory.
		self::secure_log_directory();

		// Add custom capabilities.
		self::add_plugin_roles_and_capabilities();
	}

	/**
	 * Instantiates the DB Manager and creates the custom tables.
	 *
	 * @since 1.0.0
	 * @access private
	 * @return void
	 */
	private static function create_custom_tables() {
		DbManager::get_instance()->create_tables();
	}

	/**
	 * Secures the log directory by creating an .htaccess file and an index.php file.
	 *
	 * @since    1.0.0
	 * @access private
	 * @return void
	 */
	private static function secure_log_directory() {
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/' . CAMPAIGNBAY_TEXT_DOMAIN . '-logs/';

		if ( ! is_dir( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		$htaccess_file = $log_dir . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$htaccess_content = '
			# Protect log files from direct access
			<Files *.log>
				Order allow,deny
				Deny from all
			</Files>
			';
			file_put_contents( $htaccess_file, $htaccess_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		}

		// --- Create blank index.php file for security ---
		$index_file = $log_dir . 'index.php';
		if ( ! file_exists( $index_file ) ) {
			$index_content = "<?php\n// Silence is golden.\n";
			file_put_contents( $index_file, $index_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		}
	}

	/**
	 * Adds custom roles and capabilities required by the plugin.
	 *
	 * This function should be called once on plugin activation. It performs three tasks:
	 * 1. Adds the 'manage_campaignbay' capability to the Administrator role.
	 * 2. Adds the 'manage_campaignbay' capability to the Shop Manager role.
	 * 3. Creates a new 'Demo User' role with the 'read' capability (to access the dashboard)
	 *    and the 'manage_campaignbay' capability.
	 *
	 * @since 1.0.0
	 * @access private
	 * @static
	 */
	private static function add_plugin_roles_and_capabilities() {
		// Define the unique capability slug for your plugin.
		$custom_capability = 'manage_campaignbay';

		$admin_role = get_role( 'administrator' );
		if ( $admin_role && ! $admin_role->has_cap( $custom_capability ) ) {
			$admin_role->add_cap( $custom_capability );
		}

		// woocommerce role
		$manager_role = get_role( 'shop_manager' );
		if ( $manager_role && ! $manager_role->has_cap( $custom_capability ) ) {
			$manager_role->add_cap( $custom_capability );
		}
		// only for demo user
		// $contributor = get_role('contributor');
		// if ($contributor && !$contributor->has_cap($custom_capability)) {
		// $contributor->add_cap($custom_capability);
		// }
	}
}
