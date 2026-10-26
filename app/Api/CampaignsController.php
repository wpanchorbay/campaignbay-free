<?php // phpcs:ignore Class file names should be based on the class name with "class-" prepended.

namespace WpabCampaignBay\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Import WordPress REST API classes

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WpabCampaignBay\Core\Campaign;
use WpabCampaignBay\Engine\CampaignManager;
use WpabCampaignBay\Helper\Logger;

/**
 * The REST API Controller for Campaigns.
 *
 * @link       https://wpanchorbay.com
 * @since      1.0.0
 *
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBay/includes/api
 */

/**
 * The Campaign API controller class.
 *
 * This class defines the REST API endpoints for managing campaigns.
 * It uses the Campaign class to interact with the database.
 *
 * @since      1.0.0
 * @package    WPAB_CampaignBay
 * @author     WP Anchor Bay <wpanchorbay@gmail.com>
 */
class CampaignsController extends ApiController {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 * @var   CampaignsController
	 * @access private
	 */
	private static $instance = null;
	/**
	 * Gets an instance of this object.
	 * Prevents duplicate instances which avoid artefacts and improves performance.
	 *
	 * @static
	 * @access public
	 * @return object
	 * @since 1.0.0
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
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function run() {
		$this->rest_base = 'campaigns';
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

		// campaigns
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
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_item_schema' ), // Corrected from get_public_item_schema
			)
		);

		// campaigns/{id}
		register_rest_route(
			$namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Unique identifier for the campaign.', 'campaignbay' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'view' ) ),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'force' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => __( 'Whether to bypass trash and force deletion.', 'campaignbay' ),
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		// campaigns/duplicate
		register_rest_route(
			$namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/duplicate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'duplicate_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'id' => array(
							'description' => __( 'Unique identifier for the campaign to duplicate.', 'campaignbay' ),
							'type'        => 'integer',
							'required'    => true,
						),
					),
				),
			)
		);

		// campaigns/bulk
		register_rest_route(
			$namespace,
			'/' . $this->rest_base . '/bulk',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_items' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_bulk_update_args(), // <-- Use specific args
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_items' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_bulk_delete_args(), // <-- Use specific args
				),
			)
		);

		register_rest_route(
			$namespace,
			'/' . $this->rest_base . '/export',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_items' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/' . $this->rest_base . '/import',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE, // CREATABLE is used for POST
					'callback'            => array( $this, 'import_items' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_bulk_import_args(),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/' . $this->rest_base . '/dependents',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_dependents' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(),
				),
			)
		);
	}


	/**
	 * Retrieves dependent data like all products and product categories.
	 *
	 * This endpoint is designed to populate the "Select Products" and "Select Categories"
	 * searchable dropdowns in the campaign editor. It returns a lightweight list of
	 * items containing only their ID and name.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_dependents( $request ) {
		// --- 1. Fetch All Published Products ---
		$product_posts = get_posts(
			array(
				'post_type'   => array( 'product', 'product_variation', 'variable_product' ),
				'post_status' => 'publish',
				'numberposts' => -1,          // Get all products
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);

		$products = array();
		foreach ( $product_posts as $post ) {
			// We only need the ID and title for the selector component.
			if ( $post->post_parent > 0 ) {
				if ( ! isset( $products[ $post->post_parent ] ) ) {
					$products[ $post->post_parent ]['variants'] = array();
				}
				$products[ $post->post_parent ]['variants'][] = array(
					'id'   => $post->ID,
					'name' => $post->post_title,
				);
			} else {
				$products[ $post->ID ] = array(
					'id'   => $post->ID,
					'name' => $post->post_title,
				);
			}
		}

		$products = array_values( $products );

		// --- 2. Fetch All Product Categories ---
		$category_terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false, // Include categories that don't have products yet
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		$categories = array();
		// get_terms can return a WP_Error, so we must check for it.
		if ( ! is_wp_error( $category_terms ) ) {
			foreach ( $category_terms as $term ) {
				$categories[] = array(
					'id'   => $term->term_id,
					'name' => $term->name,
				);
			}
		}

		// --- 3. Return the Combined Data in a REST Response ---
		$response_data = array(
			'products'   => $products,
			'categories' => $categories,
		);

		return new WP_REST_Response( $response_data, 200 );
	}


	/**
	 * Get a collection of campaigns.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'campaignbay_campaigns';

		// Build the query and parameters together
		$query_params = array();

		$sql       = "SELECT * FROM {$table_name} WHERE 1=1 ";
		$count_sql = "SELECT COUNT(*) FROM {$table_name} WHERE 1=1 ";

		// Handle Status Filtering
		$status = $request->get_param( 'status' );
		if ( ! empty( $status ) && 'all' !== $status && is_array( $status ) ) {
			// CORRECTED: Add a leading space for valid SQL.
			$sql         .= ' AND status IN (' . implode( ',', array_fill( 0, count( $status ), '%s' ) ) . ')';
			$count_sql   .= ' AND status IN (' . implode( ',', array_fill( 0, count( $status ), '%s' ) ) . ')';
			$query_params = array_merge( $query_params, array_map( 'sanitize_key', $status ) );
		}

		// Handle campaign type filtering
		$type_filter = $request->get_param( 'type' );
		if ( ! empty( $type_filter ) && is_array( $type_filter ) ) {
			$sql         .= ' AND type IN (' . implode( ',', array_fill( 0, count( $type_filter ), '%s' ) ) . ')';
			$count_sql   .= ' AND type IN (' . implode( ',', array_fill( 0, count( $type_filter ), '%s' ) ) . ')';
			$query_params = array_merge( $query_params, array_map( 'sanitize_key', $type_filter ) );
		}

		// Handle search
		$search = $request->get_param( 'search' );
		if ( ! empty( $search ) ) {
			$sql           .= ' AND title LIKE %s';
			$count_sql     .= ' AND title LIKE %s';
			$query_params[] = '%' . $wpdb->esc_like( sanitize_text_field( $search ) ) . '%';
		}

		// --- Get total count for headers ---
		//phpcs:ignore 
		$total_items = $wpdb->get_var($wpdb->prepare($count_sql, $query_params));

		// --- Continue building the main query ---

		// Handle ordering (whitelisting is correct)
		$orderby = $request->get_param( 'orderby' ) ?: 'date_modified';
		$order   = $request->get_param( 'order' ) ?: 'DESC';

		$allowed_orderby = array( 'id', 'title', 'status', 'type', 'date_created', 'date_modified', 'priority' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'date_modified';
		}
		$order = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';

		// Handle pagination
		$per_page = (int) ( $request->get_param( 'per_page' ) ?: 10 );
		$page     = (int) ( $request->get_param( 'page' ) ?: 1 );
		$offset   = ( $page - 1 ) * $per_page;

		// Append the final clauses to the main SQL query
		$sql .= " ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		// Add the final parameters for pagination
		$query_params[] = $per_page;
		$query_params[] = $offset;

		// Get the campaigns (using the final, complete parameter array)
		//phpcs:ignore
		$results = $wpdb->get_results($wpdb->prepare($sql, $query_params));
		$response_data = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				// Decode JSON fields
				$row->target_ids = ! empty( $row->target_ids ) ? json_decode( $row->target_ids, true ) : array();
				$row->tiers      = ! empty( $row->tiers ) ? json_decode( $row->tiers, true ) : array();

				$campaign = new Campaign( $row );
				if ( $campaign ) {
					$response_data[] = $this->prepare_item_for_response( $campaign, $request );
				}
			}
		}

		$response = new WP_REST_Response( $response_data, 200 );
		$response->header( 'X-WP-Total', $total_items );
		$response->header( 'X-WP-TotalPages', ceil( $total_items / $per_page ) );

		return $response;
	}

	/**
	 * Loads a campaign by ID, converting the "not found" exception Campaign::__construct()
	 * throws into a proper 404 WP_Error instead of letting it escape uncaught.
	 *
	 * @since 1.1.11
	 * @access private
	 * @param int $id The campaign ID.
	 * @return Campaign|WP_Error The campaign, or a 404 WP_Error if it doesn't exist.
	 */
	private function get_campaign_or_error( $id ) {
		try {
			return new Campaign( $id );
		} catch ( \Exception $e ) {
			return new WP_Error( 'rest_campaign_not_found', __( 'Campaign not found.', 'campaignbay' ), array( 'status' => 404 ) );
		}
	}

