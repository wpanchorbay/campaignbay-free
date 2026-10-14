<?php
/**
 * File.
 *
 * @package campaignbay
 */

namespace WpabCampaignBay\Admin;

use WpabCampaignBay\Core\Common;
use WpabCampaignBay\Helper\Woocommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBayadmin
 * @author     dipta-sdd <sankarsandipta@gmail.com>
 */
class Admin {


	/**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 * @var   Admin
	 * @access private
	 */
	private static $instance = null;

	/**
	 * Menu info.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $menu_info    Admin menu information.
	 */
	private $menu_info;





	/**
	 * Gets an instance of this object.
	 * Prevents duplicate instances which avoid artefacts and improves performance.
	 *
	 * @access public
	 * @return Admin
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
	 * Add Admin Page Menu page.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function add_admin_menu() {
		$white_label     = Common::get_instance()->get_white_label();
		$this->menu_info = array(
			'page_title' => $white_label['plugin_name'],
			'menu_title' => $white_label['menu_label'],
			'menu_slug'  => CAMPAIGNBAY_PLUGIN_NAME,
			'icon_url'   => $white_label['menu_icon'],
			'position'   => $white_label['position'],
			'docs_uri'   => $white_label['docs_uri'],
		);

		add_menu_page(
			$this->menu_info['page_title'],
			$this->menu_info['menu_title'],
			'manage_campaignbay',
			$this->menu_info['menu_slug'],
			array( $this, 'add_setting_root_div' ),
			$this->menu_info['icon_url'],
			$this->menu_info['position'],
		);
		add_submenu_page(
			$this->menu_info['menu_slug'],
			$this->menu_info['page_title'],
			esc_html__( 'Dashboard', 'campaignbay' ),
			'manage_campaignbay',
			CAMPAIGNBAY_TEXT_DOMAIN,
			array( $this, 'add_setting_root_div' )
		);
		$submenu_pages = array();

		$submenu_pages[] = array(
			'menu_title' => 'All Campaigns',
			'menu_slug'  => '#/campaigns',
		);
		$submenu_pages[] = array(
			'menu_title' => 'Add Campaign',
			'menu_slug'  => '#/campaigns/add',
		);
		$submenu_pages[] = array(
			'menu_title' => 'Calendar',
			'menu_slug'  => '#/calendar',
		);
		$submenu_pages[] = array(
			'menu_title' => 'Settings',
			'menu_slug'  => '#/settings',
		);
		foreach ( $submenu_pages as $submenu_page ) {
			add_submenu_page(
				$this->menu_info['menu_slug'],
				esc_html( $submenu_page['menu_title'] . '-' . $this->menu_info['page_title'] ),
				$submenu_page['menu_title'],
				'manage_campaignbay',
				CAMPAIGNBAY_TEXT_DOMAIN . $submenu_page['menu_slug'],
				array( $this, 'add_setting_root_div' )
			);
		}
	}

	/**
	 * Add "Campaign" to the admin bar "New" dropdown.
	 *
	 * @since 1.0.7
	 * @param mixed $admin_bar Description.
	 */
	public function add_admin_bar_new_item( $admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$href = admin_url( 'admin.php?page=' . CAMPAIGNBAY_PLUGIN_NAME . '#/campaigns/add' );

		// 3. Add the Node
		$args = array(
			'id'     => 'wpab_cb_new_campaign',
			'title'  => __( 'Campaign', 'campaignbay' ),
			'href'   => $href,
			'parent' => 'new-content',
		);

		$admin_bar->add_node( $args );

		// 1. Add the Main Parent Item
		$admin_bar->add_node(
			array(
				'id'    => 'campaignbay-admin-bar',
				'title' => 'CampaignBay',
				'href'  => admin_url( 'admin.php?page=campaignbay#/campaigns' ),
				'meta'  => array(
					'title' => __( 'Dashboard', 'campaignbay' ),
				),
			)
		);

		// 2. Add Submenu: Dashboard (Same as parent link)
		$admin_bar->add_node(
			array(
				'id'     => 'campaignbay-dashboard',
				'parent' => 'campaignbay-admin-bar',
				'title'  => __( 'Dashboard', 'campaignbay' ),
				'href'   => admin_url( 'admin.php?page=campaignbay' ),
			)
		);

		$admin_bar->add_node(
			array(
				'id'     => 'campaignbay-campaigns-add',
				'parent' => 'campaignbay-admin-bar',
				'title'  => __( 'Add Campaign', 'campaignbay' ),
				'href'   => admin_url( 'admin.php?page=campaignbay#/campaigns/add' ),
			)
		);
		$admin_bar->add_node(
			array(
				'id'     => 'campaignbay-campaigns',
				'parent' => 'campaignbay-admin-bar',
				'title'  => __( 'Campaigns', 'campaignbay' ),
				'href'   => admin_url( 'admin.php?page=campaignbay#/campaigns' ),
			)
		);

		// 3. Add Submenu: Calendar
		$admin_bar->add_node(
			array(
				'id'     => 'campaignbay-calendar',
				'parent' => 'campaignbay-admin-bar',
				'title'  => __( 'Calendar', 'campaignbay' ),
				'href'   => admin_url( 'admin.php?page=campaignbay#/calendar' ),
			)
		);
		// 3. Add Submenu: Settings
		$admin_bar->add_node(
			array(
				'id'     => 'campaignbay-settings',
				'parent' => 'campaignbay-admin-bar',
				'title'  => __( 'Settings', 'campaignbay' ),
				'href'   => admin_url( 'admin.php?page=campaignbay#/settings' ),
			)
		);
	}


