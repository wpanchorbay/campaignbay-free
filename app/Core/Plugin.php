<?php // phpcs:ignore Class file names should be based on the class name with "class-" prepended.

namespace WpabCampaignBay\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Use statements to import the classes we need

use WpabCampaignBay\Admin\Admin;
use WpabCampaignBay\Api\ActivityLogController;
use WpabCampaignBay\Api\CalenderController;
use WpabCampaignBay\Api\CampaignsController;
use WpabCampaignBay\Api\DashboardController;
use WpabCampaignBay\Api\LogsController;
use WpabCampaignBay\Api\ResourceController;
use WpabCampaignBay\Api\SettingsController;
use WpabCampaignBay\Core\Scheduler;
use WpabCampaignBay\Engine\CalenderManager;
use WpabCampaignBay\Engine\CampaignManager;
use WpabCampaignBay\Engine\DiscountManager;
use WpabCampaignBay\Engine\OrderManager;
use WpabCampaignBay\Engine\PricingEngine;
use WpabCampaignBay\Helper\Loader;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBayincludes
 * @author     dipta-sdd <sankarsandipta@gmail.com>
 */
class Plugin {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 * @var   Plugin
	 * @access private
	 */
	private static $instance = null;
	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;


	/**
	 * Get the instance of the Plugin class.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return Plugin
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
	 * Define the core functionality of the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 * @access public
	 * @return void
	 */
	public function __construct() {
		// Initialize the loader first
		$this->loader = Loader::get_instance();
		$this->define_core_hooks();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}



	/**
	 * Register all of the hooks related to the core functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return void
	 */
	private function define_core_hooks() {

		// Initialize API controllers
		SettingsController::get_instance()->run();
		CampaignsController::get_instance()->run();
		LogsController::get_instance()->run();
		ActivityLogController::get_instance()->run();
		DashboardController::get_instance()->run();
		ResourceController::get_instance()->run();
		Scheduler::get_instance()->run();
		CalenderController::get_instance()->run();

		// Get instances of components that have hooks
		$discount_manager      = DiscountManager::get_instance();
		$campaign_manager      = CampaignManager::get_instance();
		$pricing_engine        = PricingEngine::get_instance();
		$order_manager         = OrderManager::get_instance();
		$scheduler             = Scheduler::get_instance();
		$calender_manager      = CalenderManager::get_instance();
		$components_with_hooks = array(
			$discount_manager,
			$campaign_manager,
			$pricing_engine,
			$order_manager,
			$scheduler,
			$calender_manager,
		);

		foreach ( $components_with_hooks as $component ) {
			$hooks = $component->get_hooks();
			foreach ( $hooks as $hook ) {
				if ( 'action' === $hook['type'] ) {
					$this->loader->add_action(
						$hook['hook'],
						$component,
						$hook['callback'],
						$hook['priority'],
						$hook['accepted_args']
					);
				} elseif ( 'filter' === $hook['type'] ) {
					$this->loader->add_filter(
						$hook['hook'],
						$component,
						$hook['callback'],
						$hook['priority'],
						$hook['accepted_args']
					);
				}
			}
		}
	}

	/**
	 * Register all of the hooks related to the public area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return void
	 */
	private function define_public_hooks() {
		if ( is_admin() ) {
			return;
		}
		// Enqueue the public CSS for the plugin.
		$this->loader->add_action(
			'wp_enqueue_scripts',
			$this,
			'enqueue_public_styles'
		);
	}

	/**
	 * Enqueue the public CSS for the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return void
	 */
	public function enqueue_public_styles() {
		wp_enqueue_style( CAMPAIGNBAY_OPTION_NAME . 'public', CAMPAIGNBAY_URL . 'assets/css/public.css', array(), CAMPAIGNBAY_VERSION );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @return void
	 */
	private function define_admin_hooks() {
		$plugin_admin = Admin::get_instance();
		// if (!is_admin()) {
		// return;
		// }
		$this->loader->add_filter( 'all_plugins', $this, 'change_plugin_display_name' );

		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
		$this->loader->add_filter( 'admin_body_class', $plugin_admin, 'add_has_sticky_header' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_resources' );

		/*Register Settings*/
		$this->loader->add_action( 'rest_api_init', $plugin_admin, 'register_settings' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );
		$plugin_basename = plugin_basename( CAMPAIGNBAY_PATH . 'campaignbay.php' );
		$this->loader->add_filter( 'plugin_action_links_' . $plugin_basename, $plugin_admin, 'add_plugin_action_links', 10, 4 );
		$this->loader->add_filter( 'plugin_row_meta', $plugin_admin, 'add_plugin_row_meta', 10, 2 );
		$this->loader->add_action( 'admin_bar_menu', $plugin_admin, 'add_admin_bar_new_item', 100 );
	}

	/**
	 * Changes the plugin's display name on the plugins page.
	 *
	 * @since 1.0.1
	 * @param array $plugins The array of all plugin data.
	 * @return array The modified array of plugin data.
	 */
	public function change_plugin_display_name( $plugins ) {
		$plugin_basename = plugin_basename( CAMPAIGNBAY_PATH . 'campaignbay.php' );

		if ( isset( $plugins[ $plugin_basename ] ) ) {
			$plugins[ $plugin_basename ]['Name'] = 'CampaignBay';
		}

		return $plugins;
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 * @access public
	 * @return void
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @access public
	 * @return    Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}
}
