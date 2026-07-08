<?php
/**
 * The Database Manager class.
 *
 * This file is responsible for defining the DbManager class, which handles the
 * creation and management of the plugin's custom database tables. This is
 * a core infrastructural component that runs on plugin activation.
 *
 * @link       https://wpanchorbay.com/campaignbay
 * @since      1.0.0
 *
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBay/Data
 */
namespace WpabCampaignBay\Data;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database Manager Class.
 *
 * This class is responsible for creating and maintaining the custom database tables
 * required by the CampaignBay plugin. It uses the `dbDelta` function to ensure
 * that tables are created and updated correctly upon plugin activation.
 *
 * It is designed as a singleton to ensure a single instance is used throughout
 * the activation process.
 *
 * @since      1.0.0
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBay/Data
 * @author     WP Anchor Bay <wpanchorbay@gmail.com>
 */
class DbManager {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 * @var   DbManager
	 * @access private
	 */
	private static $instance = null;
	/**
	 * Gets an instance of this object.
	 * Prevents duplicate instances which avoids artefacts and improves performance.
	 *
	 * @static
	 * @access public
	 * @since 1.0.0
	 * @return object
	 */
	public static function get_instance() {
		// Store the instance locally to avoid private static replication.
		static $instance = null;
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * A dummy constructor to prevent the class from being loaded more than once.
	 *
	 * @see DbManager::get_instance()
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function __construct() {
		// Prevent direct instantiation.
	}

	/**
	 * Run the database manager.
	 *
	 * This method is called to initialize the database manager and create necessary tables.
	 *
	 * @since 1.0.0
	 */
	public function create_tables() {
		$this->create_campaigns_table();
		$this->create_logs_table();
	}

	/**
	 * Create the campaigns table.
	 *
	 * This table stores campaign data.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function create_campaigns_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'campaignbay_campaigns';
		$charset_collate = $wpdb->get_charset_collate();

		// - Two spaces after PRIMARY KEY.
		// - One space between KEY and its name.
		// - Each field on its own line.
		$sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'inactive',
            type varchar(20) NOT NULL,
            discount_type varchar(20) DEFAULT NULL,
            discount_value decimal(10,2) DEFAULT NULL,
            tiers json DEFAULT NULL,
            conditions json DEFAULT NULL,
            settings json DEFAULT NULL,
            target_type varchar(20) DEFAULT NULL,
            target_ids longtext DEFAULT NULL,
            is_exclude tinyint(1) NOT NULL DEFAULT 0,
            exclude_sale_items tinyint(1) NOT NULL DEFAULT 0,
            schedule_enabled tinyint(1) NOT NULL DEFAULT 0,
            start_datetime datetime DEFAULT NULL,
            end_datetime datetime DEFAULT NULL,
            usage_count int(11) NOT NULL DEFAULT 0,
            usage_limit int(11) DEFAULT NULL,
            date_created datetime NOT NULL,
            date_modified datetime NOT NULL,
            created_by bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            updated_by bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY type (type),
            KEY date_range (start_datetime, end_datetime)
        ) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create the logs table.
	 *
	 * This table stores analytics and activity data.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function create_logs_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'campaignbay_logs';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			log_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) UNSIGNED NOT NULL,
			order_id bigint(20) UNSIGNED DEFAULT 0 NOT NULL,
			user_id bigint(20) UNSIGNED DEFAULT 0 NOT NULL,
			log_type varchar(20) NOT NULL,
            base_total decimal(10, 2) DEFAULT 0.00 NOT NULL,
			total_discount decimal(10, 2) DEFAULT 0.00 NOT NULL,
			order_total decimal(10, 2) DEFAULT 0.00 NOT NULL,
			order_status varchar(20) DEFAULT '' NOT NULL,
            extra_data json DEFAULT NULL,
            timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (log_id),
			KEY campaign_id (campaign_id),
			KEY order_status (order_status),
			KEY timestamp (timestamp)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Create the indexes for the campaigns table.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function create_campaigns_table_indexes() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'campaignbay_campaigns';
        //phpcs:ignore
        $wpdb->query("CREATE INDEX `status` ON {$table_name} (`status`)");
        //phpcs:ignore
        $wpdb->query("CREATE INDEX `type` ON {$table_name} (`type`)");
        //phpcs:ignore
        $wpdb->query("CREATE INDEX `date_range` ON {$table_name} (`start_datetime`, `end_datetime`)");
	}
}
