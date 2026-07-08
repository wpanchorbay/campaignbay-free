<?php

namespace WpabCampaignBay\Engine;

use WpabCampaignBay\Core\Base;
use WpabCampaignBay\Core\Campaign;

/**
 * The file that defines the Campaign Manager class.
 *
 * A class definition that fetches and caches all active discount campaigns.
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
 * The Campaign Manager class.
 *
 * This class is responsible for querying for all active campaigns and caching the result
 * to prevent redundant database calls, especially on high-traffic frontend pages.
 *
 * @since      1.0.0
 * @package    WPAB_CampaignBay
 * @author     WP Anchor Bay <wpanchorbay@gmail.com>
 */
class CampaignManager extends Base {



	/**
	 * Array of active campaign objects.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var Campaign[]|null
	 */
	private $active_campaigns = null;

	/**
	 * Summary of scheduled_campaigns
	 *
	 * @since 1.0.0
	 * @access private
	 * @var array|null
	 */
	private $scheduled_campaigns = null;


	/**
	 * A dummy constructor to prevent the class from being loaded more than once.
	 *
	 * @see CampaignManager::get_instance()
	 *
	 * @since 1.0.0
	 * @access private
	 */
	protected function __construct() {
		parent::__construct();

		// Use the inherited add_action method to register hooks.
		// These hooks will clear the campaign cache whenever a campaign is saved or deleted.
		$this->add_action( 'campaignbay_campaign_save', 'clear_cache', 10, 2 );
		$this->add_action( 'campaignbay_campaign_usage_incremented', 'clear_cache', 10, 2 );
		$this->add_action( 'campaignbay_campaign_usage_decremented', 'clear_cache', 10, 2 );
		$this->add_action( 'campaignbay_campaign_delete', 'clear_cache', 10, 1 );
	}



	/**
	 * Get all active campaigns, using a multi-level cache.
	 *
	 * @since 1.0.0
	 * @return Campaign[] An array of active campaign objects.
	 */
	public function get_active_campaigns() {
		if ( null !== $this->active_campaigns ) {
			return $this->active_campaigns;
		}

		$cached_campaigns = get_transient( 'campaignbay_active_campaigns' );
		if ( false !== $cached_campaigns ) {
			wpab_campaignbay_log( 'cached_campaigns found', 'INFO' );
			$this->active_campaigns = $cached_campaigns;
			return $this->active_campaigns;
		}

		wpab_campaignbay_log( 'No cached campaigns found, fetching from database', 'WARNING' );

		global $wpdb;
		$table_name = $wpdb->prefix . 'campaignbay_campaigns';

		// we have our personal caching
		//phpcs:ignore
		$results = $wpdb->get_results(
			$wpdb->prepare(
				//phpcs:ignore
				"SELECT * FROM {$table_name} WHERE status = %s ORDER BY date_created ASC",
				'active'
			)
		);

		if ( is_wp_error( $results ) ) {
			wpab_campaignbay_log( 'Error in fetching campaigns', 'ERROR' );
			return array();
		}

		$campaign_objects = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				// Decode JSON fields
				$row->target_ids = ! empty( $row->target_ids ) ? json_decode( $row->target_ids, true ) : array();
				$row->tiers      = ! empty( $row->tiers ) ? json_decode( $row->tiers, true ) : array();

				$campaign = new Campaign( $row );
				if ( $campaign ) {
					// Usage count is now stored directly in the table, no need to load separately
					$campaign_objects[ $campaign->get_id() ] = $campaign;
				}
			}
		} else {
			wpab_campaignbay_log( 'query has no campaigns', 'INFO' );
		}

		set_transient( 'campaignbay_active_campaigns', $campaign_objects, 60 * MINUTE_IN_SECONDS );
		$this->active_campaigns = $campaign_objects;
		return $this->active_campaigns;
	}
	/**
	 * Get all active and scheduled campaigns where scheduling is on, using a multi-level cache.
	 *
	 * @since 1.0.0
	 * @return Campaign[] An array of active campaign objects.
	 */
	public function get_scheduled_campaigns() {
		if ( null !== $this->scheduled_campaigns ) {
			return $this->scheduled_campaigns;
		}

		$cached_campaigns = get_transient( 'campaignbay_scheduled_campaigns' );
		if ( false !== $cached_campaigns ) {
			wpab_campaignbay_log( 'cached scheduled campaigns found', 'INFO' );
			$this->scheduled_campaigns = $cached_campaigns;
			return $this->scheduled_campaigns;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'campaignbay_campaigns';

		// Get all campaigns that are currently in a state that could potentially change

		//phpcs:ignore
		$results = $wpdb->get_results(
			$wpdb->prepare(
				//phpcs:ignore
				"SELECT * FROM {$table_name} WHERE status IN (%s, %s) OR schedule_enabled = 1",
				'scheduled',
				'active'
			)
		);

		if ( is_wp_error( $results ) ) {
			wpab_campaignbay_log( 'Error in fetching scheduled campaigns', 'ERROR' );
			return array();
		}

		$campaign_objects = array();
		if ( $results ) {
			foreach ( $results as $row ) {

				$campaign = new Campaign( $row );
				if ( $campaign ) {
					$campaign_objects[ $campaign->get_id() ] = $campaign;
				}
			}
		} else {
			wpab_campaignbay_log( 'no scheduled or active campaigns found for failsafe check.', 'INFO' );
			$campaign_objects = array();
		}

		set_transient( 'campaignbay_scheduled_campaigns', $campaign_objects, 60 * MINUTE_IN_SECONDS );
		$this->scheduled_campaigns = $campaign_objects;
		return $this->scheduled_campaigns;
	}

	/**
	 * Clears the active campaign transient cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $source The source of the cache clear.
	 */
	public function clear_cache( $source = 'unknown' ) {
		wpab_campaignbay_log( 'clearing cache from ' . $source, 'INFO' );
		delete_transient( 'campaignbay_active_campaigns' );
		delete_transient( 'campaignbay_scheduled_campaigns' );
		$this->active_campaigns    = null;
		$this->scheduled_campaigns = null;
	}
}
