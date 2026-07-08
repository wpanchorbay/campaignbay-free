<?php

namespace WpabCampaignBay\Api;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Import WordPress REST API classes

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * The REST API Controller for Activity Logs.
 *
 * @since      1.0.0
 * @package    WPAB_CampaignBay
 * @author     WP Anchor Bay <wpanchorbay@gmail.com>
 */
class ActivityLogController extends ApiController {


	/**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var ActivityLogController
	 */
	private static $instance = null;

	/**
	 * Gets an instance of this object.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return ActivityLogController
	 */
	public static function get_instance() {
		// Store the instance locally to avoid private static replication.
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function run() {
		$this->rest_base = 'activity-logs';
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the routes for the objects of the controller.
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
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the log entry.', 'campaignbay' ),
						'type'        => 'integer',
						'required'    => true,
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Get a collection of activity log entries.
	 *
	 * @query SELECT
	 *          l.log_id,
	 *          l.campaign_id,
	 *          l.order_id,
	 *          l.user_id,
	 *          l.log_type,
	 *          l.base_total,
	 *          l.total_discount,
	 *          l.order_total,
	 *          l.order_status,
	 *          l.extra_data,
	 *          l.timestamp,
	 *          c.title as campaign_title,
	 *          c.type
	 *        FROM wp_campaignbay_logs l
	 *        LEFT JOIN wp_campaignbay_campaigns c ON l.campaign_id = c.id
	 *        WHERE 1=1 AND
	 *        l.log_type IN ( "campaign_created", "campaign_updated", "campaign_deleted" )
	 *        ORDER BY l.timestamp DESC LIMIT 10 OFFSET 0
	 *
	 * @since 1.0.0
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		global $wpdb;
		$logs_table      = $wpdb->prefix . 'campaignbay_logs';
		$campaigns_table = $wpdb->prefix . 'campaignbay_campaigns';

		// Get pagination parameters
		$page     = $request->get_param( 'page' ) ?: 1;
		$per_page = $request->get_param( 'per_page' ) ?: 20;
		$offset   = ( $page - 1 ) * $per_page;

		// Get sorting parameters
		$orderby = $request->get_param( 'orderby' ) ?: 'timestamp';
		$order   = $request->get_param( 'order' ) ?: 'desc';

		// Validate orderby parameter
		$allowed_orderby = array( 'timestamp', 'log_type', 'campaign_title', 'order_id', 'user_name', 'base_total', 'total_discount', 'order_total' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'timestamp';
		}

		// Validate order parameter
		$order = strtoupper( $order );
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		// Get filter parameters
		$log_type     = $request->get_param( 'log_type' );
		$campaign_id  = $request->get_param( 'campaign_id' );
		$order_status = $request->get_param( 'order_status' );
		$date_from    = $request->get_param( 'date_from' );
		$date_to      = $request->get_param( 'date_to' );

		// Build WHERE clause
		$sql          = "SELECT 
			l.log_id,
			l.campaign_id,
			l.order_id,
			l.user_id,
			l.log_type,
			l.base_total,
			l.total_discount,
			l.order_total,
			l.order_status,
			l.extra_data,
			l.timestamp,
			c.title as campaign_title,
			c.type
		FROM {$logs_table} l
		LEFT JOIN {$campaigns_table} c ON l.campaign_id = c.id WHERE 1=1";
		$count_sql    = "SELECT COUNT(*) FROM {$logs_table} l WHERE 1=1";
		$query_params = array();

		if ( ! empty( $log_type ) ) {
			if ( $log_type === 'activity' ) {
				$sql       .= ' AND l.log_type IN ( "campaign_created", "campaign_updated", "campaign_deleted" )';
				$count_sql .= ' AND l.log_type IN ( "campaign_created", "campaign_updated", "campaign_deleted" )';
			} else {
				$sql           .= ' AND l.log_type = %s';
				$count_sql     .= ' AND l.log_type = %s';
				$query_params[] = $log_type;
			}
		}

		if ( ! empty( $campaign_id ) ) {
			$sql           .= ' AND l.campaign_id = %d';
			$count_sql     .= ' AND l.campaign_id = %d';
			$query_params[] = (int) $campaign_id;
		}

		if ( ! empty( $order_status ) ) {
			$sql           .= ' AND l.order_status = %s';
			$count_sql     .= ' AND l.order_status = %s';
			$query_params[] = $order_status;
		}

		if ( ! empty( $date_from ) ) {
			$sql           .= ' AND l.timestamp >= %s';
			$count_sql     .= ' AND l.timestamp >= %s';
			$query_params[] = $date_from . ' 00:00:00';
		}

		if ( ! empty( $date_to ) ) {
			$sql           .= ' AND l.timestamp <= %s';
			$count_sql     .= ' AND l.timestamp <= %s';
			$query_params[] = $date_to . ' 23:59:59';
		}

		//phpcs:ignore
		$count_sql = $wpdb->prepare($count_sql, $query_params);

		// Build ORDER BY clause
		switch ( $orderby ) {
			case 'campaign_title':
				$sql .= ' ORDER BY c.title ';
				break;
			case 'user_name':
				$sql .= ' ORDER BY l.user_id ';
				break;
			case 'order_id':
				$sql .= ' ORDER BY l.order_id ';
				break;
			case 'base_total':
				$sql .= ' ORDER BY l.base_total ';
				break;
			case 'total_discount':
				$sql .= ' ORDER BY l.total_discount ';
				break;
			case 'order_total':
				$sql .= ' ORDER BY l.order_total ';
				break;
			case 'log_type':
				$sql .= ' ORDER BY l.log_type ';
				break;
			case 'timestamp':
			default:
				$sql .= ' ORDER BY l.timestamp ';
				break;
		}

		$sql .= $order === 'ASC' ? 'ASC' : 'DESC';

		//phpcs:ignore
		$total_items = $wpdb->get_var($count_sql);

		$sql           .= ' LIMIT %d OFFSET %d';
		$query_params[] = $per_page;
		$query_params[] = $offset;

		//phpcs:ignore
		$data_sql = $wpdb->prepare($sql, $query_params);

		//phpcs:ignore
		$results = $wpdb->get_results($data_sql, ARRAY_A);

		// Prepare the response data
		$data = array();
		foreach ( $results as $row ) {
			$data[] = $this->prepare_item_for_response( $row, $request );
		}

		// Calculate pagination info
		$total_pages = ceil( $total_items / $per_page );

		$response = new WP_REST_Response( $data, 200 );
		$response->header( 'X-WP-Total', $total_items );
		$response->header( 'X-WP-TotalPages', $total_pages );

		return $response;
	}



	/**
	 * Get a single activity log entry.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		global $wpdb;
		$logs_table      = $wpdb->prefix . 'campaignbay_logs';
		$campaigns_table = $wpdb->prefix . 'campaignbay_campaigns';

		$log_id = $request->get_param( 'id' );

		$sql = "SELECT 
			l.log_id,
			l.campaign_id,
			l.order_id,
			l.user_id,
			l.log_type,
			l.base_total,
			l.total_discount,
			l.order_total,
			l.order_status,
			l.extra_data,
			l.timestamp,
			c.title as campaign_title,
			c.type
		FROM {$logs_table} l
		LEFT JOIN {$campaigns_table} c ON l.campaign_id = c.id
		WHERE l.log_id = %d";

		//phpcs:ignore
		$result = $wpdb->get_row($wpdb->prepare($sql, $log_id), ARRAY_A);

		if ( ! $result ) {
			return new WP_Error( 'rest_log_not_found', __( 'Log entry not found.', 'campaignbay' ), array( 'status' => 404 ) );
		}

		$data = $this->prepare_item_for_response( $result, $request );
		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Prepare a single log entry for response.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param array           $log_entry Log entry data.
	 * @param WP_REST_Request $request   Request object.
	 * @return array Prepared log entry data.
	 */
	public function prepare_item_for_response( $log_entry, $request ) {
		$data = array(
			'id'             => (int) $log_entry['log_id'],
			'campaign_id'    => (int) $log_entry['campaign_id'],
			'order_id'       => (int) $log_entry['order_id'],
			'user_id'        => (int) $log_entry['user_id'],
			'log_type'       => $log_entry['log_type'],
			'base_total'     => (float) $log_entry['base_total'],
			'total_discount' => (float) $log_entry['total_discount'],
			'order_total'    => (float) $log_entry['order_total'],
			'order_status'   => $log_entry['order_status'],
			'extra_data'     => ! empty( $log_entry['extra_data'] ) ? json_decode( $log_entry['extra_data'], true ) : null,
			'timestamp'      => $log_entry['timestamp'],
			'campaign_title' => $log_entry['campaign_title'],
			'type'           => $log_entry['type'],
		);

		// Add user information if user_id exists
		if ( $log_entry['user_id'] > 0 ) {
			$user = get_user_by( 'id', $log_entry['user_id'] );
			if ( $user ) {
				$data['user_name']  = $user->display_name;
				$data['user_email'] = $user->user_email;
			}
		}

		// Add order information if order_id exists
		if ( $log_entry['order_id'] > 0 ) {
			$order = wc_get_order( $log_entry['order_id'] );
			if ( $order ) {
				$data['order_number']       = $order->get_order_number();
				$data['order_status_label'] = wc_get_order_status_name( $log_entry['order_status'] );
			}
		}

		// Add campaign edit link
		if ( $log_entry['campaign_id'] > 0 ) {
			$data['campaign_edit_link'] = admin_url( 'admin.php?page=campaignbay-campaigns&action=edit&id=' . $log_entry['campaign_id'] );
		}

		// Add order edit link
		if ( $log_entry['order_id'] > 0 ) {
			$data['order_edit_link'] = admin_url( 'post.php?post=' . $log_entry['order_id'] . '&action=edit' );
		}

		return $data;
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

		$params['per_page']['default'] = 20;
		$params['per_page']['maximum'] = 100;

		$params['orderby'] = array(
			'description' => __( 'Sort collection by object attribute.', 'campaignbay' ),
			'type'        => 'string',
			'default'     => 'timestamp',
			'enum'        => array( 'timestamp', 'log_type', 'campaign_title', 'order_id', 'user_name', 'base_total', 'total_discount', 'order_total' ),
		);

		$params['order'] = array(
			'description' => __( 'Order sort attribute ascending or descending.', 'campaignbay' ),
			'type'        => 'string',
			'default'     => 'desc',
			'enum'        => array( 'asc', 'desc' ),
		);

		$params['log_type'] = array(
			'description' => __( 'Filter by log type.', 'campaignbay' ),
			'type'        => 'string',
			'enum'        => array( 'activity', 'sale', 'campaign_created', 'campaign_updated', 'campaign_deleted' ),
		);

		$params['campaign_id'] = array(
			'description' => __( 'Filter by campaign ID.', 'campaignbay' ),
			'type'        => 'integer',
		);

		$params['order_status'] = array(
			'description' => __( 'Filter by order status.', 'campaignbay' ),
			'type'        => 'string',
			'enum'        => array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' ),
		);

		$params['date_from'] = array(
			'description' => __( 'Filter logs from this date (Y-m-d format).', 'campaignbay' ),
			'type'        => 'string',
			'format'      => 'date',
		);

		$params['date_to'] = array(
			'description' => __( 'Filter logs to this date (Y-m-d format).', 'campaignbay' ),
			'type'        => 'string',
			'format'      => 'date',
		);

		return $params;
	}

	/**
	 * Check if a given request has access to get items.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Check if a given request has access to update items.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has update access, WP_Error object otherwise.
	 */
	public function update_item_permissions_check( $request ) {
		return current_user_can( 'manage_woocommerce' );
	}
}
