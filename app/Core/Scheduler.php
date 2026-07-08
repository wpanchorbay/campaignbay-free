<?php
/**
 * File.
 *
 * @package campaignbay
 */

namespace WpabCampaignBay\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Import necessary classes

use WP_Post;
use WP_Query;
use WpabCampaignBay\Core\Campaign;
use WpabCampaignBay\Engine\CampaignManager;

/**
 * Handles the WP-Cron scheduling for campaigns.
 *
 * This class is responsible for scheduling, unscheduling, and executing the activation
 * and expiration of time-based campaigns based on their post status.
 *
 * @since      1.0.0
 * @package    WPAB_CampaignBay
 * @author     WP Anchor Bay <wpanchorbay@gmail.com>
 */
class Scheduler extends Base {



	// Define our custom hook names as constants for consistency and to avoid typos.
	const ACTIVATION_HOOK   = 'campaignbay_activate_campaign_event';
	const DEACTIVATION_HOOK = 'campaignbay_deactivate_campaign_event';


	/**
	 * Private constructor to define hooks and enforce singleton pattern.
	 *
	 * @since 1.0.0
	 * @access private
	 * @return void
	 */
	protected function __construct() {
		parent::__construct();
		$this->define_hooks();
	}

	/**
	 * Run the scheduler.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function run() {
		// $this->run_scheduled_campaigns_cron();
	}

	/**
	 * Defines all hooks this class needs to run.
	 *
	 * @since 1.0.0
	 * @access private
	 * @return void
	 */
	private function define_hooks() {
		// The main trigger to set up schedules when a campaign is saved or updated.
		$this->add_action( 'campaignbay_campaign_save', 'handle_campaign_save', 10, 2 );

		// The hooks that our cron events will trigger to change the campaign status.
		$this->add_action( self::ACTIVATION_HOOK, 'run_campaign_activation', 10, 1 );
		$this->add_action( self::DEACTIVATION_HOOK, 'run_campaign_deactivation', 10, 1 );

		// Hook to clean up schedules if a campaign is deleted from the database.
		$this->add_action( 'campaignbay_campaign_delete', 'clear_campaign_schedules_on_delete', 10, 1 );

		// Hook to check for scheduled campaigns.
		$this->add_action( 'init', 'run_scheduled_campaigns_cron', 10, 0 );
	}


	/**
	 * Main handler that runs when a campaign post is saved.
	 * It decides whether to schedule or unschedule events based on the new post status.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param int      $campaign_id The campaign ID.
	 * @param Campaign $campaign    The campaign object.
	 * @return void
	 */
	public function handle_campaign_save( $campaign_id, $campaign ) {
		if ( ! $campaign instanceof Campaign ) {
			$campaign = new Campaign( $campaign_id );
		}
		$this->clear_campaign_schedules( $campaign_id );
		wpab_campaignbay_log( sprintf( 'Clearing schedules for campaign #%d', $campaign_id ), 'INFO' );

		$start_timestamp = $campaign->get_start_timestamp();
		$end_timestamp   = $campaign->get_end_timestamp();
		wpab_campaignbay_log( sprintf( 'Start timestamp: %d, End timestamp: %d', $start_timestamp, $end_timestamp ), 'INFO' );
		wpab_campaignbay_log( sprintf( 'Campaign status: %s, Schedule enabled: %s', $campaign->get_status(), $campaign->get_schedule_enabled() ), 'INFO' );

		if ( 'scheduled' === $campaign->get_status() && $campaign->get_schedule_enabled() ) {
			if ( ! $start_timestamp ) {
				wpab_campaignbay_log( sprintf( 'Campaign #%d is missing start timestamps. Cannot schedule. %d', $campaign_id, $start_timestamp ), 'ERROR' );
			} else {
				wp_schedule_single_event(
					$start_timestamp,
					self::ACTIVATION_HOOK,
					array( 'campaign_id' => $campaign_id )
				);
				wpab_campaignbay_log(
					sprintf(
						'Scheduled campaign #%d: activation at %s.',
						$campaign_id,
						wp_date( 'Y-m-d H:i:s T', $start_timestamp )
					),
					'INFO'
				);
			}

			if ( ! $end_timestamp ) {
				wpab_campaignbay_log( sprintf( 'Campaign #%d is missing end timestamps. Cannot schedule deactivation.', $campaign_id ), 'ERROR' );
				return;
			} else {
				// Schedule the deactivation event.
				wp_schedule_single_event(
					$end_timestamp,
					self::DEACTIVATION_HOOK,
					array( 'campaign_id' => $campaign_id )
				);
				wpab_campaignbay_log(
					sprintf(
						'Scheduled campaign #%d: deactivation at %s.',
						$campaign_id,
						wp_date( 'Y-m-d H:i:s T', $end_timestamp )
					),
					'INFO'
				);
			}
		}
	}