	/**
	 * Check if current page is menu page.
	 *
	 * @access public
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_menu_page() {
		$screen              = get_current_screen();
		$admin_scripts_bases = array( 'toplevel_page_' . CAMPAIGNBAY_PLUGIN_NAME );
		if ( ! ( isset( $screen->base ) && in_array( $screen->base, $admin_scripts_bases, true ) ) ) {
			return false;
		}
		return true;
	}


	/**
	 * Add has sticky header class.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param string $classes The classes.
	 * @return string
	 */
	public function add_has_sticky_header( $classes ) {
		if ( $this->is_menu_page() ) {
			$classes .= ' at-has-hdr-stky ';
		}
		return $classes;
	}

	/**
	 * Add setting root div.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function add_setting_root_div() {
		echo '<div id="' . esc_attr( CAMPAIGNBAY_PLUGIN_NAME ) . '">
			<div class="campaignbay-loader-container">
				<div class="campaignbay-newtons-cradle">
					<div class="campaignbay-newtons-cradle__dot"></div>
					<div class="campaignbay-newtons-cradle__dot"></div>
					<div class="campaignbay-newtons-cradle__dot"></div>
					<div class="campaignbay-newtons-cradle__dot"></div>
				</div>
			</div>
		</div>';
	}

	/**
	 * Enqueue resources.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function enqueue_resources() {
		$this->enqueue_global_admin_styles();
		if ( ! $this->is_menu_page() ) {
			return;
		}

		$deps_file  = CAMPAIGNBAY_PATH . 'build/admin.asset.php';
		$dependency = array( 'wp-i18n' );
		$version    = CAMPAIGNBAY_VERSION;
		if ( file_exists( $deps_file ) ) {
			$deps_file  = require $deps_file;
			$dependency = $deps_file['dependencies'];
			$version    = $deps_file['version'];
		}

		$wordpress_version = get_bloginfo( 'version' );

		if ( version_compare( $wordpress_version, '6.8.0', '>=' ) ) {
			/**
			 * Filters the URL of the main admin JavaScript file.
			 *
			 * This allows developers or other plugins to override the default script path,
			 * which can be useful for integrations or custom build setups.
			 *
			 * @hook campaignbay_admin_script
			 * @since 1.0.0
			 *
			 * @param {string} $script_url The URL to the admin.js file.
			 */
			$admin_script = apply_filters( 'campaignbay_admin_script', CAMPAIGNBAY_URL . 'build/admin.js' );
			wp_enqueue_script( CAMPAIGNBAY_PLUGIN_NAME, $admin_script, $dependency, $version, true );
		} else {
			$version = CAMPAIGNBAY_VERSION;

			/**
			 * Filters the URL of the self-contained "legacy" admin JavaScript file.
			 *
			 * This allows developers or other plugins to override the default script path for
			 * the legacy build, which is used on older WordPress versions.
			 *
			 * @hook campaignbay_admin_legacy_script
			 * @since 1.0.0
			 *
			 * @param {string} $script_url The URL to the admin-legacy.js file.
			 */
			$admin_legacy_script = apply_filters( 'campaignbay_admin_legacy_script', CAMPAIGNBAY_URL . 'build/admin-legacy.js' );

			wp_enqueue_script( CAMPAIGNBAY_PLUGIN_NAME, CAMPAIGNBAY_URL . 'build/admin-legacy.js', array(), $version, true );
		}

		/**
		 * Filters the URL of the main admin CSS file.
		 *
		 * This allows developers to override the default stylesheet path for custom
		 * styling or integrations.
		 *
		 * @hook campaignbay_admin_css
		 * @since 1.0.0
		 *
		 * @param {string} $style_url The URL to the admin.css file.
		 */
		$admin_css = apply_filters( 'campaignbay_admin_css', CAMPAIGNBAY_URL . 'build/admin.css' );

		wp_enqueue_style( CAMPAIGNBAY_PLUGIN_NAME, $admin_css, array(), $version );

		wp_style_add_data( CAMPAIGNBAY_PLUGIN_NAME, 'rtl', 'replace' );

		$woocommerce_currency_symbol = Woocommerce::get_currency_symbol();

		$user_id                   = get_current_user_id();
		$onboarding_first_campaign = get_user_meta( $user_id, '_campaignbay_onboarding_first_campaign', true );

		$user_roles = get_editable_roles();
		$user_roles = array_map(
			function ( $key, $role ) {
				return array(
					'value' => $key,
					'name'  => $role['name'],
				);
			},
			array_keys( $user_roles ),
			$user_roles
		);

		/**
		 * Filters the data passed from PHP to the main admin JavaScript application.
		 *
		 * This array is made available in the frontend as a global JavaScript object
		 * (e.g., `window.wpab_cb_Localize`). It serves as the primary "bootstrap" data,
		 * providing the React application with all the necessary server-side information
		 * it needs to initialize and function correctly. This includes API details,
		 * security nonces, global settings, and localization data.
		 *
		 * @since 1.0.0
		 * @hook campaignbay_admin_localize
		 *
		 * @param array $localize An associative array of data to be passed to the JavaScript application.
		 *    @type string $version                 The current version of the plugin, useful for cache-busting or debugging.
		 *    @type string $root_id                 The ID of the HTML element where the React application will be mounted.
		 *    @type string $nonce                   The security nonce required for making authenticated WordPress REST API requests.
		 *    @type string $store                   A unique identifier for the plugin, often used for JavaScript state management stores (e.g., Redux).
		 *    @type string $rest_url                The root URL of the WordPress REST API (e.g., 'https://example.com/wp-json/'). Essential for making API calls robustly.
		 *    @type array  $white_label             An array of white-label settings (plugin name, author, support links, etc.) for display in the UI.
		 *    @type string $woocommerce_currency_symbol The active currency symbol for the WooCommerce store (e.g., '$').
		 *    @type array  $wpSettings              An array of core WordPress settings needed by the frontend.
		 *        @type string $dateFormat          The site's configured date format (e.g., 'F j, Y').
		 *        @type string $timeFormat          The site's configured time format (e.g., 'g:i a').
		 *    @type array  $campaignbay_settings    An array containing all the saved global settings for the CampaignBay plugin.
		 * @return array The filtered localization data array.
		 */
		$localize = apply_filters(
			'campaignbay_admin_localize',
			array(
				'version'                     => $version,
				'root_id'                     => CAMPAIGNBAY_PLUGIN_NAME,
				'nonce'                       => wp_create_nonce( 'wp_rest' ),
				'store'                       => CAMPAIGNBAY_PLUGIN_NAME,
				'rest_url'                    => get_rest_url(),
				'white_label'                 => Common::get_instance()->get_white_label(),
				'woocommerce_currency_symbol' => $woocommerce_currency_symbol,
				'onboarding'                  => array(
					'first_campaign' => $onboarding_first_campaign || false,
				),
				'wpSettings'                  => array(
					'dateFormat' => get_option( 'date_format' ),
					'timeFormat' => get_option( 'time_format' ),
					'user_roles' => $user_roles,
				),
				'campaignbay_settings'        => Common::get_instance()->get_settings(),
			)
		);

		wp_localize_script( CAMPAIGNBAY_PLUGIN_NAME, 'campaignbay_Localize', $localize );

		$path_to_check = CAMPAIGNBAY_PATH . 'languages';
		$result        = wp_set_script_translations(
			CAMPAIGNBAY_PLUGIN_NAME,
			'campaignbay',
			$path_to_check
		);
	}



	/**
	 * Enqueues CSS that should be present on all admin pages.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function enqueue_global_admin_styles() {
		wp_enqueue_style(
			'campaignbay-admin-menu',
			CAMPAIGNBAY_URL . 'assets/css/admin-menu.css',
			array(),
			CAMPAIGNBAY_VERSION
		);
	}


	/**
	 * Get settings schema.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return array settings schema for this plugin.
	 */
	public function get_settings_schema() {
		/**
		 * Filters the entire settings schema for the plugin.
		 *
		 * This schema is used with the WordPress `register_setting` function to define the
		 * structure, data types, default values, and sanitization callbacks for all of the
		 * plugin's global options. It powers the REST API endpoint that the React-based
		 * settings page uses to read and write data.
		 *
		 * The structure is a flat associative array where each key represents a single setting.
		 * A `[tab_name]_[setting_name]` naming convention is used to organize the settings
		 * logically, even though they are stored in a single database option.
		 *
		 * Developers can use this filter to add, modify, or remove settings from the CampaignBay
		 * settings page, allowing for powerful extensibility.
		 *
		 * @since 1.0.0
		 * @hook campaignbay_options_properties
		 *
		 * @param array $setting_properties The associative array of setting properties.
		 *    @type string $key The unique key for the setting (e.g., 'global_enableAddon').
		 *    @type array  $value An array defining the schema for the setting.
		 *        @type string   $type              The data type ('string', 'boolean', 'integer', 'object', 'array').
		 *        @type mixed    $default           The default value for the setting.
		 *        @type callable $sanitize_callback The function to use for sanitizing the setting's value upon saving.
		 *        @type array    $properties        For 'object' types, a nested associative array defining the properties of the object.
		 *
		 * @return array The filtered array of setting properties.
		 */
		$setting_properties = apply_filters(
			'campaignbay_options_properties',
			array(
				/*
				==================================================
				* Global Settings Tab
				==================================================
			*/
				'global_enableAddon'                      => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'global_calculate_discount_from'          => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_key',
					'default'           => 'regular_price',
				),
				'position_to_show_bulk_table'             => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_key',
					'default'           => 'woocommerce_after_add_to_cart_form',
				),
				'position_to_show_discount_bar'           => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_key',
					'default'           => 'woocommerce_before_add_to_cart_form',
				),

				/*
				==================================================
				* Performance & Caching (from Global Tab)
				==================================================
			*/
				'perf_enableCaching'                      => array(
					'type'    => 'boolean',
					'default' => true,
				),

				/*
				==================================================
				* Debugging & Logging (from Global Tab)
				==================================================
			*/
				'debug_enableMode'                        => array(
					'type'    => 'boolean',
					'default' => false,
				),

				/*
				==================================================
				* Product Settings Tab
				==================================================
			*/
				'product_message_format_percentage'       => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => 'You save {percentage_off}!',
				),
				'product_message_format_fixed'            => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => 'You save {amount_off} per item',
				),
				'bogo_banner_message_format'              => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => 'Buy {buy_quantity} and get {get_quantity} free!',
				),
				'product_priorityMethod'                  => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_key',
					'default'           => 'apply_highest',
				),
				'quantity_limit_show_product_message'     => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'quantity_limit_product_message_format'   => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => 'Purchase limits: {limits}',
				),
				'quantity_limit_message_min'              => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => 'The minimum quantity for "{product_name}" is {min_quantity}.',
				),
				'quantity_limit_message_max'              => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => 'The maximum quantity for "{product_name}" is {max_quantity}.',
				),
				'quantity_limit_message_step'             => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => '"{product_name}" must be purchased in multiples of {step}.',
				),
				'show_discount_table'                     => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'discount_table_options'                  => array(
					'type'       => 'object',
					'properties' => array(
						'show_header' => array(
							'type'    => 'boolean',
							'default' => true,
						),
						'title'       => array(
							'type'       => 'object',
							'properties' => array(
								'show'  => array(
									'type'    => 'boolean',
									'default' => true,
								),
								'label' => array(
									'type'              => 'string',
									'sanitize_callback' => 'sanitize_text_field',
									'default'           => 'Title',
								),
							),
						),
						'range'       => array(
							'type'       => 'object',
							'properties' => array(
								'show'  => array(
									'type'    => 'boolean',
									'default' => true,
								),
								'label' => array(
									'type'              => 'string',
									'sanitize_callback' => 'sanitize_text_field',
									'default'           => 'Range',
								),
							),
						),
						'discount'    => array(
							'type'       => 'object',
							'properties' => array(
								'show'    => array(
									'type'    => 'boolean',
									'default' => true,
								),
								'label'   => array(
									'type'              => 'string',
									'sanitize_callback' => 'sanitize_text_field',
									'default'           => 'Discount',
								),
								'content' => array(
									'type'              => 'string',
									'sanitize_callback' => 'sanitize_key',
									'default'           => 'price',
								),
							),
						),
					),
				),

				'discount_table_layout'                   => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_key',
					'default'           => 'table',
				),
				'discount_table_show_tier_label'          => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'discount_card_title_format'              => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => '{min} - {max} items',
				),
				'discount_card_price_format'              => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => '{price} each',
				),
				'discount_card_discount_format'           => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => 'Save {discount}',
				),
				'countdown_show_product'                  => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'countdown_show_loop'                     => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'countdown_show_cart'                     => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'countdown_format_product'                => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => 'Sale ends in {countdown}',
				),
				'countdown_format_loop'                   => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => 'Ends in {countdown}',
				),
				'countdown_format_cart'                   => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => 'Offer ends in {countdown}',
				),
				'countdown_format_shortcode'              => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => 'Offer ends in {countdown}',
				),
				'countdown_expired_message'               => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => 'This offer has ended.',
				),
				'badge_enabled'                           => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'badge_text'                              => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => 'Sale!',
				),
				'badge_bg_color'                          => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_hex_color',
					'default'           => '#e2401c',
				),
				'badge_text_color'                        => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_hex_color',
					'default'           => '#ffffff',
				),
				'category_offer_show'                     => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'category_offer_show_shop'                => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'category_offer_format'                   => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => 'Sale on {category_name}: {campaign_title} - {discount} off!',
				),
				'promote_show_product'                    => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'promote_show_category'                   => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'promote_format'                          => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => '{promo_text}',
				),

				/*
				==================================================
				* Cart Settings Tab
				==================================================
			*/
				'cart_allowWcCouponStacking'              => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'cart_allowCampaignStacking'              => array(
					'type'    => 'boolean',
					'default' => false,
				),
				'cart_quantity_message_format_percentage' => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => 'Add {remaining_quantity_for_next_offer} more and get {percentage_off}% off',
				),
				'cart_quantity_message_format_fixed'      => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => 'Add {remaining_quantity_for_next_offer} more and get {amount_off} off per item!',
				),
				'cart_bogo_message_format'                => array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => '{title} discount applied.',
				),

				/*
				==================================================
				* Advance Settings Tab
				==================================================
			*/
				'advanced_deleteAllOnUninstall'           => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
		);

		return array(
			'type'       => 'object',
			'properties' => $setting_properties,
		);
	}


	/**
	 * Register settings.
	 * Common callback function of rest_api_init and admin_init
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function register_settings() {
		$defaults = Common::get_instance()->get_default_settings();

		register_setting(
			'campaignbay_settings_group',
			CAMPAIGNBAY_OPTION_NAME,
			array(
				'type'              => 'object',
				'default'           => $defaults,
				'show_in_rest'      => array(
					'schema' => $this->get_settings_schema(),
				),
				'sanitize_callback' => array( $this, 'sanitize_settings_object' ),
			)
		);
	}

	/**
	 * Custom sanitization callback for the main settings object.
	 *
	 * This function receives the entire settings object and is responsible for
	 * sanitizing each individual property according to its expected type.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param array $input The raw array of settings data submitted for saving.
	 * @return array The sanitized array of settings data.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	 // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function sanitize_settings_object( $input ) {
		$schema          = $this->get_settings_schema();
		$properties      = $schema['properties'] ?? array();
		$default_options = Common::get_instance()->get_default_settings();

		$sanitized_output = get_option( CAMPAIGNBAY_OPTION_NAME, $default_options );

		// Loop through every property defined in our schema.
		foreach ( $properties as $key => $details ) {
			if ( ! isset( $input[ $key ] ) ) {
				continue;
			}

			$value = $input[ $key ];
			$type  = $details['type'] ?? 'string';

			switch ( $type ) {
				case 'boolean':
					$sanitized_output[ $key ] = (bool) $value;
					break;

				case 'integer':
					$sanitized_output[ $key ] = absint( $value );
					break;

				case 'string':
					$callback = $details['sanitize_callback'] ?? 'sanitize_text_field';
					if ( 'sanitize_hex_color' === $callback ) {
						$color                    = sanitize_hex_color( (string) $value );
						$sanitized_output[ $key ] = $color ? $color : ( $default_options[ $key ] ?? '' );
					} elseif ( 'discount_table_layout' === $key ) {
						$layout                   = sanitize_key( $value );
						$sanitized_output[ $key ] = in_array( $layout, array( 'table', 'cards' ), true ) ? $layout : 'table';
					} elseif ( 'sanitize_key' === $callback ) {
						$sanitized_output[ $key ] = sanitize_key( $value );
					} else {
						$sanitized_output[ $key ] = sanitize_text_field( $value );
					}
					break;

				case 'object':
					if ( is_array( $value ) ) {
						$sanitized_output[ $key ] = $this->sanitize_nested_object( $value, $details['properties'] );
					}
					break;

				case 'array':
					if ( is_array( $value ) ) {
						$sanitized_output[ $key ] = array_map( 'sanitize_text_field', $value );
					} else {
						$sanitized_output[ $key ] = array();
					}
					break;

				default:
					$sanitized_output[ $key ] = sanitize_text_field( $value );
					break;
			}
		}

		return $sanitized_output;
	}

	/**
	 * Add plugin menu items.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param string[] $actions     An array of plugin action links. By default this can include
	 *                              'activate', 'deactivate', and 'delete'. With Multisite active
	 *                              this can also include 'network_active' and 'network_only' items.
	 * @param string   $plugin_file Path to the plugin file relative to the plugins directory.
	 * @param array    $plugin_data An array of plugin data. See get_plugin_data()
	 *                              and the {@see 'plugin_row_meta'} filter for the list
	 *                              of possible values.
	 * @param string   $context     The plugin context. By default this can include 'all',
	 *                              'active', 'inactive', 'recently_activated', 'upgrade',
	 *                              'mustuse', 'dropins', and 'search'.
	 * @return array settings schema for this plugin.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function add_plugin_action_links( $actions, $plugin_file, $plugin_data, $context ) {
		$actions[] = '<a href="' . esc_url( menu_page_url( $this->menu_info['menu_slug'], false ) ) . '">' . esc_html__( 'Settings', 'campaignbay' ) . '</a>';
		return $actions;
	}


	/**
	 * Helper function to recursively sanitize nested objects.
	 * This is a pure function that takes input and a schema and returns clean output.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param array $input The input object (as an array).
	 * @param array $properties The schema properties for this object.
	 * @return array The sanitized object.
	 */
	private function sanitize_nested_object( $input, $properties ) {
		$output = array();

		// Loop through the schema's properties to ensure we only process expected keys.
		foreach ( $properties as $key => $details ) {
			// Only process if the key exists in the submitted input.
			if ( ! isset( $input[ $key ] ) ) {
				continue;
			}

			$value = $input[ $key ];
			$type  = $details['type'] ?? 'string';

			switch ( $type ) {
				case 'boolean':
					$output[ $key ] = (bool) $value;
					break;

				case 'string':
					// Also respect the specific sanitize_callback here.
					$callback = $details['sanitize_callback'] ?? 'sanitize_text_field';
					if ( function_exists( $callback ) ) {
						$output[ $key ] = call_user_func( $callback, $value );
					} else {
						$output[ $key ] = sanitize_text_field( $value );
					}
					break;

				case 'object':
					if ( is_array( $value ) ) {
						$output[ $key ] = $this->sanitize_nested_object( $value, $details['properties'] );
					}
					break;

				// Add other types as needed (integer, array, etc.)
				default:
					$output[ $key ] = sanitize_text_field( $value );
					break;
			}
		}

		return $output;
	}



	/**
	 * Add plugin row meta links.
	 *
	 * These are the links that appear in the second row, next to the version number.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param array  $links The existing meta links.
	 * @param string $file  The plugin file name.
	 * @return array The modified array of meta links.
	 */
	public function add_plugin_row_meta( $links, $file ) {
		if ( CAMPAIGNBAY_PLUGIN_BASENAME !== $file ) {
			return $links;
		}

		// Get the URLs from your white label settings.
		$white_label = Common::get_instance()->get_white_label();
		$docs_url    = $white_label['docs_uri'] ?? '#';
		$support_url = $white_label['support_uri'] ?? '#';

		// Add the new links.
		$row_meta = array(
			'docs'    => '<a href="' . esc_url( $docs_url ) . '" target="_blank">' . esc_html__( 'Docs', 'campaignbay' ) . '</a>',
			'support' => '<a href="' . esc_url( $support_url ) . '" target="_blank">' . esc_html__( 'Support', 'campaignbay' ) . '</a>',
		);

		return array_merge( $links, $row_meta );
	}
}
