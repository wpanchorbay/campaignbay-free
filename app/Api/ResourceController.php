<?php
/**
 * File.
 *
 * @package campaignbay
 */

namespace WpabCampaignBay\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Import WordPress REST API classes
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WpabCampaignBay\Admin\Admin;

/**
 * Class used to manage a plugin's resources via the REST API.
 *
 * @since      1.1.5
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBay_Api_Settings
 */
class ResourceController extends ApiController {


	/**
	 * The single instance of the class.
	 *
	 * @since 1.1.5
	 * @var   ResourceController
	 * @access private
	 */
	private static $instance = null;

	/**
	 * Initialize the class and set up actions.
	 *
	 * @since 1.1.5
	 * @access public
	 * @return void
	 */
	public function run() {
		$this->type      = 'campaignbay_api_resources';
		$this->rest_base = 'resources';

		/*Custom Rest Routes*/
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API route.
	 *
	 * @since    1.1.5
	 * @access public
	 * @return void
	 */
	public function register_routes() {
		$namespace = $this->namespace . $this->version;

		register_rest_route(
			$namespace,
			'/' . $this->rest_base . '/users',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_user' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/' . $this->rest_base . '/products',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_products' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/' . $this->rest_base . '/buyable-products',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_buyable_products' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/' . $this->rest_base . '/categories',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_categories' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Get list of users.
	 *
	 * @since    1.1.5
	 * @access public
	 * @return WP_REST_Response
	 * @param mixed $request Description.
	 */
	public function get_user( $request ) {
		$args = array(
			'orderby' => 'display_name',
			'number'  => 50,
			'order'   => 'ASC',
		);

		$search = $request->get_param( 'search' );
		if ( ! empty( $search ) ) {
			$args['search'] = '*' . sanitize_text_field( $search ) . '*';
		}

		$users    = get_users( $args );
		$response = array();

		foreach ( $users as $user ) {
			$response[] = array(
				'id'    => $user->ID,
				'name'  => $user->display_name,
				'email' => $user->user_email,
			);
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Get all products including variable parents and variations, structured hierarchically.
	 *
	 * @since    1.1.5
	 * @access public
	 * @return WP_REST_Response
	 * @param mixed $request Description.
	 */
	public function get_products( $request ) {
		$args = array(
			'post_type'   => array( 'product', 'product_variation' ),
			'post_status' => 'publish',
			'numberposts' => 50,
			'orderby'     => 'title',
			'order'       => 'ASC',
		);

		$search = $request->get_param( 'search' );
		if ( ! empty( $search ) ) {
			$args['s'] = sanitize_text_field( $search );
		}

		$product_posts = get_posts( $args );

		$products = array();
		foreach ( $product_posts as $post ) {
			// If it's a variation (has a parent)
			if ( $post->post_parent > 0 ) {
				// Ensure the parent structure exists in our array first
				if ( ! isset( $products[ $post->post_parent ] ) ) {
					// Fetch parent title if missing (useful if parent wasn't in the initial query results)
					$parent_title                   = get_the_title( $post->post_parent );
					$products[ $post->post_parent ] = array(
						'id'       => $post->post_parent,
						'name'     => $parent_title,
						'variants' => array(),
					);
				}

				// Add variation to parent's 'variants' array
				$products[ $post->post_parent ]['variants'][] = array(
					'id'   => $post->ID,
					'name' => $post->post_title, // Often includes attributes like "Hoodie - Blue"
				);
			} else {
				// It's a parent/simple product
				if ( ! isset( $products[ $post->ID ] ) ) {
					$products[ $post->ID ] = array(
						'id'       => $post->ID,
						'name'     => $post->post_title,
						'variants' => array(), // Initialize empty array for potential variants
					);
				} else {
					// If it was created as a placeholder by a child loop above, just update the name
					$products[ $post->ID ]['name'] = $post->post_title;
				}
			}
		}

		// Re-index array keys to 0,1,2... for JSON response
		$products = array_values( $products );
		return rest_ensure_response( $products );
	}

	/**
	 * Get only "buyable" items (Simple products and individual Variations).
	 * Excludes Variable parent containers which cannot be added to cart directly.
	 *
	 * @since    1.1.5

	 * @return WP_REST_Response
	 * @param mixed $request Description.
	 */
	public function get_buyable_products( $request ) {
		$search_term = $request->get_param( 'search' );

		// 1. Get Simple Products
		$simple_args = array(
			'status' => 'publish',
			'limit'  => 25,
			'type'   => 'simple',
		);
		if ( ! empty( $search_term ) ) {
			$simple_args['s'] = sanitize_text_field( $search_term );
		}
		$simple_products = wc_get_products( $simple_args );

		// 2. Get Product Variations
		$variation_args = array(
			'status' => 'publish',
			'limit'  => 25,
			'type'   => 'variation',
		);
		// Note: Searching variations directly with 's' can be unreliable in standard WC queries.
		// For a simple implementation, we proceed. For complex search, a custom query might be needed.
		if ( ! empty( $search_term ) ) {
			$variation_args['s'] = sanitize_text_field( $search_term );
		}
		$variations = wc_get_products( $variation_args );

		$products = array();

		// Process Simple Products
		foreach ( $simple_products as $product ) {
			$products[] = array(
				'id'   => $product->get_id(),
				'name' => $product->get_name(),
				'type' => 'simple',
			);
		}

		// Process Variations
		foreach ( $variations as $product ) {
			$products[] = array(
				'id'   => $product->get_id(),
				'name' => $product->get_name(), // WC formats this as "Parent - Attribute"
				'type' => 'variation',
			);
		}

		return rest_ensure_response( $products );
	}

	/**
	 * Get product categories.
	 *
	 * @since    1.1.5

	 * @return WP_REST_Response
	 * @param mixed $request Description.
	 */
	public function get_categories( $request ) {
		$args = array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false, // Include categories that don't have products yet
			'orderby'    => 'name',
			'order'      => 'ASC',
		);

		$search = $request->get_param( 'search' );
		if ( ! empty( $search ) ) {
			$args['search'] = sanitize_text_field( $search );
		}

		$category_terms = get_terms( $args );

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

		return rest_ensure_response( $categories );
	}

	/**
	 * Prepares a value for output based off a schema array.
	 *
	 * @since 1.1.5
	 * @access public
	 * @param mixed $value  Value to prepare.
	 * @param array $schema Schema to match.
	 * @return mixed The prepared value.
	 */
	protected function prepare_value( $value, $schema ) {
		$sanitized_value = rest_sanitize_value_from_schema( $value, $schema );
		return $sanitized_value;
	}

	/**
	 * Gets an instance of this class.
	 * Prevents duplicate instances which avoid artefacts and improves performance.
	 *
	 * @since 1.1.5
	 * @access public
	 * @return ResourceController
	 */
	public static function get_instance() {
		// Store the instance locally to avoid private static replication.
		static $instance = null;
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