	/**
	 * A failsafe method that manually checks all scheduled and active campaigns.
	 *
	 * This acts as a safety net for unreliable WP-Cron environments. It queries for
	 * any campaigns that should have been activated or deactivated by now and corrects
	 * their status, relying on the Campaign object's internal date logic.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function run_scheduled_campaigns_cron() {
		wpab_campaignbay_log( 'Running scheduled campaigns cron.', 'INFO' );
		$campaigns = CampaignManager::get_instance()->get_scheduled_campaigns();

		if ( empty( $campaigns ) ) {
			wpab_campaignbay_log( 'no scheduled or active campaigns found for failsafe check.', 'WARNING' );
			return;
		}

		// Get the current time once as a UTC timestamp.
		$current_timestamp    = time();
		$cache_needs_clearing = false;

		foreach ( $campaigns as $campaign ) {

			$start_timestamp = $campaign->get_start_timestamp();
			$end_timestamp   = $campaign->get_end_timestamp();

			if ( ( 'scheduled' === $campaign->get_status() && $campaign->get_schedule_enabled() ) && $start_timestamp && $start_timestamp <= $current_timestamp ) {
				//phpcs:ignore
				wpab_campaignbay_log(sprintf('Failsafe: Activating campaign #%d (%s) as its start time (%s) has passed. Current time: %s.', $campaign->get_id(), $campaign->get_title(), date('Y-m-d H:i:s', $start_timestamp), date('Y-m-d H:i:s', $current_timestamp)), 'INFO');
				$this->run_campaign_activation( $campaign->get_id() );
				$cache_needs_clearing = true;
			}

			// --- Check for Deactivation ---
			// If the campaign is active and its end time has passed, expire it.
			if ( ( 'active' === $campaign->get_status() || 'scheduled' === $campaign->get_status() ) && $campaign->get_schedule_enabled() && $end_timestamp && $end_timestamp <= $current_timestamp ) {
				//phpcs:ignore
				wpab_campaignbay_log(sprintf('Failsafe: Expiring campaign #%d (%s) as its end time (%s) has passed. Current time: %s.', $campaign->get_id(), $campaign->get_title(), date('Y-m-d H:i:s', $end_timestamp), date('Y-m-d H:i:s', $current_timestamp)), 'INFO');
				$this->run_campaign_deactivation( $campaign->get_id() );
				$cache_needs_clearing = true;
			}
		}

		if ( $cache_needs_clearing ) {
			CampaignManager::get_instance()->clear_cache();
		}
	}

	/**
	 * Runs when the activation cron event fires. Changes status to 'active'.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param int $campaign_id The ID of the campaign to activate.
	 * @return void
	 */
	public function run_campaign_activation( $campaign_id ) {
		wpab_campaignbay_log( sprintf( 'WP-Cron: Running activation for campaign #%d.', $campaign_id ), 'INFO' );

		global $wpdb;
		$table_name = $wpdb->prefix . 'campaignbay_campaigns';

		//phpcs:ignore
		$wpdb->update(
			$table_name,
			array(
				'status'        => 'active',
				'date_modified' => current_time( 'mysql' ),
			),
			array( 'id' => $campaign_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		// Clear the main campaign cache so the change is reflected immediately on the frontend.
		CampaignManager::get_instance()->clear_cache( 'scheduler' );
	}

	/**
	 * Runs when the deactivation cron event fires. Changes status to 'expired'.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param int $campaign_id The ID of the campaign to expire.
	 * @return void
	 */
	public function run_campaign_deactivation( $campaign_id ) {
		wpab_campaignbay_log( sprintf( 'WP-Cron: Running deactivation for campaign #%d.', $campaign_id ), 'INFO' );

		global $wpdb;
		$table_name = $wpdb->prefix . 'campaignbay_campaigns';

		//phpcs:ignore
		$wpdb->update(
			$table_name,
			array(
				'status'        => 'expired',
				'date_modified' => current_time( 'mysql' ),
			),
			array( 'id' => $campaign_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		CampaignManager::get_instance()->clear_cache( 'scheduler' );
	}

	/**
	 * Clears schedules when a campaign is about to be deleted.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param int $campaign_id The campaign ID being deleted.
	 * @return void
	 */
	public function clear_campaign_schedules_on_delete( $campaign_id ) {
		$this->clear_campaign_schedules( $campaign_id );
	}

	/**
	 * Clears all scheduled cron events associated with a specific campaign.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 * @param int $campaign_id The post ID of the campaign.
	 * @return void
	 */
	private function clear_campaign_schedules( $campaign_id ) {
		$args = array( 'campaign_id' => $campaign_id );
		wp_clear_scheduled_hook( self::ACTIVATION_HOOK, $args );
		wp_clear_scheduled_hook( self::DEACTIVATION_HOOK, $args );
		wpab_campaignbay_log( sprintf( 'Cleared all schedules for campaign #%d.', $campaign_id ), 'DEBUG' );
	}
}
