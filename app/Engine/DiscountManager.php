<?php
/**
 * File.
 *
 * @package campaignbay
 */

namespace WpabCampaignBay\Engine;

use WpabCampaignBay\Core\Base;
use WpabCampaignBay\Core\Common;

/**
 * The file that defines the DiscountManager class.
 *
 * A class definition that handles all pricing interactions with WooCommerce.
 *
 * @link       https://wpanchorbay.com
 * @since      1.0.0
 *
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBay/includes
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Pricing Engine class.
 *
 * This class is responsible for applying discount logic by hooking into WooCommerce
 * pricing filters and actions. It is the "engine" that drives the customer-facing changes.
 *
 * @since      1.0.0
 * @package    WPAB_CampaignBay
 * @author     WP Anchor Bay <wpanchorbay@gmail.com>
 */
class DiscountManager extends Base {




	/**
	 * Description.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var array
	 */
	private $settings = array();


	/**
	 * Constructor to define and build the hooks array.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		parent::__construct();
		$this->settings = Common::get_instance()->get_settings();
		$this->define_hooks();
	}

	/**
	 * Defines all hooks this class needs to run.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function define_hooks() {
		$hooks = array(
			array( 'filter', 'campaignbay_get_product', 'add_discount_data', 20, 2 ),
		);
		foreach ( $hooks as $hook ) {
			$this->add_hook( ...$hook );
		}
	}

	/**
	 * Adds discount data to the product.
	 *
	 * @since 1.0.0
	 *
	 * @param object $product The product object.
	 * @param int    $product_id The product ID.
	 * @return object The product object with discount data added.
	 */
	public function add_discount_data( $product, $product_id ) {
		$product = ProductDiscount::create( $product )->apply_discounts()->get_product();

		return $product;
	}
}
