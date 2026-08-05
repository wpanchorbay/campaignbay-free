<?php
/**
 * File.
 *
 * @package campaignbay
 */

namespace WpabCampaignBay\Api;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Import WordPress REST API classes

use DateInterval;
use DateTime;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WpabCampaignBay\Core\Campaign;
use WpabCampaignBay\Engine\CalenderManager;

/**
 * The REST API Controller for the Dashboard.
 *
 * @since      1.0.0
 * @package    WPAB_CampaignBay
 * @author     WP Anchor Bay <wpanchorbay@gmail.com>
 */
class DashboardController extends ApiController {

	/**
	 * The instance of the DashboardController.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var DashboardController
	 */
	private static $instance = null;

	/**
	 * Get the instance of the DashboardController.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return DashboardController
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Run the DashboardController.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function run() {
		$this->rest_base = 'dashboard';
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the routes for the DashboardController.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function register_routes() {
		$namespace = $this->namespace . $this->version;

		register_rest_route(
			$namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_dashboard_data' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}


	/**
	 * Main callback to fetch all data for the dashboard.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_dashboard_data( $request ) {
		$period     = $request->get_param( 'period' );
		$start_date = $request->get_param( 'start_date' );
		$end_date   = $request->get_param( 'end_date' );

		list($current_start, $current_end, $previous_start, $previous_end) = $this->parse_date_range( $period, $start_date, $end_date );

		$response = array(
			'kpis'              => $this->get_kpi_data( $current_start, $current_end, $previous_start, $previous_end ),
			'charts'            => $this->get_chart_data( $current_start, $current_end ),
			'recent_activity'   => $this->get_recent_activity(),
			'live_and_upcoming' => $this->get_live_and_upcoming_campaigns(),
			'campaignsCalendar' => $this->get_campaigns_calendar(),
		);

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Get campaigns calendar data.
	 *
	 * @since 1.1.1
	 * @access private
	 * @return array
	 */
	private function get_campaigns_calendar() {
		return array_values( CalenderManager::get_instance()->get_campaigns() );
	}

