<?php

namespace WpabCampaignBay\Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles all logging for the plugin.
 *
 * This class provides a central point for logging sales, activities, errors,
 * and system events to a custom database table.
 *
 * @since      1.0.0
 * @package    WPAB_CampaignBay
 * @author     WP Anchor Bay <wpanchorbay@gmail.com>
 */
class Logger {


	/**
	 * The instance of the Logger class.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var Logger
	 */
	private static $instance = null;

	/**
	 * Gets an instance of the Logger class.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return Logger
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor for the Logger class.
	 *
	 * @since 1.0.0
	 * @access private
	 * @return void
	 */
	private function __construct() {
		// Private constructor for singleton
	}

	/**
	 * Central logging function for the entire plugin.
	 *
	 * Records events to a custom database table. It handles standard fields like
	 * campaign ID, order ID, and user ID, while also storing flexible contextual
	 * data in a JSON column.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param string $log_type    The category of the log entry (e.g., 'sale', 'activity', 'error').
	 * @param string $message     A short, human-readable message describing the event.
	 * @param array  $context     Optional. An associative array of contextual data (e.g., IDs, totals) to store.
	 * @return void
	 */
	public function log( $log_type, $message, $context = array() ) {
		global $wpdb;

		// Get the table name.
		$table_name = $wpdb->prefix . 'campaignbay_logs';

		// --- Prepare core, indexed columns ---
		$campaign_id = isset( $context['campaign_id'] ) ? absint( $context['campaign_id'] ) : 0;
		$order_id    = isset( $context['order_id'] ) ? absint( $context['order_id'] ) : 0;
		$user_id     = isset( $context['user_id'] ) ? absint( $context['user_id'] ) : get_current_user_id();

		// --- Prepare analytics columns (mostly for 'sale' type logs) ---
		$base_total     = isset( $context['base_total'] ) ? (float) $context['base_total'] : 0;
		$total_discount = isset( $context['total_discount'] ) ? (float) $context['total_discount'] : 0;
		$order_total    = isset( $context['order_total'] ) ? (float) $context['order_total'] : 0;
		$order_status   = isset( $context['order_status'] ) ? sanitize_text_field( $context['order_status'] ) : '';

		// --- Prepare the flexible JSON data column ---
		// Start with any extra_data passed in the context.
		$extra_data = isset( $context['extra_data'] ) && is_array( $context['extra_data'] ) ? $context['extra_data'] : array();

		// Add the primary message to the JSON data for clarity and consistency.
		$extra_data['message'] = sanitize_text_field( $message );

		// Insert the final, structured log entry.
		//phpcs:ignore
		$wpdb->insert(
			$table_name,
			array(
				'campaign_id'    => $campaign_id,
				'order_id'       => $order_id,
				'user_id'        => $user_id,
				'log_type'       => sanitize_key( $log_type ),
				'base_total'     => $base_total,
				'total_discount' => $total_discount,
				'order_total'    => $order_total,
				'order_status'   => $order_status, // This is now correctly used only for order status.
				'extra_data'     => wp_json_encode( $extra_data ),
				'timestamp'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%f', '%f', '%f', '%s', '%s', '%s' )
		);
	}
}