	/**
	 * Get a single campaign.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$id       = (int) $request['id'];
		$campaign = $this->get_campaign_or_error( $id );

		if ( is_wp_error( $campaign ) ) {
			return $campaign;
		}

		$data = $this->prepare_item_for_response( $campaign, $request );
		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Create a single campaign.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @access public
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		$params   = $request->get_json_params();
		$campaign = Campaign::create( $params );

		if ( is_wp_error( $campaign ) ) {
			return $campaign;
		}

		$data     = $this->prepare_item_for_response( $campaign, $request );
		$response = new WP_REST_Response( $data, 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace . $this->version, $this->rest_base, $campaign->get_id() ) ) );

		return $response;
	}

	/**
	 * Update a single campaign.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_item( $request ) {
		$id       = (int) $request['id'];
		$campaign = $this->get_campaign_or_error( $id );

		if ( is_wp_error( $campaign ) ) {
			return $campaign;
		}

		$params = $request->get_json_params();
		$result = $campaign->update( $params );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! $result ) {
			return new WP_Error( 'rest_cannot_update', __( 'Failed to update campaign.', 'campaignbay' ), array( 'status' => 500 ) );
		}

		// Get the fresh, updated campaign object to return.
		$updated_campaign = new Campaign( $id );
		$data             = $this->prepare_item_for_response( $updated_campaign, $request );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Delete a single campaign.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_item( $request ) {
		$campaign_id = $request->get_param( 'id' );
		$force       = $request->get_param( 'force' );

		$campaign = $this->get_campaign_or_error( $campaign_id );
		if ( is_wp_error( $campaign ) ) {
			return $campaign;
		}

		$result = $campaign->delete( $campaign_id, $force );
		if ( ! $result ) {
			return new WP_Error( 'rest_cannot_delete', __( 'Failed to delete campaign.', 'campaignbay' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Bulk update campaigns' statuses.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_items( $request ) {
		$params = $request->get_json_params();
		$ids    = isset( $params['ids'] ) ? array_map( 'absint', $params['ids'] ) : array();
		$status = isset( $params['status'] ) ? sanitize_key( $params['status'] ) : '';

		if ( empty( $ids ) ) {
			return new WP_Error( 'rest_invalid_ids', __( 'Campaign IDs are required.', 'campaignbay' ), array( 'status' => 400 ) );
		}
		if ( empty( $status ) ) {
			return new WP_Error( 'rest_invalid_status', __( 'A valid status is required.', 'campaignbay' ), array( 'status' => 400 ) );
		}

		$updated_count = 0;
		foreach ( $ids as $id ) {
			$campaign = $this->get_campaign_or_error( $id );
			if ( ! is_wp_error( $campaign ) ) {
				$title  = $campaign->get_title();
				$result = $campaign->set_status( $status );

				if ( true === $result && ! is_wp_error( $result ) ) {
					wpab_campaignbay_log( 'title : ' . $campaign->get_title() . ' ' . $status, 'ERROR' );
					++$updated_count;
					// Log the activity.
					Logger::get_instance()->log(
						'activity',
						'updated',
						array(
							'campaign_id' => $id,
							'extra_data'  => array(
								'title' => $title,
							),
						)
					);
				}
				if ( is_wp_error( $result ) ) {
					//phpcs:ignore
					wpab_campaignbay_log(print_r($result, true), 'ERROR');
				}
			}
		}

		// Clear the cache after all updates are done.
		CampaignManager::get_instance()->clear_cache();

		return new WP_REST_Response(
			array(
				'success' => true,
				'updated' => $updated_count,
			),
			200
		);
	}

	/**
	 * Bulk delete campaigns.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_items( $request ) {
		$params = $request->get_json_params();
		$ids    = isset( $params['ids'] ) ? array_map( 'absint', $params['ids'] ) : array();

		if ( empty( $ids ) ) {
			return new WP_Error( 'rest_invalid_ids', __( 'Campaign IDs are required.', 'campaignbay' ), array( 'status' => 400 ) );
		}

		$deleted_count = 0;
		foreach ( $ids as $id ) {
			// Skip IDs that no longer exist instead of letting the constructor's
			// exception bubble up as an uncaught 500 (e.g. a stale ID in the batch).
			if ( is_wp_error( $this->get_campaign_or_error( $id ) ) ) {
				continue;
			}
			// Using the Campaign::delete method ensures our custom hooks are fired.
			if ( Campaign::delete( $id, true ) ) {
				++$deleted_count;
			}
		}

		// Cache is cleared by the delete hooks, so no need to do it here.

		return new WP_REST_Response(
			array(
				'success' => true,
				'deleted' => $deleted_count,
			),
			200
		);
	}

	/**
	 * Function.
	 */
	public function duplicate_item( $request ) {
		try {
			$id       = (int) $request['id'];
			$campaign = $this->get_campaign_or_error( $id );

			if ( is_wp_error( $campaign ) ) {
				return $campaign;
			}

			$new_campaign = $campaign->create(
				array(
					'title'              => $campaign->get_title() . ' (Copy)',
					'type'               => $campaign->get_type(),
					'status'             => $campaign->get_status(),

					'discount_type'      => $campaign->get_discount_type(),
					'discount_value'     => $campaign->get_discount_value(),
					'tiers'              => $campaign->get_tiers(),

					'target_type'        => $campaign->get_target_type(),
					'target_ids'         => $campaign->get_target_ids(),
					'is_exclude'         => $campaign->get_is_exclude(),
					'exclude_sale_items' => $campaign->get_exclude_sale_items(),

					'schedule_enabled'   => $campaign->get_schedule_enabled(),
					'start_datetime'     => $campaign->get_start_datetime(),
					'end_datetime'       => $campaign->get_end_datetime(),

					'conditions'         => $campaign->get_conditions(),
					'settings'           => $campaign->get_settings(),
					'usage_limit'        => $campaign->get_usage_limit(),
				)
			);
			if ( is_wp_error( $new_campaign ) ) {
				return $new_campaign;
			}

			$data     = $this->prepare_item_for_response( $new_campaign, $request );
			$response = new WP_REST_Response( $data, 201 );
			$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace . $this->version, $this->rest_base, $new_campaign->get_id() ) ) );

			return $response;
		} catch ( \Exception $e ) {
			return new WP_Error( 'rest_duplicate_failed', __( 'Failed to duplicate campaign.', 'campaignbay' ), array( 'status' => 500 ) );
		}
	}

	/**
	 * Export all campaigns as a JSON file.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function export_items( $request ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'campaignbay_campaigns';

		$sql = "SELECT `title`, `status`, `type`, `discount_type`, `discount_value`, `tiers`, `conditions`, `settings`, `target_type`, `target_ids`, `is_exclude`, `exclude_sale_items`, `schedule_enabled`, `start_datetime`, `end_datetime`, `usage_count`, `usage_limit`, `date_created`, `date_modified` FROM {$table_name} ORDER BY date_modified DESC";
		//phpcs:ignore
		$results = $wpdb->get_results($sql);
		return new WP_REST_Response( $results, 200 );
	}

	/**
	 * Bulk import campaigns from a JSON array.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function import_items( $request ) {
		$params    = $request->get_json_params();
		$campaigns = isset( $params['campaigns'] ) ? $params['campaigns'] : array();

		if ( empty( $campaigns ) || ! is_array( $campaigns ) ) {
			return new WP_Error( 'rest_invalid_data', __( 'Campaign data is missing or not an array.', 'campaignbay' ), array( 'status' => 400 ) );
		}

		$campaigns     = json_decode( wp_json_encode( $campaigns ), true );
		$created_count = 0;
		$failed_count  = 0;
		$errors        = array();

		foreach ( $campaigns as $index => $campaign_data ) {
			$args = array(
				'title'              => isset( $campaign_data['title'] ) ? sanitize_text_field( $campaign_data['title'] ) : 'Imported Campaign',
				'status'             => isset( $campaign_data['status'] ) ? sanitize_key( $campaign_data['status'] ) : 'draft',
				'type'               => isset( $campaign_data['type'] ) ? sanitize_key( $campaign_data['type'] ) : 'scheduled', // Corrected from 'type' to 'campaign_type'

				'discount_type'      => isset( $campaign_data['discount_type'] ) ? sanitize_key( $campaign_data['discount_type'] ) : null,
				'discount_value'     => isset( $campaign_data['discount_value'] ) ? floatval( $campaign_data['discount_value'] ) : 0,

				'target_type'        => isset( $campaign_data['target_type'] ) ? sanitize_key( $campaign_data['target_type'] ) : 'entire_store',
				'target_ids'         => isset( $campaign_data['target_ids'] ) && ! empty( $campaign_data['target_ids'] ) ? ( is_string( $campaign_data['target_ids'] ) ? json_decode( $campaign_data['target_ids'], true ) : $campaign_data['target_ids'] ) : array(),
				'is_exclude'         => isset( $campaign_data['is_exclude'] ) ? (bool) $campaign_data['is_exclude'] : false,
				'exclude_sale_items' => isset( $campaign_data['exclude_sale_items'] ) ? $campaign_data['exclude_sale_items'] : false,

				'schedule_enabled'   => isset( $campaign_data['schedule_enabled'] ) ? (bool) $campaign_data['schedule_enabled'] : false,
				'start_datetime'     => isset( $campaign_data['start_datetime'] ) && ! empty( $campaign_data['start_datetime'] ) ? sanitize_text_field( $campaign_data['start_datetime'] ) : null,
				'end_datetime'       => isset( $campaign_data['end_datetime'] ) && ! empty( $campaign_data['end_datetime'] ) ? sanitize_text_field( $campaign_data['end_datetime'] ) : null,

				'tiers'              => isset( $campaign_data['tiers'] ) && ! empty( $campaign_data['tiers'] ) ? ( is_string( $campaign_data['tiers'] ) ? json_decode( $campaign_data['tiers'], true ) : $campaign_data['tiers'] ) : array(),
				'conditions'         => isset( $campaign_data['conditions'] ) && ! empty( $campaign_data['conditions'] ) ? ( is_string( $campaign_data['conditions'] ) ? json_decode( $campaign_data['conditions'], true ) : $campaign_data['conditions'] ) : null,
				'settings'           => isset( $campaign_data['settings'] ) && ! empty( $campaign_data['settings'] ) ? ( is_string( $campaign_data['settings'] ) ? json_decode( $campaign_data['settings'], true ) : $campaign_data['settings'] ) : null,

				'usage_limit'        => isset( $campaign_data['usage_limit'] ) && is_numeric( $campaign_data['usage_limit'] ) ? absint( $campaign_data['usage_limit'] ) : null,
				'usage_count'        => isset( $campaign_data['usage_count'] ) ? absint( $campaign_data['usage_count'] ) : 0, // Allow importing a previous usage count
			);
			$result = Campaign::create( $args, $args['usage_count'] );

			if ( is_wp_error( $result ) ) {
				++$failed_count;
				$errors[] = array( 'Row ' . ( $index + 1 ) . " ('" . esc_html( $args['title'] ) . "'): " => $result );
			} else {
				++$created_count;
			}
		}

		// Clear the campaign cache once after all imports are done.
		CampaignManager::get_instance()->clear_cache();

		if ( $failed_count > 0 ) {
			return new WP_Error(
				'rest_import_partial_failure',
				__( 'Some campaigns could not be imported.', 'campaignbay' ),
				array(
					'status'  => 400,
					'details' => array(
						'created_count' => $created_count,
						'failed_count'  => $failed_count,
						'errors'        => $errors,
					),
				)
			);
		}

		return new \WP_REST_Response(
			array(
				'success'       => true,
				'created_count' => $created_count,
			),
			201
		);
	}

	/**
	 * Defines the arguments for the bulk import endpoint.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_bulk_import_args() {
		return array(
			'campaigns' => array(
				'description' => __( 'An array of campaign objects to import.', 'campaignbay' ),
				'type'        => 'array',
				'required'    => true,
				'items'       => array(
					'type' => 'object',
				),
			),
		);
	}



	/**
	 * Prepare a single campaign output for response.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param Campaign        $campaign Campaign object.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $campaign, $request ) {
		$data                      = $campaign->get_data();
		$data->start_datetime_unix = $campaign->get_start_timestamp();
		$data->end_datetime_unix   = $campaign->get_end_timestamp();
		$data->date_modified_unix  = $campaign->get_time_stamp( $data->date_modified );
		$data->date_created_unix   = $campaign->get_time_stamp( $data->date_created );
		return $data;
	}

	/**
	 * Get the query params for collections.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return array
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['per_page']['default'] = 10;
		$params['per_page']['maximum'] = 100;

		return $params;
	}

	/**
	 * Get the campaign schema, conforming to JSON Schema.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return array
	 */
	public function get_item_schema() {
		if ( isset( $this->schema ) ) {
			/**
			 * Filters the REST API schema for a single campaign item.
			 *
			 * This filter allows other developers to extend the campaign's REST API endpoint
			 * by adding, modifying, or removing properties from its JSON schema. This is useful
			 * for add-ons that need to save their own custom data alongside a campaign.
			 *
			 * The `add_additional_fields_schema` function is called after this filter, ensuring that
			 * any fields registered via `register_rest_field` are also included.
			 *
			 * @since 1.0.0
			 * @hook  campaignbay_campaign_schema
			 *
			 * @param array $schema The campaign item schema array.
			 * @return array The filtered campaign item schema array.
			 */
			return $this->add_additional_fields_schema( apply_filters( 'campaignbay_campaign_schema', $this->schema ) );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'campaign',
			'type'       => 'object',
			'properties' => array(
				'id'                 => array(
					'description' => __( 'Unique identifier for the campaign.', 'campaignbay' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'title'              => array(
					'description' => __( 'The title for the campaign.', 'campaignbay' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'required'    => true,
				),
				'status'             => array(
					'description' => __( 'A named status for the campaign.', 'campaignbay' ),
					'type'        => 'string',
					'enum'        => array( 'active', 'inactive', 'scheduled', 'expired' ),
					'context'     => array( 'view', 'edit' ),
				),
				'type'               => array(
					'description' => __( 'The core type of the campaign.', 'campaignbay' ),
					'type'        => 'string',
					'enum'        => array(
						'scheduled',
						'quantity',
						'earlybird',
						'bogo',
					),
					'context'     => array( 'view', 'edit' ),
					'required'    => true,
				),
				'discount_type'      => array(
					'description' => __( 'The type of discount (percentage, fixed, fixed_per_item).', 'campaignbay' ),
					'type'        => 'string',
					'enum'        => array( 'percentage', 'fixed', 'fixed_per_item' ),
					'context'     => array( 'view', 'edit' ),
				),
				'discount_value'     => array(
					'description' => __( 'The numeric value of the discount.', 'campaignbay' ),
					'type'        => 'number',
					'context'     => array( 'view', 'edit' ),
				),
				'tiers'              => array(
					'description' => __( 'The tiers of the campaign.', 'campaignbay' ),
					'type'        => 'array',
					'items'       => array( 'type' => 'object' ),
					'context'     => array( 'view', 'edit' ),
				),
				'target_type'        => array(
					'description' => __( 'The scope of the rule (store, categories, products, tags).', 'campaignbay' ),
					'type'        => 'string',
					'enum'        => array( 'entire_store', 'category', 'product', 'tag' ),
					'context'     => array( 'view', 'edit' ),
				),
				'target_ids'         => array(
					'description' => __( 'Array of category, product, or tag IDs.', 'campaignbay' ),
					'type'        => 'array',
					'items'       => array( 'type' => 'integer' ),
					'context'     => array( 'view', 'edit' ),
				),
				'is_exclude'         => array(
					'description' => __( 'Whether to exclude the specified targets.', 'campaignbay' ),
					'type'        => 'boolean',
					'context'     => array( 'view', 'edit' ),
				),
				'exclude_sale_items' => array(
					'description' => __( 'Prevent double-discounting.', 'campaignbay' ),
					'type'        => 'boolean',
					'context'     => array( 'view', 'edit' ),
				),
				'schedule_enabled'   => array(
					'description' => __( 'Whether scheduling is enabled for the campaign.', 'campaignbay' ),
					'type'        => 'boolean',
					'context'     => array( 'view', 'edit' ),
				),
				'start_datetime'     => array(
					'description' => __( 'The rule\'s start date/time (ISO 8601 string).', 'campaignbay' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'end_datetime'       => array(
					'description' => __( 'The rule\'s end date/time (ISO 8601 string).', 'campaignbay' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'usage_count'        => array(
					'description' => __( 'The number of times the campaign has been used.', 'campaignbay' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'usage_limit'        => array(
					'description' => __( 'The maximum number of times the campaign can be used.', 'campaignbay' ),
					'type'        => array( 'integer', 'null' ),
					'context'     => array( 'view', 'edit' ),
				),
				'date_created'       => array(
					'description' => __( 'The date the campaign was created.', 'campaignbay' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'date_modified'      => array(
					'description' => __( 'The date the campaign was last modified.', 'campaignbay' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'created_by'         => array(
					'description' => __( 'User ID who created the campaign.', 'campaignbay' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'updated_by'         => array(
					'description' => __( 'User ID who last updated the campaign.', 'campaignbay' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
			),
		);

		$this->schema = $schema;

		/**
		 * Filters the REST API schema for a single campaign item.
		 *
		 * This filter allows other developers to extend the campaign's REST API endpoint
		 * by adding, modifying, or removing properties from its JSON schema. This is useful
		 * for add-ons that need to save their own custom data alongside a campaign.
		 *
		 * The `add_additional_fields_schema` function is called after this filter, ensuring that
		 * any fields registered via `register_rest_field` are also included.
		 *
		 * @since 1.0.0
		 * @hook  campaignbay_campaign_schema
		 *
		 * @param array $schema The campaign item schema array.
		 * @return array The filtered campaign item schema array.
		 */
		return $this->add_additional_fields_schema( apply_filters( 'campaignbay_campaign_schema', $this->schema ) );
	}

	/**
	 * Defines the arguments for the bulk update endpoint.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return array
	 */
	private function get_bulk_update_args() {
		return array(
			'ids'    => array(
				'description' => __( 'An array of campaign IDs to update.', 'campaignbay' ),
				'type'        => 'array',
				'items'       => array( 'type' => 'integer' ),
				'required'    => true,
			),
			'status' => array(
				'description' => __( 'The new status to apply to the campaigns.', 'campaignbay' ),
				'type'        => 'string',
				'enum'        => array( 'active', 'inactive', 'scheduled' ),
				'required'    => true,
			),
		);
	}

	/**
	 * Defines the arguments for the bulk delete endpoint.
	 *
	 * @since 1.0.0
	 * @access private
	 * @return array
	 */
	private function get_bulk_delete_args() {
		return array(
			'ids' => array(
				'description' => __( 'An array of campaign IDs to delete.', 'campaignbay' ),
				'type'        => 'array',
				'items'       => array( 'type' => 'integer' ),
				'required'    => true,
			),
		);
	}

	/**
	 * Parses a malformed, non-standard JSON-like string into a PHP array.
	 *
	 * This function is designed as a fallback to repair a specific type of malformed
	 * string where keys and some string values are not properly quoted.
	 * For example: '[{ id: 0, type: percentage }]'
	 *
	 * WARNING: The most reliable solution is always to fix the source (JavaScript)
	 * to generate valid JSON using `JSON.stringify()`. This function should only be
	 * used if you cannot control the input source.
	 *
	 * @param string $malformed_string The malformed, JSON-like string.
	 * @return array|null Returns the decoded PHP array on success, or null on failure.
	 */
	public function parse_malformed_json_string( $malformed_string ) {
		// Step 0: Basic validation. If the input is not a string or is empty, fail early.
		if ( ! is_string( $malformed_string ) || empty( trim( $malformed_string ) ) ) {
			return null;
		}

		// Step 1: Wrap all unquoted keys (e.g., "id:", "max:") in double quotes.
		// This regex finds a word character (alphanumeric + underscore) followed by a colon
		// and wraps the word in double quotes.
		$json_fixed_keys = preg_replace( '/([a-zA-Z0-9_]+)\s*:/', '"$1":', $malformed_string );
		if ( null === $json_fixed_keys ) {
			// preg_replace can return null on error.
			return null;
		}

		// Step 2: Wrap unquoted string values (e.g., ": percentage,") in double quotes.
		// This regex finds a colon, optional space, and then a sequence of letters,
		// and wraps the letters in double quotes. It avoids quoting numbers.
		$json_fixed_values = preg_replace( '/: \s*([a-zA-Z_]+)/', ': "$1"', $json_fixed_keys );
		if ( null === $json_fixed_values ) {
			return null;
		}

		// Step 3: Now that the string should be valid JSON, decode it.
		$decoded_array = json_decode( $json_fixed_values, true );

		// Step 4: Final check. If json_decode failed, it returns null.
		// We also check json_last_error to be certain.
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return null;
		}

		return $decoded_array;
	}
}