	/**
	 * Calculates KPI data for the dashboard.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param string $current_start  Start date for the current period.
	 * @param string $current_end    End date for the current period.
	 * @param string $previous_start Start date for the previous period.
	 * @param string $previous_end   End date for the previous period.
	 * @return array
	 */
	private function get_kpi_data( $current_start, $current_end, $previous_start, $previous_end ) {
		global $wpdb;
		$logs_table = $wpdb->prefix . CAMPAIGNBAY_TEXT_DOMAIN . '_logs';

		// This is the new, corrected SQL query structure.
		$sql = "
        SELECT
            (SELECT SUM(total_discount) 
             FROM {$logs_table} 
             WHERE log_type = 'sale' AND order_status IN ('processing', 'completed') AND timestamp BETWEEN %s AND %s) as total_discount_value,

            SUM(unique_orders.final_order_total) as sales_from_campaigns,
            SUM(unique_orders.final_base_total) as base_total_for_discounts,
            COUNT(unique_orders.order_id) as discounted_orders
        FROM 
            (
                -- This subquery runs first. It finds each unique order_id,
                -- calculates the MIN(order_total) for that order,
                -- and takes just one value for base_total (using AVG, but MIN or MAX would also work as it's the same).
                SELECT 
                    order_id,
                    MIN(order_total) as final_order_total,
                    MAX(base_total) as final_base_total -- Using AVG as a safe way to get one value
                FROM {$logs_table}
                WHERE 
                    log_type = 'sale' 
                    AND order_status IN ('processing', 'completed') 
                    AND timestamp BETWEEN %s AND %s
                GROUP BY order_id
            ) as unique_orders
    ";

		// --- Get Current Period Data ---
		$current_sql = $wpdb->prepare(
			//phpcs:ignore
			$sql,
			$current_start,
			$current_end, // First two placeholders are for the total_discount subquery
			$current_start,
			$current_end  // Last two placeholders are for the unique_orders subquery
		);
		//phpcs:ignore
		$current_data = $wpdb->get_row($current_sql, ARRAY_A);

		// --- Get Previous Period Data ---
		$previous_sql = $wpdb->prepare(
			//phpcs:ignore
			$sql,
			$previous_start,
			$previous_end, // For total_discount
			$previous_start,
			$previous_end  // For unique_orders
		);
		//phpcs:ignore
		$previous_data = $wpdb->get_row($previous_sql, ARRAY_A);

		// --- Get Active Campaign Count (this part remains the same) ---
		$campaigns_table = $wpdb->prefix . 'campaignbay_campaigns';
		$sql             = "SELECT COUNT(*) FROM {$campaigns_table} WHERE status = 'active'";

		$sql = $wpdb->prepare(
			//phpcs:ignore
			$sql,
		);

		//phpcs:ignore
		$active_count = $wpdb->get_var($sql);

		return array(
			'active_campaigns'     => array(
				'value' => (int) $active_count,
			),
			'total_discount_value' => array(
				'value'      => (float) ( $current_data['total_discount_value'] ?? 0 ),
				'base_total' => (float) ( $current_data['base_total_for_discounts'] ?? 0 ),
				'change'     => $this->calculate_percentage_change( $current_data['total_discount_value'], $previous_data['total_discount_value'] ),
			),
			'discounted_orders'    => array(
				'value'  => (int) ( $current_data['discounted_orders'] ?? 0 ),
				'change' => $this->calculate_percentage_change( $current_data['discounted_orders'], $previous_data['discounted_orders'] ),
			),
			'sales_from_campaigns' => array(
				'value'  => (float) ( $current_data['sales_from_campaigns'] ?? 0 ),
				'change' => $this->calculate_percentage_change( $current_data['sales_from_campaigns'], $previous_data['sales_from_campaigns'] ),
			),
		);
	}

	/**
	 * Calculates Chart data for the dashboard.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param string $start_date Start date for the period.
	 * @param string $end_date   End date for the period.
	 * @return array
	 */
	private function get_chart_data( $start_date, $end_date ) {
		global $wpdb;
		$logs_table = $wpdb->prefix . CAMPAIGNBAY_TEXT_DOMAIN . '_logs';

		// --- CORRECTED: Discount Trends (Line Chart) ---
		// This query now uses the same robust pattern as the corrected get_kpi_data function.
		$sql = "
        SELECT
            -- The date for the grouping.
            unique_orders_per_day.date,
            
            -- Sum the total sales and base totals FROM the clean, de-duplicated subquery.
            SUM(unique_orders_per_day.final_order_total) as total_sales,
            SUM(unique_orders_per_day.final_base_total) as total_base,
            
            -- Get the sum of all discounts in a separate, efficient subquery.
            (SELECT SUM(total_discount) 
             FROM {$logs_table} 
             WHERE log_type = 'sale' AND order_status IN ('processing', 'completed') AND DATE(timestamp) = unique_orders_per_day.date
            ) as total_discount_value
        FROM
            (
                -- This inner subquery runs first. It creates a temporary table in memory
                -- containing exactly one row for each unique order on each unique day.
                SELECT 
                    DATE(timestamp) as date,
                    order_id,
                    MIN(order_total) as final_order_total,
                    MAX(base_total) as final_base_total
                FROM {$logs_table}
                WHERE 
                    log_type = 'sale' 
                    AND order_status IN ('processing', 'completed') 
                    AND timestamp BETWEEN %s AND %s
                GROUP BY DATE(timestamp), order_id
            ) as unique_orders_per_day
        GROUP BY unique_orders_per_day.date
        ORDER BY unique_orders_per_day.date ASC
    ";

		$trends_sql = $wpdb->prepare(
			//phpcs:ignore
			$sql,
			$start_date,
			$end_date
		);
		//phpcs:ignore
		$discount_trends = $wpdb->get_results($trends_sql, ARRAY_A);

		// Fill in missing dates with zero values (this function remains essential).
		$discount_trends = $this->fill_missing_dates( $discount_trends, $start_date, $end_date );

		// --- Top Campaigns (Bar Chart) - This query was already correct. ---
		$sql_top = "SELECT campaign_id, SUM(total_discount) as value
             FROM {$logs_table}
             WHERE log_type = 'sale' AND order_status IN ('processing', 'completed')
             AND timestamp BETWEEN %s AND %s
             GROUP BY campaign_id
             ORDER BY value DESC
             LIMIT 5";

		$top_campaigns_sql = $wpdb->prepare(
			//phpcs:ignore
			$sql_top,
			$start_date,
			$end_date
		);
		//phpcs:ignore
		$top_campaigns = $wpdb->get_results($top_campaigns_sql, ARRAY_A);

		// Add campaign titles to the results (this logic remains the same).
		$campaigns_table = $wpdb->prefix . 'campaignbay_campaigns';
		foreach ( $top_campaigns as &$campaign_data ) {

			//phpcs:ignore
			$title = $wpdb->get_var(
				//phpcs:ignore
				$wpdb->prepare(
					//phpcs:ignore
					"SELECT title FROM {$campaigns_table} WHERE id = %d",
					$campaign_data['campaign_id']
				)
			);
			$campaign_data['name'] = $title ?: 'Unknown Campaign';
		}

		return array(
			'discount_trends'      => $discount_trends,
			'top_campaigns'        => $top_campaigns,
			'most_impactful_types' => $this->get_most_impactful_types( $start_date, $end_date ),
		);
	}

	/**
	 * Gets the data for the "Most Impactful Types" pie chart.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param string $start_date Start date for the period.
	 * @param string $end_date   End date for the period.
	 * @return array
	 */
	private function get_most_impactful_types( $start_date, $end_date ) {
		global $wpdb;
		$logs_table       = $wpdb->prefix . CAMPAIGNBAY_TEXT_DOMAIN . '_logs';
		$campaigns_table  = $wpdb->prefix . 'campaignbay_campaigns';
		$success_statuses = "'processing', 'completed'";

		$sql = "SELECT c.type, SUM(l.order_total) as total_sales
			 FROM {$logs_table} l
			 JOIN {$campaigns_table} c ON l.campaign_id = c.id
			 WHERE l.log_type = 'sale' AND l.order_status IN ('processing', 'completed')
			 AND l.timestamp BETWEEN %s AND %s
			 GROUP BY c.type
			 ORDER BY total_sales DESC";
		$sql = $wpdb->prepare(
			//phpcs:ignore
			$sql,
			$start_date,
			$end_date
		);

		//phpcs:ignore
		return $wpdb->get_results($sql, ARRAY_A);
	}

	/**
	 * Gets the data for the "Live & Upcoming Campaigns" widget.
	 *
	 * @since 1.0.0
	 * @access private
	 * @return array
	 */
	private function get_live_and_upcoming_campaigns() {
		global $wpdb;
		$campaigns_table = $wpdb->prefix . 'campaignbay_campaigns';

		// --- Get currently active campaigns (ordered by which one will expire first) ---
		$active_sql = "SELECT id, title, end_datetime, type 
					   FROM {$campaigns_table} 
					   WHERE status = 'active' 
					   ORDER BY end_datetime ASC 
					   LIMIT 5";
		$sql        = $wpdb->prepare(
			//phpcs:ignore
			$active_sql,
		);
		//phpcs:ignore
		$active_results = $wpdb->get_results($sql, ARRAY_A);

		$active_campaigns = array();
		foreach ( $active_results as $row ) {
			$active_campaigns[] = array(
				'id'       => (int) $row['id'],
				'title'    => $row['title'],
				'end_date' => $row['end_datetime'],
				'type'     => $row['type'],
			);
		}

		// --- Get upcoming scheduled campaigns (ordered by which one will start first) ---
		$scheduled_sql = "SELECT id, title, start_datetime, type 
						  FROM {$campaigns_table} 
						  WHERE status = 'scheduled' 
						  ORDER BY start_datetime ASC 
						  LIMIT 5";
		$sql           = $wpdb->prepare(
			//phpcs:ignore
			$scheduled_sql,
		);

		//phpcs:ignore
		$scheduled_results = $wpdb->get_results($sql, ARRAY_A);

		$scheduled_campaigns = array();
		foreach ( $scheduled_results as $row ) {
			$scheduled_campaigns[] = array(
				'id'         => (int) $row['id'],
				'title'      => $row['title'],
				'start_date' => $row['start_datetime'],
				'type'       => $row['type'],
			);
		}

		return array(
			'active'    => $active_campaigns,
			'scheduled' => $scheduled_campaigns,
		);
	}

	/**
	 * Gets the most recent activity logs, returning raw data for the frontend to format.
	 *
	 * @since 1.0.0
	 * @access private
	 * @return array
	 */
	private function get_recent_activity() {
		global $wpdb;
		$table_name = $wpdb->prefix . CAMPAIGNBAY_TEXT_DOMAIN . '_logs';
		$sql        = "SELECT timestamp, extra_data, user_id, campaign_id FROM {$table_name}
				 WHERE log_type IN ( 'campaign_created', 'campaign_updated', 'campaign_deleted' )
				 ORDER BY timestamp DESC
				 LIMIT 5";
		//phpcs:ignore
		$results = $wpdb->get_results(
			//phpcs:ignore
			$wpdb->prepare(
				//phpcs:ignore
				$sql,
			),
			ARRAY_A
		);

		$activity_log = array();
		foreach ( $results as $row ) {
			$user_info  = get_userdata( $row['user_id'] );
			$extra_data = json_decode( $row['extra_data'], true );

			// --- NEW: Return raw, structured data ---
			$activity_log[] = array(
				'timestamp'      => $row['timestamp'],
				'campaign_id'    => (int) $row['campaign_id'],
				'campaign_title' => $extra_data['title'] ?? 'N/A',
				'action'         => $extra_data['message'] ?? 'unknown', // e.g., "created", "updated"
				'user'           => $user_info ? $user_info->display_name : 'System',
				'link'           => admin_url( 'admin.php?page=campaignbay-campaigns&action=edit&id=' . $row['campaign_id'] ),
			);
		}
		return $activity_log;
	}

	/**
	 * Parses the date range parameters from the request.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param string $period Preset period ('7days', '30days', 'year').
	 * @param string $start_date Custom start date.
	 * @param string $end_date Custom end date.
	 * @return array [current_start, current_end, previous_start, previous_end]
	 */
	private function parse_date_range( $period, $start_date, $end_date ) {
		$timezone = new \DateTimeZone( wp_timezone_string() );
		$end      = new DateTime( 'now', $timezone );
		$end->setTime( 23, 59, 59 );
		$start = new DateTime( 'now', $timezone );
		$start->setTime( 0, 0, 0 );

		if ( 'custom' === $period && $start_date && $end_date ) {
			$start = new DateTime( $start_date, $timezone );
			$start->setTime( 0, 0, 0 );
			$end = new DateTime( $end_date, $timezone );
			$end->setTime( 23, 59, 59 );
		} else {
			switch ( $period ) {
				case '7days':
					$start->sub( new DateInterval( 'P6D' ) );
					break;
				case 'year':
					$start->sub( new DateInterval( 'P1Y' ) );
					break;
				case '30days':
				default:
					$start->sub( new DateInterval( 'P29D' ) );
					break;
			}
		}

		$current_start_str = $start->format( 'Y-m-d H:i:s' );
		$current_end_str   = $end->format( 'Y-m-d H:i:s' );

		$interval  = $end->diff( $start );
		$days_diff = $interval->days + 1;

		// Calculate previous period
		$previous_start = clone $start;
		$previous_start->sub( new DateInterval( 'P' . $days_diff . 'D' ) );
		$previous_end = clone $start;
		$previous_end->sub( new DateInterval( 'P1D' ) );
		$previous_end->setTime( 23, 59, 59 );

		return array(
			$current_start_str,
			$current_end_str,
			$previous_start->format( 'Y-m-d H:i:s' ),
			$previous_end->format( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Calculates percentage change between two values.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param float $current Current value.
	 * @param float $previous Previous value.
	 * @return float Percentage change.
	 */
	private function calculate_percentage_change( $current, $previous ) {
		if ( null === $previous || '' === $previous || false === $previous || 0.0 === (float) $previous ) {
			return $current > 0 ? 100 : 0;
		}
		return round( ( ( $current - $previous ) / $previous ) * 100, 2 );
	}

	/**
	 * Fills in missing dates with zero values for chart data.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param array  $data Array of data with date keys.
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array Data with missing dates filled.
	 */
	private function fill_missing_dates( $data, $start_date, $end_date ) {
		$start        = new DateTime( $start_date );
		$end          = new DateTime( $end_date );
		$filled_data  = array();
		$data_by_date = array();

		// Index existing data by date
		foreach ( $data as $row ) {
			$data_by_date[ $row['date'] ] = $row;
		}

		// Fill in all dates in range
		$current = clone $start;
		while ( $current <= $end ) {
			$date_str = $current->format( 'Y-m-d' );
			if ( isset( $data_by_date[ $date_str ] ) ) {
				$filled_data[] = $data_by_date[ $date_str ];
			} else {
				$filled_data[] = array(
					'date'                 => $date_str,
					'total_discount_value' => 0,
					'total_base'           => 0,
					'total_sales'          => 0,
				);
			}
			$current->add( new DateInterval( 'P1D' ) );
		}

		return $filled_data;
	}

	/**
	 * Retrieves the query params for collections.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['period'] = array(
			'description' => __( 'Time period for the dashboard data.', 'campaignbay' ),
			'type'        => 'string',
			'default'     => '30days',
			'enum'        => array( '7days', '30days', 'year', 'custom' ),
		);

		$params['start_date'] = array(
			'description' => __( 'Custom start date (required when period is custom).', 'campaignbay' ),
			'type'        => 'string',
			'format'      => 'date',
		);

		$params['end_date'] = array(
			'description' => __( 'Custom end date (required when period is custom).', 'campaignbay' ),
			'type'        => 'string',
			'format'      => 'date',
		);

		return $params;
	}
}
