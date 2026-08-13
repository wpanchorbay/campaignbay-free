<?php // phpcs:ignore Class file names should be based on the class name with "class-" prepended.

namespace WpabCampaignBay\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The common bothend functionality of the plugin.
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @since      1.0.0
 *
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBayincludes
 */

/**
 * The common bothend functionality of the plugin.
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @since      1.0.0
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBayincludes
 * @author     dipta-sdd <sankarsandipta@gmail.com>
 */
class Common {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var   Common
	 */
	private static $instance = null;

	/**
	 * The default settings of the plugin.
	 *
	 * @since 1.0.3
	 * @access private
	 * @var   array
	 */
	private $default_settings = array(
		/*
			==================================================
		* Global Settings Tab
		==================================================
	*/
		'global_enableAddon'                      => true,
		'global_calculate_discount_from'          => 'regular_price',
		'position_to_show_bulk_table'             => 'woocommerce_after_add_to_cart_form',
		'position_to_show_discount_bar'           => 'woocommerce_before_add_to_cart_form',

		/*
			==================================================
		* Performance & Caching (from Global Tab)
		==================================================
	*/
		'perf_enableCaching'                      => true,

		/*
			==================================================
		* Debugging & Logging (from Global Tab)
		==================================================
	*/
		'debug_enableMode'                        => true,

		/*
			==================================================
		* Product Settings Tab
		==================================================
	*/
		'product_message_format_percentage'       => 'You save {percentage_off}%',
		'product_message_format_fixed'            => 'You save {amount_off} per item',
		'bogo_banner_message_format'              => 'Buy {buy_quantity} and get {get_quantity} free!',
		'product_priorityMethod'                  => 'apply_highest',
		'show_discount_table'                     => 'true',
		'discount_table_options'                  => array(
			'show_header' => true,
			'title'       => array(
				'show'  => true,
				'label' => 'Title',
			),
			'range'       => array(
				'show'  => true,
				'label' => 'Range',
			),
			'discount'    => array(
				'show'    => true,
				'label'   => 'Discount',
				'content' => 'price',
			),
		),


		/*
			==================================================
		* Cart Settings Tab
		==================================================
	*/
		'cart_allowWcCouponStacking'              => false,
		'cart_allowCampaignStacking'              => false,
		'cart_quantity_message_format_percentage' => 'Add {remaining_quantity_for_next_offer} more and get {percentage_off}% off',
		'cart_quantity_message_format_fixed'      => 'Add {remaining_quantity_for_next_offer} more and get {amount_off} off per item!',
		'cart_bogo_message_format'                => '{title} discount applied.',

		/*
			==================================================
		* Advance Settings Tab
		==================================================
	*/
		'advanced_deleteAllOnUninstall'           => false,
	);

	/**
	 * The settings of the plugin.
	 *
	 * @since 1.0.3
	 * @access private
	 * @var   array
	 */
	private $settings = null;

	/**
	 * Gets an instance of this object.
	 * Prevents duplicate instances which avoid artefacts and improves performance.
	 *
	 * @access public
	 * @return Common
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
	 * Get the settings with caching.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param string $key optional meta key.
	 * @return array|null
	 */
	public function get_settings( $key = '' ) {
		if ( ! $this->settings ) {
			$this->load_settings();
		}
		if ( ! empty( $key ) ) {
			return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : false;
		}
		return $this->settings;
	}

	/**
	 * Get the default settings.
	 *
	 * @since 1.0.3
	 * @access public
	 * @return array
	 */
	public function get_default_settings() {
		return $this->default_settings;
	}

	/**
	 * Load the settings.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function load_settings() {
		$options = get_option( CAMPAIGNBAY_OPTION_NAME );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$default_settings = $this->get_default_settings();
		$settings         = array_merge( $default_settings, $options );
		$this->settings   = $settings;
	}

	/**
	 * Update the settings.
	 *
	 * @since 1.0.3
	 * @access public
	 * @param string $key_or_data The key or data to update.
	 * @param string $val The value to update.
	 * @return void
	 */
	public function update_settings( $key_or_data, $val = '' ) {
		if ( is_string( $key_or_data ) ) {
			$options                 = $this->get_settings();
			$options[ $key_or_data ] = $val;
		} else {
			// Merge onto the current settings rather than replacing wholesale, so a caller
			// passing a partial array (e.g. SettingsController::update_item(), which only
			// includes whatever fields the REST request actually sent) can't silently wipe
			// every other stored setting.
			$options = array_merge( $this->get_settings(), (array) $key_or_data );
		}
		update_option( CAMPAIGNBAY_OPTION_NAME, $options );
		$this->load_settings();
	}
	/**
	 * Get options related to white label.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return array|null
	 */
	public function get_white_label() {
		static $cache = null;
		if ( ! $cache ) {
			$cache = wpab_campaignbay_get_white_label();
		}

		return $cache;
	}

	/**
	 * Register scripts and styles
	 *
	 * @since    1.0.0
	 * @access   public
	 * @return void
	 */
	public function register_scripts_and_styles() {
	}
}
