<?php
/**
 * File.
 *
 * @package campaignbay
 */

namespace WpabCampaignBay\Engine;

use WpabCampaignBay\Core\Base;
use WpabCampaignBay\Core\Campaign;
use WpabCampaignBay\Helper\Helper;

/**
 * Description.
 *
 * @link       https://wpanchorbay.com
 * @since      1.1.1
 *
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBay/includes
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Description.
 *
 * @since      1.1.1
 * @package    WPAB_CampaignBay
 * @author     WP Anchor Bay <wpanchorbay@gmail.com>
 */
class CalenderManager extends Base {


	// interface Campaign {
	// id: number;
	// name: string;
	// startDate: Date;
	// endDate?: Date | null; // Optional end date for ongoing campaigns
	// type: CampaignType;
	// }

	/**
	 * Array of all campaigns.
	 *
	 * @since 1.1.1
	 * @access private
	 * @var Campaign[]|null
	 */
	private $campaigns = null;


	/**
	 * A dummy constructor to prevent the class from being loaded more than once.
	 *
	 * @see CalenderManager::get_instance()
	 *
	 * @since 1.1.1
	 * @access private
	 */
	protected function __construct() {
		parent::__construct();
		$this->add_action( 'campaignbay_campaign_save', 'clear_cache', 10, 2 );
		$this->add_action( 'campaignbay_campaign_delete', 'clear_cache', 10, 1 );
	}



	/**
	 * Get all active campaigns, using a multi-level cache.
	 *
	 * @since 1.1.1
	 * @return Campaign[] An array of active campaign objects.
	 */
	public function get_campaigns() {
		if ( null !== $this->campaigns ) {
			return $this->campaigns;
		}

		$cached_campaigns = get_transient( 'campaignbay_calender_campaigns' );
		if ( false !== $cached_campaigns ) {
			$this->campaigns = $cached_campaigns;
			return $this->campaigns;
		}

		wpab_campaignbay_log( 'No cached campaigns found, fetching from database', 'WARNING' );

		global $wpdb;
		$table_name = $wpdb->prefix . 'campaignbay_campaigns';

		// we have our personal caching
		//phpcs:ignore
		$results = $wpdb->get_results(
			$wpdb->prepare(
				//phpcs:ignore
				"SELECT id, title, type, status, start_datetime, end_datetime, date_created, date_modified, schedule_enabled FROM {$table_name}"
			)
		);

		if ( is_wp_error( $results ) ) {
			wpab_campaignbay_log( 'Error in fetching campaigns in calendar manager', 'ERROR' );
			return array();
		}

		$campaign_objects = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$data = array(
					'id'   => $row->id,
					'name' => $row->title,
					'type' => $row->type,
				);
				if ( $row->schedule_enabled && $row->start_datetime ) {
					$data['startDate'] = strtotime( Helper::get_utc_time( $row->start_datetime ) );
				} else {
					$data['startDate'] = strtotime( Helper::get_utc_time( $row->date_created ) );
				}
				if ( $row->schedule_enabled && $row->end_datetime ) {
					$data['endDate'] = strtotime( Helper::get_utc_time( $row->end_datetime ) );
				} else {
					$data['endDate'] = null;
				}
				$campaign_objects[ $row->id ] = $data;
			}
		} else {
			wpab_campaignbay_log( 'Query has no campaigns', 'WARNING' );
		}

		set_transient( 'campaignbay_calender_campaigns', $campaign_objects, 60 * 24 * 30 * MINUTE_IN_SECONDS );
		$this->campaigns = $campaign_objects;
		return $this->campaigns;
	}


	/**
	 * Clears the active campaign transient cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $source The source of the cache clear.
	 */
	public function clear_cache( $source = 'unknown' ) {
		wpab_campaignbay_log( 'clearing calendar cache', 'INFO' );
		delete_transient( 'campaignbay_calender_campaigns' );
		$this->campaigns = null;
	}
}
