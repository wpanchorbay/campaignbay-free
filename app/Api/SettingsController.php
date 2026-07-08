<?php

namespace WpabCampaignBay\Api;

use WpabCampaignBay\Core\Common;

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
 * Class used to manage a plugin's settings via the REST API.
 *
 * @since      1.0.0
 *
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBay_Api_Settings
 */

/**
 * Plugin's settings via the REST API.
 *
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBay_Api_Settings
 * @author     dipta-sdd <sankarsandipta@gmail.com>
 *
 * @see ApiController
 */
/**
 * SettingsController
 *
 * @see WP_REST_Settings_Controller
 * @package    WPAB_CampaignBay
 * @since 1.0.0
 */
class SettingsController extends ApiController {


	/**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 * @var   SettingsController
	 * @access private
	 */
	private static $instance = null;
	/**
	 * Initialize the class and set up actions.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function run() {
		$this->type      = 'campaignbay_api_settings';
		$this->rest_base = 'settings';

		/*Custom Rest Routes*/
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API route.
	 *
	 * @since    1.0.0
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
					'callback'            => array( $this, 'get_item' ),
					'args'                => array(),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'args'                => rest_get_endpoint_args_for_schema( $this->get_item_schema(), WP_REST_Server::EDITABLE ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$namespace,
			'/' . $this->rest_base . '/onboarding',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE, // POST
					'callback'            => array( $this, 'update_onboarding_status' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);
	}





	/**
	 * Retrieves the settings.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return array|WP_Error Array on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$response = array();

		/**
		 * Filters the settings.
		 *
		 * @since 1.0.3
		 * @hook campaignbay_get_settings
		 *
		 * @param array $settings The settings.
		 * @return array The filtered settings.
		 */
		$saved_options = apply_filters( 'campaignbay_get_settings', Common::get_instance()->get_settings() );
		$schema        = $this->get_registered_schema();
		$response      = $this->prepare_value( $saved_options, $schema );

		return $response;
	}

	/**
	 * Prepares a value for output based off a schema array.
	 *
	 * @since 1.0.0
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
	 * Updates settings.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return array|WP_Error Array on success, or error object on failure.
	 */
	public function update_item( $request ) {
		$schema = $this->get_registered_schema();
		$params = $request->get_params();

		if ( is_wp_error( rest_validate_value_from_schema( $params, $schema ) ) ) {

			return new WP_Error(
				'rest_invalid_stored_value',
				/* translators: %s: The name of the plugin setting or property. */
				sprintf( __( 'The %s property has an invalid stored value, and cannot be updated to null.', 'campaignbay' ), CAMPAIGNBAY_OPTION_NAME ),
				array( 'status' => 500 )
			);
		}

		$sanitized_options = $this->prepare_value( $params, $schema );
		Common::get_instance()->update_settings( $sanitized_options );
		$response = $this->prepare_value( $sanitized_options, $schema );

		return $response;
	}

	/**
	 * Mark the guide as seen.
	 *
	 * @since 1.0.2
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_onboarding_status( $request ) {
		$user_id    = get_current_user_id();
		$params     = $request->get_params();
		$onboarding = $params['first_campaign'];
		if ( ! $user_id ) {
			return new \WP_Error( 'no_user', 'Invalid user', array( 'status' => 401 ) );
		}

		if ( $onboarding ) {
			update_user_meta( $user_id, '_campaignbay_onboarding_first_campaign', true );
			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => 'Onboarding status updated successfully',
				),
				200
			);
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Retrieves all of the registered options for the Settings API.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return array Array of registered options.
	 */
	protected function get_registered_schema() {
		// Use a static variable to cache the schema.
		static $cached_schema = null;

		// If the schema is already cached, return it.
		if ( null !== $cached_schema ) {
			return $cached_schema;
		}

		// If not cached, fetch the value and cache it.
		$schema = Admin::get_instance()->get_settings_schema();

		// Cache the schema in the static variable.
		$cached_schema = $schema;

		return $schema;
	}

	/**
	 * Retrieves the site setting schema, conforming to JSON Schema.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => $this->type,
			'type'       => 'object',
			'properties' => $this->get_registered_schema()['properties'],
		);

		/**
		 * Filters the REST API schema for the global settings object.
		 *
		 * This filter allows other developers to extend the plugin's main settings page
		 * by adding their own setting fields to the REST API endpoint. Any properties
		 * added here should also have their default values added via the
		 * `campaignbay_default_options` filter to ensure proper functionality.
		 *
		 * @since 1.0.0
		 * @hook  campaignbay_rest_settings_item_schema
		 *
		 * @param array $schema The associative array defining the entire settings schema.
		 * @return array The filtered settings schema array.
		 */
		$schema = apply_filters( 'campaignbay_rest_settings_item_schema', $schema );

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}



	/**
	 * Gets an instance of this class.
	 * Prevents duplicate instances which avoid artefacts and improves performance.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return SettingsController
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
