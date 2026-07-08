<?php
/**
 * File.
 *
 * @package campaignbay
 */

namespace WpabCampaignBay\Engine;

use WC_Order;
use WpabCampaignBay\Core\Base;
use WpabCampaignBay\Core\Campaign;

/**
 * Handles actions related to WooCommerce order status changes.
 *
 * This class is responsible for logging campaign usage and recording analytics
 * based on order status transitions.
 *
 * @since      1.0.0
 * @package    WPAB_CampaignBay
 * @author     WP Anchor Bay <wpanchorbay@gmail.com>
 */
class OrderManager extends Base {

	/**
	 * Constructor to define and build the hooks array.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		parent::__construct();
		$this->define_hooks();
	}

	/**
	 * Defines all hooks this class needs to run.
	 *
	 * @since 1.0.0
	 */
	private function define_hooks() {
		// Use the generic hook to capture ALL status changes.
		$this->add_action( 'woocommerce_order_status_changed', 'handle_order_status_change', 10, 4 );
	}


	/**
	 * Main callback that logs every important order status change.
	 *
	 * @since 1.0.0
	 * @hook woocommerce_order_status_changed
	 * @param int      $order_id    The ID of the order.
	 * @param string   $old_status  The old order status (without wc- prefix).
	 * @param string   $new_status  The new order status (without wc- prefix).
	 * @param WC_Order $order       The order object.
	 */
	public function handle_order_status_change( $order_id, $old_status, $new_status, $order ) {
		if ( 'processing' === $old_status || 'completed' === $old_status ) {
			wpab_campaignbay_log( sprintf( 'Order #%d status changed canceled for old status. from "%s" to "%s". Handling event.', $order_id, $old_status, $new_status ), 'INFO' );
			$this->reverse_order( $order_id, $old_status, $new_status, $order );
			return;
		}
		if ( 'processing' !== $new_status && 'completed' !== $new_status ) {
			wpab_campaignbay_log( sprintf( 'Order #%d status changed canceled for new status. from "%s" to "%s". Handling event.', $order_id, $old_status, $new_status ), 'INFO' );
			$this->reverse_order( $order_id, $old_status, $new_status, $order );
			return;
		}
		wpab_campaignbay_log( sprintf( 'Order #%d status changed from "%s" to "%s". Handling event.', $order_id, $old_status, $new_status ), 'INFO' );

		$discount_breakdown = $order->get_meta( '_campaignbay_discount_breakdown', true );

		if ( empty( $discount_breakdown ) || ! is_array( $discount_breakdown ) ) {
			wpab_campaignbay_log( sprintf( 'No campaign data found on order #%d. Aborting log.', $order_id ), 'DEBUG' );
			return; // This order was not processed by our plugin.
		}

		foreach ( $discount_breakdown as $campaign_id => $data ) {
			wpab_campaignbay_log( 'campaign_id: ' . $campaign_id, 'INFO' );
			$campaign = new Campaign( $campaign_id );
			$campaign->increment_usage_count();
			wpab_campaignbay_log( 'incrementing usage count' . $campaign_id );
			$this->log_sale_event( $campaign_id, $order, $data, $new_status );
		}
	}

	/**
	 * Function.
	 */
	private function reverse_order( $order_id, $old_status, $new_status, $order ) {
		if ( 'processing' !== $old_status && 'completed' !== $old_status ) {
			wpab_campaignbay_log( sprintf( 'Order #%d status reversed for old status. from "%s" to "%s". Handling event.', $order_id, $old_status, $new_status ), 'INFO' );
			return;
		}
		if ( 'processing' === $new_status || 'completed' === $new_status ) {
			wpab_campaignbay_log( sprintf( 'Order #%d status reversed for new status. from "%s" to "%s". Handling event.', $order_id, $old_status, $new_status ), 'INFO' );
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'campaignbay_logs';

		$sql = "SELECT campaign_id FROM $table_name WHERE order_id = %d";
		// phpcs:ignore 
		$campaign_ids = $wpdb->get_results(
			$wpdb->prepare(
				//phpcs:ignore 
				$sql,
				$order_id
			)
		);

		$result = $wpdb->delete(
			$table_name,
			array( 'order_id' => $order_id ),
			array( '%d' )
		);
		foreach ( $campaign_ids ?? array() as $key => $campaign ) {
			wpab_campaignbay_log( 'decrementing usage count' . $campaign->campaign_id, 'INFO' );
			$campaign = new Campaign( $campaign->campaign_id );
			$campaign->decrement_usage_count();
		}
	}

	/**
	 * Adds or updates a log entry for a campaign on a specific order.
	 *
	 * This function is the single source of truth for all sale-related logging.
	 * It will UPDATE an existing log if the status changes (e.g., from 'processing' to 'completed').
	 *
	 * @param int      $campaign_id   The ID of the campaign used.
	 * @param WC_Order $order         The order object.
	 * @param array    $data          The discount data for this campaign from the breakdown.
	 * @param string   $new_status    The new status of the order.
	 */
	private function log_sale_event( $campaign_id, $order, $data, $new_status ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'campaignbay_logs';
		$order_id   = $order->get_id();

		// Calculate the base total and the total discount for this specific campaign.
		$base_total = (float) ( $data['old_price'] ?? 0 );

		$total_discount = $data['discount'] ?? 0;

		// Prepare the flexible JSON data column.
		$extra_data = array(
			'campaign_title' => $data['campaign_title'] ?? 'Unknown',
		);

		// Prepare the data for the database record.
		$log_data = array(
			'campaign_id'    => $campaign_id,
			'order_id'       => $order_id,
			'user_id'        => $order->get_customer_id(),
			'log_type'       => 'sale', // All order events are of type 'sale'.
			'base_total'     => $base_total,
			'total_discount' => $total_discount,
			'order_total'    => $order->get_total(),
			'order_status'   => $new_status,
			'extra_data'     => wp_json_encode( $extra_data ),
			'timestamp'      => current_time( 'mysql' ),
		);

		// Check if a log for this order and campaign already exists.
		$sql = "SELECT log_id FROM $table_name WHERE order_id = %d AND campaign_id = %d";
		// phpcs:ignore 
		$existing_log_id = $wpdb->get_var(
			$wpdb->prepare(
				//phpcs:ignore 
				$sql,
				$order_id,
				$campaign_id
			)
		);

		if ( $existing_log_id ) {
			// If it exists, UPDATE it with the new status and timestamp.
			wpab_campaignbay_log( sprintf( 'Updating existing log entry #%d for order #%d, campaign #%d. New status: %s', $existing_log_id, $order_id, $campaign_id, $new_status ), 'DEBUG' );
			// phpcs:ignore 
			$wpdb->update($table_name, $log_data, array('log_id' => $existing_log_id));// phpcs:ignore 
		} else {
			// If it doesn't exist, INSERT a new record.
			wpab_campaignbay_log( sprintf( 'Creating new log entry for order #%d, campaign #%d. Status: %s', $order_id, $campaign_id, $new_status ), 'DEBUG' );
			// phpcs:ignore 
			$wpdb->insert($table_name, $log_data);

			// Increment the usage count for the campaign
			$campaign = new Campaign( $campaign_id );
			CampaignManager::get_instance()->clear_cache( 'order_manager' );
		}
	}
}
