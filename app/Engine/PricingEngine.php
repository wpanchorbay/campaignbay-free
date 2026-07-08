<?php

namespace WpabCampaignBay\Engine;

use WC_Product;
use WP_Error;
use WpabCampaignBay\Core\Base;
use WpabCampaignBay\Core\Common;
use WpabCampaignBay\Helper\Helper;
use WpabCampaignBay\Helper\Woocommerce;

/**
 * The file that defines the Pricing Engine class.
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
class PricingEngine extends Base {

	/**
	 * The settings array.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $settings = array();

	/**
	 * The coupons array.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	public $coupons = array();

	/**
	 * Whether a discount has been applied.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $discount_applied = false;

	/**
	 * Whether totals have been calculated.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $calculated_totals = false;


	/**
	 * The cart level condition campaigns array.
	 *
	 * Stores the campaigns that have cart level conditions. we will apply discount on
	 * these campaigns after cart total calculation.
	 *
	 * @since 1.1.17
	 * @var array
	 */
	private $cart_level_condition_campaigns = array();

	/**
	 * Whether we are calculating cart total for cart level conditions.
	 *
	 * @since 1.1.17
	 * @var bool
	 */
	protected $calculating_cart_total_for_cart_level_conditions = false;

	private $calculated_cart = null;

	/**
	 * Constructor to define and build the hooks array.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {

		parent::__construct();
		$this->settings = Common::get_instance()->get_settings();

		if ( $this->settings['global_enableAddon'] ) {
			$this->define_hooks();
		}
	}

	/**
	 * Defines all hooks this class needs to run.
	 *
	 * @since 1.0.0
	 * @access private
	 */
	private function define_hooks() {
		// woocommerce_product_variation_get_price
		$bulk_table_position = $this->settings['position_to_show_bulk_table'] ?? 'woocommerce_after_add_to_cart_form';
		$banner_position     = $this->settings['position_to_show_discount_bar'] ?? 'woocommerce_before_add_to_cart_form';
		$hooks               = array(
			array( 'filter', 'woocommerce_get_price_html', 'get_price_html', 20, 2 ),
			array( 'filter', 'woocommerce_variable_price_html', 'get_variable_price_html', 20, 2 ),
			array( 'filter', 'woocommerce_product_is_on_sale', 'is_on_sale', 20, 2 ),
			array( 'action', $banner_position, 'display_product_discount_message', 20, 0 ),
			array( 'action', $bulk_table_position, 'display_product_quantity_table', 20, 0 ),

			array( 'action', 'woocommerce_before_cart', 'ensure_cart_calculate_totals', 20, 1 ),
			array( 'action', 'woocommerce_before_mini_cart', 'ensure_cart_calculate_totals', 20, 1 ),
			array( 'action', 'woocommerce_before_mini_cart_contents', 'ensure_cart_calculate_totals', 20, 1 ),

			// ['action', 'woocommerce_before_calculate_totals', 'before_calculate_totals', 20, 1],
			array( 'filter', 'woocommerce_get_shop_coupon_data', 'validate_fake_coupon_data', 10, 2 ),
			array( 'filter', 'woocommerce_cart_totals_coupon_label', 'change_virtual_coupon_label', 10, 2 ),
			array( 'filter', 'woocommerce_coupon_is_valid', 'validate_fake_coupon', 10, 3 ),
			array( 'filter', 'woocommerce_cart_item_price', 'cart_item_price', 10, 3 ),
			array( 'filter', 'woocommerce_cart_item_subtotal', 'cart_item_subtotal', 10, 3 ),
			array( 'filter', 'woocommerce_cart_item_name', 'cart_item_name', 10, 3 ),
			array( 'action', 'woocommerce_after_cart_item_quantity_update', 'after_cart_item_quantity_update', 10, 4 ),
			array( 'action', 'woocommerce_checkout_create_order', 'save_discount_breakdown_to_order_meta', 10, 2 ),
			array( 'action', 'woocommerce_store_api_checkout_update_order_meta', 'save_discount_breakdown_to_order_meta', 10, 1 ),
		);

		add_action( 'woocommerce_before_calculate_totals', array( $this, 'before_calculate_totals' ), 20, 1 );
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'after_calculate_totals' ), 20, 1 );

		foreach ( $hooks as $hook ) {
			$this->add_hook( ...$hook );
		}
	}



	/**
	 * Save the discount breakdown to the order meta.
	 *
	 * @since 1.0.0
	 * @hook woocommerce_checkout_create_order
	 * @hook woocommerce_store_api_checkout_update_order_meta
	 * @param WC_Order $order The order object.
	 * @param array    $data The order data.
	 */
	public function save_discount_breakdown_to_order_meta( $order, $data = null ) {
		// The cart object is available globally via WC()->cart at this point.
		$cart = WC()->cart;
		if ( ! $cart ) {
			return;
		}

		$discount_breakdown = $cart->campaignbay_discount_breakdown ?? array();

		$applied_coupons = $cart->get_coupon_discount_totals();
		$our_coupons     = $this->coupons;
		if ( is_array( $applied_coupons ) && ! empty( $applied_coupons ) && ! empty( $our_coupons ) ) {
			foreach ( $applied_coupons as $key => $value ) {
				if ( ! isset( $our_coupons[ $key ] ) ) {
					continue;
				}
				$coupon      = $our_coupons[ $key ];
				$campaign_id = $coupon['campaign'];
				if ( ! isset( $discount_breakdown[ $campaign_id ] ) ) {
					$discount_breakdown[ $campaign_id ] = array(
						'campaign_title' => $coupon['campaign_title'],
						'old_price'      => 0,
						'discount'       => 0,
					);
				}
				$discount_breakdown[ $campaign_id ]['old_price'] = $discount_breakdown[ $campaign_id ]['old_price'] + $coupon['old_price'];
				$discount_breakdown[ $campaign_id ]['discount']  = $discount_breakdown[ $campaign_id ]['discount'] + $value;
			}
		}

		// Save the entire breakdown array to a single meta key on the order.
		$order->update_meta_data( '_campaignbay_discount_breakdown', $discount_breakdown );
		wpab_campaignbay_log( sprintf( 'Saved discount breakdown to order #%d .', $order->get_id() ), 'INFO' );
	}

	/**
	 * Get the price HTML for a product.
	 *
	 * @since 1.0.0
	 * @param string     $price_html The price HTML.
	 * @param WC_Product $product The product object.
	 * @return string The price HTML.
	 */
	public function get_price_html( $price_html, $product ) {
		if ( Woocommerce::product_type_is( $product, 'variable' ) ) {
			return $price_html;
		}
		$meta = Woocommerce::get_product( $product->get_id() )->get_meta( 'campaignbay' );

		if ( ! is_array( $meta ) || empty( $meta ) || ! $meta['on_discount'] || ! isset( $meta['simple'] ) ) {
			return Woocommerce::get_price_html(
				$price_html,
				$product,
				Woocommerce::get_product_regular_price( $product ),
				$product->get_price(),
				false
			);
		}
		$price_html = Woocommerce::get_price_html(
			$price_html,
			$product,
			$meta['original_price'],
			$meta['simple']['price'],
			$meta['simple']['display_as_regular_price']
		);
		return $price_html;
	}

	/**
	 * Get the variable price HTML for a product.
	 *
	 * @since 1.0.0
	 * @param string     $price_html The price HTML.
	 * @param WC_Product $product The product object.
	 * @return string The price HTML.
	 */
	public function get_variable_price_html( $price_html, $product ) {
		if ( ! Woocommerce::product_type_is( $product, 'variable' ) ) {
			return $price_html;
		}
		$meta = Woocommerce::get_product( $product->get_id() )->get_meta( 'campaignbay' );
		if ( ! is_array( $meta ) || empty( $meta ) || ! $meta['on_discount'] || ! isset( $meta['simple'] ) ) {
			return $price_html;
		}

		$prices = Woocommerce::get_variation_prices( $product );
		foreach ( $meta['simple'] as $key => $value ) {
			$prices['price'][ $key ] = $value['price'];
			if ( $value['display_as_regular_price'] !== null && $value['display_as_regular_price'] === true ) {
				$prices['regular_price'][ $key ] = $value['price'];
			}
		}
		$min_price     = current( $prices['price'] );
		$max_price     = end( $prices['price'] );
		$min_reg_price = current( $prices['regular_price'] );
		$max_reg_price = end( $prices['regular_price'] );

		if ( $min_price !== $max_price ) {
			$price_html = wc_format_price_range( $min_price, $max_price );
		} else {
			$price_html = Woocommerce::format_price( $min_price );
		}
		if ( $min_reg_price !== $max_reg_price ) {
			$reg_price = wc_format_price_range( $min_reg_price, $max_reg_price );
		} else {
			$reg_price = Woocommerce::format_price( $min_reg_price );
		}
		if ( $min_reg_price !== $min_price && $max_reg_price !== $max_price ) {
			$price_html = wc_format_sale_price( $reg_price, $price_html );
		}
		return $price_html;
	}

	/**
	 * Get the cart item price.
	 *
	 * @since 1.0.0
	 * @param string $price_html The price HTML.
	 * @param array  $cart_item The cart item.
	 * @param string $cart_item_key The cart item key.
	 * @return string The price HTML.
	 */
	public function cart_item_price( $price_html, $cart_item, $cart_item_key ) {
		// wpab_campaignbay_log('cart item price ' . $cart_item['data']->get_name());
		$meta = isset( $cart_item['campaignbay'] ) ? $cart_item['campaignbay'] : null;
		if ( $meta === null || ! isset( $meta['on_discount'] ) || ! $meta['on_discount'] ) {
			return $price_html;
		}

		$base_price   = $meta['base_price'];
		$price        = $base_price;
		$as_reg_price = false;
		if ( isset( $meta['quantity'] ) ) {
			$quantity = $meta['quantity'];
			// seting simple discount as base price
			if ( isset( $meta['simple']['display_as_regular_price'] ) && $meta['simple']['display_as_regular_price'] === true ) {
				$base_price = $quantity['base_price'];
			}
			if ( $quantity['settings']['apply_as'] === 'line_total' ) {
				$price = $quantity['price'];
			}
		} elseif ( isset( $meta['simple'] ) ) {
			$price        = $meta['simple']['price'];
			$as_reg_price = $meta['simple']['display_as_regular_price'];
			$base_price   = $meta['base_price'];
		} else {
			return $price_html;
		}
		$price_html = Woocommerce::get_price_html(
			$price_html,
			$cart_item['data'],
			$base_price,
			$price,
			$as_reg_price
		);
		return $price_html;
	}

	/**
	 * Get the cart item subtotal.
	 *
	 * @since 1.0.0
	 * @param string $price_html The price HTML.
	 * @param array  $cart_item The cart item.
	 * @param string $cart_item_key The cart item key.
	 * @return string The price HTML.
	 */
	public function cart_item_subtotal( $price_html, $cart_item, $cart_item_key ) {

		$meta = isset( $cart_item['campaignbay'] ) ? $cart_item['campaignbay'] : null;
		if ( $meta === null || ! isset( $meta['on_discount'] ) || ! $meta['on_discount'] ) {
			return $price_html;
		}

		$base_price = $meta['base_price'];
		$price      = $base_price;

		$as_reg_price = false;

		if ( isset( $meta['quantity'] ) ) {
			$quantity = $meta['quantity'];
			// seting simple discount as base price
			if ( isset( $meta['simple']['display_as_regular_price'] ) && $meta['simple']['display_as_regular_price'] === true ) {
				$base_price = $quantity['base_price'];
			}
			if ( $quantity['settings']['apply_as'] === 'line_total' ) {
				$price = $quantity['price'];
			}
		} elseif ( isset( $meta['simple'] ) ) {
			$price        = $meta['simple']['price'];
			$as_reg_price = $meta['simple']['display_as_regular_price'];
		}

		$price_html = Woocommerce::get_price_html(
			$price_html,
			$cart_item['data'],
			$base_price * $cart_item['quantity'],
			$price * $cart_item['quantity'],
			$as_reg_price
		);
		return $price_html;
	}

	/**
	 * Get the cart item name.
	 *
	 * @since 1.0.0
	 * @hook woocommerce_cart_item_name
	 *
	 * @param string $name The name.
	 * @param array  $cart_item The cart item.
	 * @param string $cart_item_key The cart item key.
	 * @return string The name.
	 */
	public function cart_item_name( $name, $cart_item, $cart_item_key ) {
		if ( isset( $cart_item['is_campaignbay_free_product'] ) && $cart_item['is_campaignbay_free_product'] === true ) {
			$data     = wpab_campaignbay_get_value( $cart_item, 'campaignbay_parent.data' );
			$message  = Helper::get_bogo_cart_message( $data );
			$location = wpab_campaignbay_get_value( $data, 'settings.bogo_cart_message_location' );
			if ( $message !== '' || $message !== null ) {
				if ( $location === 'line_item_name' ) {
					$name .= '<br/><span>' . $message . '</span>';
				}
			}
			return $name;
		}
		if ( ! isset( $cart_item['campaignbay'] ) ) {
			return $name;
		}
		$meta = $cart_item['campaignbay'];
		if ( isset( $meta['quantity_next_tier'] ) ) {
			$cart_quantity_message_location = wpab_campaignbay_get_value( $meta['quantity_next_tier'], 'settings.cart_quantity_message_location', 'line_item_name' );
			if ( $cart_quantity_message_location !== 'line_item_name' ) {
				return $name;
			}
			$message = Helper::get_quantity_message( $meta['quantity_next_tier'] );
			if ( $message !== '' || $message !== null ) {
				$name .= '<br/><span>' . $message . '</span>';
			}
		}
		// if (
		// isset($meta['is_bogo']) &&
		// $meta['is_bogo'] === true &&
		// isset($meta['bogo'])
		// ) {
		// $message = Helper::get_bogo_cart_message($meta['bogo']);
		// $location = $meta['bogo']['settings']['bogo_cart_message_location'];
		// if ($message !== '' || $message !== null) {
		// if ($location === 'line_item_name')
		// $name .= '<br/><span>' . $message . '</span>';
		// elseif ($location === 'notice')
		// Woocommerce::wc_add_notice($message, 'success');
		// }
		// }
		return $name;
	}

	/**
	 * Check if a product is on sale.
	 *
	 * @since 1.0.0
	 * @param bool       $is_on_sale The is on sale flag.
	 * @param WC_Product $product The product object.
	 * @return bool The is on sale flag.
	 */
	public function is_on_sale( $is_on_sale, $product ) {
		if ( $is_on_sale ) {
			return $is_on_sale;
		}

		$meta = Woocommerce::get_product( $product->get_id() )->get_meta( 'campaignbay' );
		if ( is_array( $meta ) && ! empty( $meta ) && isset( $meta['is_on_sale'] ) && $meta['is_on_sale'] === true ) {
			return true;
		}

		$quantity_tiers = Helper::get_quantity_tiers_with_campaign( $product );
		if ( ! empty( $quantity_tiers ) ) {
			return true;
		}
		return $is_on_sale;
	}

	/**
	 * Displays a promotional discount message on the single product page.
	 *
	 * This function retrieves pre-calculated discount metadata that was attached to the
	 * global product object by the main pricing engine. It then generates and renders
	 * a user-facing notice (e.g., "You save 20%!") based on that data.
	 *
	 * This function is intended to be used with a WooCommerce action hook like
	 * `woocommerce_before_add_to_cart_form`.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_product_discount_message() {
		global $product;
		if ( ! $product ) {
			return;
		}

		$meta = Woocommerce::get_product( $product->get_id() )->get_meta( 'campaignbay' );

		/**
		 * Action hook that fires before any discount message is rendered for a product.
		 *
		 * This allows other functions or third-party add-ons to add custom content
		 * before the main CampaignBay message is displayed.
		 *
		 * @since 1.0.0
		 * @hook campaignbay_before_product_display_product_discount_message
		 *
		 * @param WC_Product $product The current product object.
		 * @param array      $meta    The pre-calculated discount metadata for the product.
		 */
		do_action( 'campaignbay_before_product_display_product_discount_message', $product, $meta );
		Helper::render_product_bogo_message( $product );
		if ( ! is_array( $meta ) || empty( $meta ) || ! $meta['on_discount'] || ! isset( $meta['simple'] ) ) {
			return;
		}

		// if a variable product has a simple campaign, use the first simple campaign
		if ( Woocommerce::product_type_is( $product, 'variable' ) ) {
			foreach ( $meta['simple'] as $simple ) {
				// if the simple campaign is set to display as regular price, return
				if ( wpab_campaignbay_get_value( $simple, 'display_as_regular_price', false ) ) {
					return;
				}
				if ( wpab_campaignbay_get_value( $simple, 'show_product_message', false ) === false ) {
					return;
				}
				$value  = wpab_campaignbay_get_value( $simple, 'value', 0 );
				$type   = wpab_campaignbay_get_value( $simple, 'type', 'percentage' );
				$format = wpab_campaignbay_get_value( $simple, 'message_format' );
				// break after the first simple campaign , value and type will be same for all simple campaigns
				break;
			}
			$message = Woocommerce::generate_product_banner(
				$value,
				$type,
				$format
			);
		} else {
			// if the simple campaign is set to display as regular price, return
			if ( wpab_campaignbay_get_value( $meta['simple'], 'display_as_regular_price', false ) === true ) {
				return;
			}
			wpab_campaignbay_log( '________ simple: ' . print_r( $meta['simple'], true ), 'INFO' );
			if ( wpab_campaignbay_get_value( $meta['simple'], 'show_product_message', true ) === false ) {
				return;
			}
			$format  = wpab_campaignbay_get_value( $meta['simple'], 'message_format' );
			$message = Woocommerce::generate_product_banner(
				wpab_campaignbay_get_value( $meta['simple'], 'value', 0 ),
				wpab_campaignbay_get_value( $meta['simple'], 'type', 'percentage' ),
				$format
			);
		}

		/**
		 * Filter hook to allow modification of the final discount message string.
		 *
		 * This is useful for developers who want to change the wording or add extra
		 * details to the promotional banner on the product page.
		 *
		 * @since 1.0.0
		 * @hook campaignbay_product_display_product_discount_message
		 *
		 * @param string     $message The generated message HTML.
		 * @param WC_Product $product The current product object.
		 * @param array      $meta    The pre-calculated discount metadata.
		 * @param string     $format  The message format string.
		 */
		$message = apply_filters( 'campaignbay_product_display_product_discount_message', $message, $product, $meta, $format );
		if ( $message !== '' ) {
			Woocommerce::print_notice(
				$message,
				'success'
			);
		}

		/**
		 * Action hook that fires after any discount message is rendered for a product.
		 *
		 * This allows other functions or third-party add-ons to add custom content
		 * after the main CampaignBay message is displayed.
		 *
		 * @since 1.0.0
		 * @hook campaignbay_after_product_display_product_discount_message
		 *
		 * @param WC_Product $product The current product object.
		 * @param array      $meta    The pre-calculated discount metadata for the product.
		 */
		do_action( 'campaignbay_after_product_display_product_discount_message', $product, $meta, $format );
	}


	/**
	 * Displays a table showing the price of a product at different quantity tiers.
	 *
	 * This function is intended to be used with a WooCommerce action hook like
	 * `woocommerce_single_product_summary`.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function display_product_quantity_table() {
		if ( ! Common::get_instance()->get_settings( 'show_discount_table' ) ) {
			return;
		}

		global $product;
		$meta = Woocommerce::get_product( $product->get_id() )->get_meta( 'campaignbay' );
		if ( Woocommerce::product_type_is( $product, 'variable' ) ) {
			foreach ( $meta['base_price'] as $key => $value ) {
				$price = $value;
				break;
			}
			if ( wpab_campaignbay_get_value( $meta, 'is_simple', false ) ) {
				foreach ( wpab_campaignbay_get_value( $meta, 'simple', array() ) as $key => $value ) {
					$price = $value['price'];
					break;
				}
			}
		} else {
			$price = $meta['base_price'];
			if ( $this->settings['cart_allowCampaignStacking'] ) {
				if ( isset( $meta['simple'] ) && $meta['is_simple'] === true && isset( $meta['simple']['price'] ) ) {
					$price = $meta['simple']['price'];
				}
			}
		}
		$tiers = Helper::get_quantity_tiers_with_campaign( $product );
		$tiers = Helper::get_unique_quantity_tiers( $tiers, $price );
		if ( empty( $tiers ) ) {
			return;
		}

		// escaping just before echo
		Helper::generate_quantity_table( $tiers );

		return;
	}

	/**
	 * After cart item quantity update.
	 *
	 * @since 1.0.0
	 * @param string  $cart_item_key The cart item key.
	 * @param int     $quantity The quantity.
	 * @param int     $old_quantity The old quantity.
	 * @param WC_Cart $cart The cart object.
	 * @return void
	 */
	public function after_cart_item_quantity_update( $cart_item_key, $quantity, $old_quantity, $cart ) {
		if ( ! did_action( 'woocommerce_before_calculate_totals' ) ) {

			// add to cart message for prduct page goes here
			// wpab_campaignbay_log(' after_cart_item_quantity_update');
		}
	}

	/**
	 * Before calculate totals.
	 *
	 * @since 1.0.0
	 * @param WC_Cart $cart The cart object.
	 * @return void
	 */
	public function before_calculate_totals( $cart ) {
		remove_action( 'woocommerce_before_calculate_totals', array( $this, 'before_calculate_totals' ), 20, 1 );
		wpab_campaignbay_log( 'Before calculate totals: ', 'INFO' );
		$this->coupons           = CartDiscount::calculate_cart_discount( $cart );
		$this->calculated_totals = true;
		$discount_breakdown      = $cart->campaignbay_discount_breakdown ?? array();
		if ( is_array( $discount_breakdown ) && ! empty( $discount_breakdown ) ) {
			$this->discount_applied = true;
		}

		add_action( 'woocommerce_before_calculate_totals', array( $this, 'before_calculate_totals' ), 20, 1 );
	}

	/**
	 * After calculate totals.
	 *
	 * @since 1.1.17
	 * @param WC_Cart $cart The cart object.
	 * @return void
	 */
	public function after_calculate_totals( $cart ) {
		$cart_level_condition_campaigns = $this->get_cart_level_condition_campaigns();
		if ( empty( $cart_level_condition_campaigns ) ) {
			wpab_campaignbay_log( 'No cart level condition campaigns found', 'info' );
			return;
		}
		remove_action( 'woocommerce_after_calculate_totals', array( $this, 'after_calculate_totals' ), 20, 1 );
		$this->calculated_cart = array(
			'subtotal' => $cart->get_subtotal(),
			'total'    => $cart->get_total( 'edit' ),
		);
		wpab_campaignbay_log( 'Cart level condition campaigns found for evaluation: ' . print_r( $this->cart_level_condition_campaigns, true ), 'INFO' );
		wpab_campaignbay_log( 'Calculating cart total for cart level conditions', 'INFO' );

		$this->calculating_cart_total_for_cart_level_conditions = true;
		$cart->calculate_totals();
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'after_calculate_totals' ), 20, 1 );
	}




	/**
	 * Validate fake coupon data.
	 *
	 * @since 1.0.0
	 * @param array  $data The data.
	 * @param string $coupon_code The coupon code.
	 * @return array The data.
	 */
	public function validate_fake_coupon_data( $data, $coupon_code ) {
		if ( $coupon_code !== false && $coupon_code !== 0 && isset( $this->coupons[ $coupon_code ] ) ) {

			$data = array(
				'id'                         => $coupon_code,
				'amount'                     => $this->coupons[ $coupon_code ]['discount'],
				'individual_use'             => false,
				'product_ids'                => $this->coupons[ $coupon_code ]['product_ids'] ?? array(),
				'exclude_product_ids'        => array(),
				'usage_limit'                => '',
				'usage_limit_per_user'       => '',
				'limit_usage_to_x_items'     => '',
				'usage_count'                => '',
				'date_created'               => gmdate( 'Y-m-d' ),
				'expiry_date'                => '',
				'apply_before_tax'           => 'yes',
				'free_shipping'              => false,
				'product_categories'         => array(),
				'exclude_product_categories' => array(),
				'exclude_sale_items'         => false,
				'minimum_amount'             => '',
				'maximum_amount'             => '',
				'customer_email'             => '',
				'discount_type'              => $this->coupons[ $coupon_code ]['type'],
			);
		} elseif ( $this->calculated_totals === false && is_string( $coupon_code ) && strlen( $coupon_code ) >= 11 && substr( $coupon_code, 0, 11 ) === 'campaignbay' ) {
			// before calculating total
			$data = array(
				'id'                         => $coupon_code,
				'amount'                     => 0,
				'individual_use'             => false,
				'product_ids'                => array(),
				'exclude_product_ids'        => array(),
				'usage_limit'                => '',
				'usage_limit_per_user'       => '',
				'limit_usage_to_x_items'     => '',
				'usage_count'                => '',
				'date_created'               => gmdate( 'Y-m-d' ),
				'expiry_date'                => '',
				'apply_before_tax'           => 'yes',
				'free_shipping'              => false,
				'product_categories'         => array(),
				'exclude_product_categories' => array(),
				'exclude_sale_items'         => false,
				'minimum_amount'             => '',
				'maximum_amount'             => '',
				'customer_email'             => '',
				'discount_type'              => 'fixed_cart',
			);
		}
		return $data;
	}

	/**
	 * Change virtual coupon label.
	 *
	 * @since 1.0.0
	 * @param string    $label The label.
	 * @param WC_Coupon $coupon_code The coupon code.
	 * @return string The label.
	 */
	public function change_virtual_coupon_label( $label, $coupon_code ) {
		$code = $coupon_code->get_code();
		if ( isset( $this->coupons[ $code ] ) ) {
			return $this->coupons[ $code ]['campaign_title'];
		}
		return $label;
	}

	/**
	 * Validate fake coupon.
	 *
	 * @since 1.0.0
	 * @param bool      $value The value.
	 * @param WC_Coupon $coupon The coupon.
	 * @param float     $discount The discount.
	 * @return bool The value.
	 */
	public function validate_fake_coupon( $value, $coupon, $discount ) {
		if ( isset( $this->coupons[ $coupon->get_code() ] ) ) {
			return true;
		}
		if ( ! $this->settings['cart_allowWcCouponStacking'] && $this->discount_applied ) {
			return false;
		}
		return $value;
	}

	/**
	 * Ensure cart calculate totals.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ensure_cart_calculate_totals() {
		if ( ! did_action( 'woocommerce_before_calculate_totals' ) ) {
			return;
		}

		if ( function_exists( 'WC' ) ) {
			if ( isset( WC()->cart ) && WC()->cart != null ) {
				if ( is_object( WC()->cart ) && method_exists( WC()->cart, 'calculate_totals' ) ) {
					WC()->cart->calculate_totals();
				}
			}
		}
	}

	/**
	 * Add cart level condition campaigns.
	 *
	 * @since 1.1.17
	 * @param \WpabCampaignBay\Model\Campaign $campaign The campaign object.
	 * @return void
	 */
	public function add_cart_level_condition_campaigns( $campaign ) {
		$this->cart_level_condition_campaigns[] = $campaign;
	}

	/**
	 * Get cart level condition campaigns.
	 *
	 * @since 1.1.17
	 * @return array The cart level condition campaigns.
	 */
	public function get_cart_level_condition_campaigns() {
		return $this->cart_level_condition_campaigns;
	}

	/**
	 * Check if calculating cart total for cart level conditions.
	 *
	 * @since 1.1.17
	 * @return bool True if calculating cart total for cart level conditions, false otherwise.
	 */
	public function is_calculating_cart_total_for_cart_level_conditions() {
		return $this->calculating_cart_total_for_cart_level_conditions;
	}

	/**
	 * Get calculated cart.
	 *
	 * @since 1.1.17
	 * @return \WC_Cart The cart object.
	 */
	public function get_calculated_cart() {
		return $this->calculated_cart;
	}
}
