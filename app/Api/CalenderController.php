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
use WpabCampaignBay\Engine\CalenderManager;

/**
 * Class used to manage a plugin's resources via the REST API.
 *
 * @since      1.0.0
 *
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBay_Api_Calender
 */

/**
 * Get Plugin's resources via the REST API.
 *
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBay_Api_Calender
 * @author     dipta-sdd <sankarsandipta@gmail.com>
 *
 * @see ApiController
 */
/**
 * ResourceController
 *
 * @see WP_REST_Calender_Controller
 * @package    WPAB_CampaignBay
 * @since 1.0.0
 */
class CalenderController extends ApiController {


	/**
	 * The single instance of the class.
	 *
	 * @since 1.1.1
	 * @var   ResourceController
	 * @access private
	 */
	private static $instance = null;
	/**
	 * Initialize the class and set up actions.
	 *
	 * @since 1.1.1
	 * @access public
	 * @return void
	 */
	public function run() {
		$this->type      = 'campaignbay_api_calender';
		$this->rest_base = 'calendar';

		/*Custom Rest Routes*/
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API route.
	 *
	 * @since    1.1.1
	 * @access public
	 * @return void
	 */
	public function register_routes() {
		$namespace = $this->namespace . $this->version;

		register_rest_route(
			$namespace,
			'/' . $this->rest_base . '/campaigns',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE, // POST
					'callback'            => array( $this, 'get_campaigns' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Function.
	 */
	public function get_campaigns( $request ) {
		$campaigns = CalenderManager::get_instance()->get_campaigns();
		return rest_ensure_response( array_values( $campaigns ) );
	}


	/**
	 * Gets an instance of this class.
	 * Prevents duplicate instances which avoid artefacts and improves performance.
	 *
	 * @since 1.0.0
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
