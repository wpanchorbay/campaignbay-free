<?php
/**
 * File.
 *
 * @package campaignbay
 */

namespace WpabCampaignBay\Engine;

use WC_Product;
use WpabCampaignBay\Core\Base;
use WpabCampaignBay\Core\Common;

/**
 * The file that defines the QuantityLimits class.
 *
 * Enforces the optional min / max / step quantity limits configured inside a
 * campaign's settings (`settings.quantity_limits`) for every product the campaign
 * targets while the campaign is active.
 *
 * @link       https://wpanchorbay.com
 * @since      1.1.18
 *
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBay/Engine
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Quantity Limits class.
 *
 * Limits are applied in two layers: the WooCommerce quantity input arguments (UI hint)
 * and server-side validation on add to cart, cart update, cart/checkout validation and
 * the Store API (blocks). When several active campaigns limit the same product the
 * strictest combination wins: highest minimum, lowest maximum and the least common
 * multiple of the steps.
 *
 * @since      1.1.18
 * @package    WPAB_CampaignBay
 * @author     WP Anchor Bay <wpanchorbay@gmail.com>
 */
class QuantityLimits extends Base {

	/**
	 * Constructor to define and build the hooks array.
	 *
	 * @since 1.1.18
	 */
	protected function __construct() {
		parent::__construct();
		$this->define_hooks();
	}

	/**
	 * Defines all hooks this class needs to run.
	 *
	 * @since 1.1.18
	 * @access private
	 * @return void
	 */
	private function define_hooks() {
		$hooks = array(
			array( 'filter', 'woocommerce_quantity_input_args', 'filter_quantity_input_args', 20, 2 ),
			array( 'filter', 'woocommerce_available_variation', 'filter_available_variation', 20, 3 ),
			array( 'filter', 'woocommerce_add_to_cart_validation', 'validate_add_to_cart', 20, 4 ),
			array( 'filter', 'woocommerce_update_cart_validation', 'validate_cart_update', 20, 4 ),
			array( 'action', 'woocommerce_check_cart_items', 'validate_cart_items', 20, 0 ),
			array( 'action', 'woocommerce_before_add_to_cart_button', 'display_product_message', 10, 0 ),
			array( 'filter', 'woocommerce_store_api_product_quantity_minimum', 'filter_store_api_minimum', 20, 3 ),
			array( 'filter', 'woocommerce_store_api_product_quantity_maximum', 'filter_store_api_maximum', 20, 3 ),
			array( 'filter', 'woocommerce_store_api_product_quantity_multiple_of', 'filter_store_api_multiple_of', 20, 3 ),
		);
		foreach ( $hooks as $hook ) {
			$this->add_hook( ...$hook );
		}
	}

	/**
	 * Gets the effective quantity limits for a product.
	 *
	 * @since 1.1.18
	 * @access public
	 *
	 * @param WC_Product|int $product The product (or variation) or its ID.
	 * @return array|null Array with `min` (int), `max` (int|null) and `step` (int), or null when no limit applies.
	 */
	public function get_limits( $product ) {
		$product_id = $product instanceof WC_Product ? $product->get_id() : (int) $product;
		// Always evaluate the targeting against the untouched product (not the discounted copy).
		$product = $product_id ? wc_get_product( $product_id ) : null;
		if ( ! $product instanceof WC_Product ) {
			return null;
		}

		$min   = 1;
		$max   = null;
		$step  = 1;
		$found = false;
		foreach ( CampaignManager::get_instance()->get_active_campaigns() as $campaign ) {
			$settings = $campaign->get_settings();
			$limits   = $settings['quantity_limits'] ?? null;
			if ( ! is_array( $limits ) || empty( $limits['enabled'] ) ) {
				continue;
			}
			if ( ! $campaign->is_applicable_to_product( $product ) ) {
				continue;
			}
			$found = true;
			if ( ! empty( $limits['min'] ) ) {
				$min = max( $min, (int) $limits['min'] );
			}
			if ( ! empty( $limits['max'] ) ) {
				$max = null === $max ? (int) $limits['max'] : min( $max, (int) $limits['max'] );
			}
			if ( ! empty( $limits['step'] ) ) {
				$step = $this->lcm( $step, (int) $limits['step'] );
			}
		}
		if ( ! $found ) {
			return null;
		}

		// Align to the step so that the HTML input (min + n * step) matches "multiple of step".
		$min = (int) ( ceil( $min / $step ) * $step );
		if ( null !== $max ) {
			$max = (int) ( floor( $max / $step ) * $step );
			if ( $max < $min ) {
				// Conflicting campaigns leave no valid quantity; ignore the maximum rather than block the product.
				$max = null;
			}
		}

		$limits = array(
			'min'  => $min,
			'max'  => $max,
			'step' => $step,
		);

		/**
		 * Filters the quantity limits that apply to a product.
		 *
		 * @since 1.1.18
		 * @hook campaignbay_product_quantity_limits
		 *
		 * @param array      $limits  Array with `min` (int), `max` (int|null) and `step` (int).
		 * @param WC_Product $product The product being checked.
		 */
		return apply_filters( 'campaignbay_product_quantity_limits', $limits, $product );
	}

	/**
	 * Returns the error message if a quantity breaks the limits, or an empty string.
	 *
	 * @since 1.1.18
	 * @access private
	 *
	 * @param array      $limits   The limits from get_limits().
	 * @param int        $quantity The total quantity to check.
	 * @param WC_Product $product  The product (used for the message).
	 * @return string The error message, empty when valid.
	 */
	private function get_violation( $limits, $quantity, $product ) {
		$key = '';
		if ( $quantity < $limits['min'] ) {
			$key = 'quantity_limit_message_min';
		} elseif ( null !== $limits['max'] && $quantity > $limits['max'] ) {
			$key = 'quantity_limit_message_max';
		} elseif ( $limits['step'] > 1 && 0 !== $quantity % $limits['step'] ) {
			$key = 'quantity_limit_message_step';
		}
		if ( '' === $key ) {
			return '';
		}
		$format = Common::get_instance()->get_settings( $key );
		if ( ! is_string( $format ) || '' === trim( $format ) ) {
			$defaults = Common::get_instance()->get_default_settings();
			$format   = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
		}
		return $this->format_message( $format, $limits, $product );
	}

	/**
	 * Replaces the placeholders of an admin-editable message format.
	 *
	 * Supported: {product_name}, {min_quantity}, {max_quantity}, {step}, {limits}.
	 *
	 * @since 1.1.18
	 * @access private
	 *
	 * @param string     $format  The message format.
	 * @param array      $limits  The limits from get_limits().
	 * @param WC_Product $product The product.
	 * @return string The message.
	 */
	private function format_message( $format, $limits, $product ) {
		$parts = array();
		if ( $limits['min'] > 1 ) {
			/* translators: %d: minimum quantity */
			$parts[] = sprintf( __( 'minimum %d', 'campaignbay' ), $limits['min'] );
		}
		if ( null !== $limits['max'] ) {
			/* translators: %d: maximum quantity */
			$parts[] = sprintf( __( 'maximum %d', 'campaignbay' ), $limits['max'] );
		}
		if ( $limits['step'] > 1 ) {
			/* translators: %d: quantity step */
			$parts[] = sprintf( __( 'multiples of %d', 'campaignbay' ), $limits['step'] );
		}
		return strtr(
			(string) $format,
			array(
				'{product_name}' => $product->get_name(),
				'{min_quantity}' => (string) $limits['min'],
				'{max_quantity}' => null === $limits['max'] ? '' : (string) $limits['max'],
				'{step}'         => (string) $limits['step'],
				'{limits}'       => implode( ', ', $parts ),
			)
		);
	}

	/**
	 * Prints the quantity limits note on the product page, above the add to cart button.
	 *
	 * @since 1.1.18
	 * @return void
	 */
	public function display_product_message() {
		global $product;
		if ( ! $product instanceof WC_Product || ! Common::get_instance()->get_settings( 'quantity_limit_show_product_message' ) ) {
			return;
		}
		$limits = $this->get_limits( $product );
		if ( null === $limits || ( $limits['min'] <= 1 && null === $limits['max'] && $limits['step'] <= 1 ) ) {
			return;
		}
		$message = $this->format_message( Common::get_instance()->get_settings( 'quantity_limit_product_message_format' ), $limits, $product );
		if ( '' === trim( $message ) ) {
			return;
		}
		echo '<p class="campaignbay-quantity-limits">' . esc_html( $message ) . '</p>';
	}

	/**
	 * Sets min / max / step on the WooCommerce quantity input.
	 *
	 * @since 1.1.18
	 * @param array      $args    The quantity input arguments.
	 * @param WC_Product $product The product.
	 * @return array The filtered arguments.
	 */
	public function filter_quantity_input_args( $args, $product ) {
		$limits = $this->get_limits( $product );
		if ( null === $limits ) {
			return $args;
		}
		if ( $limits['min'] > 1 ) {
			$args['min_value'] = $limits['min'];
		}
		$args['step'] = $limits['step'];
		if ( null !== $limits['max'] ) {
			$args['max_value'] = $limits['max'];
		}
		if ( isset( $args['input_value'] ) ) {
			$value = (int) $args['input_value'];
			$value = max( $value, $limits['min'] );
			if ( null !== $limits['max'] ) {
				$value = min( $value, $limits['max'] );
			}
			$args['input_value'] = $value;
		}
		return $args;
	}

	/**
	 * Sets min / max quantity on the data WooCommerce sends for a variation.
	 *
	 * @since 1.1.18
	 * @param array                 $data      The variation data.
	 * @param WC_Product            $product   The variable product.
	 * @param \WC_Product_Variation $variation The variation.
	 * @return array The filtered data.
	 */
	public function filter_available_variation( $data, $product, $variation ) {
		$limits = $this->get_limits( $variation );
		if ( null === $limits ) {
			return $data;
		}
		$data['min_qty'] = $limits['min'];
		if ( null !== $limits['max'] ) {
			$data['max_qty'] = $limits['max'];
		}
		return $data;
	}

	/**
	 * Validates the quantity being added to the cart (including what is already in it).
	 *
	 * @since 1.1.18
	 * @param bool $passed       Whether validation has passed so far.
	 * @param int  $product_id   The product ID.
	 * @param int  $quantity     The quantity being added.
	 * @param int  $variation_id The variation ID, if any.
	 * @return bool Whether validation passes.
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0 ) {
		if ( ! $passed ) {
			return $passed;
		}
		$target_id = $variation_id ? (int) $variation_id : (int) $product_id;
		$limits    = $this->get_limits( $target_id );
		if ( null === $limits ) {
			return $passed;
		}
		$product = wc_get_product( $target_id );
		$total   = (int) $quantity + $this->get_cart_quantity( (int) $product_id, (int) $variation_id );
		$message = $this->get_violation( $limits, $total, $product );
		if ( '' !== $message ) {
			wc_add_notice( $message, 'error' );
			return false;
		}
		return $passed;
	}

	/**
	 * Validates a quantity change on the cart page.
	 *
	 * @since 1.1.18
	 * @param bool   $passed        Whether validation has passed so far.
	 * @param string $cart_item_key The cart item key.
	 * @param array  $values        The cart item.
	 * @param int    $quantity      The new quantity.
	 * @return bool Whether validation passes.
	 */
	public function validate_cart_update( $passed, $cart_item_key, $values, $quantity ) {
		if ( ! $passed || ! empty( $values['is_campaignbay_free_product'] ) || (int) $quantity <= 0 ) {
			return $passed;
		}
		$target_id = ! empty( $values['variation_id'] ) ? (int) $values['variation_id'] : (int) $values['product_id'];
		$limits    = $this->get_limits( $target_id );
		if ( null === $limits ) {
			return $passed;
		}
		$message = $this->get_violation( $limits, (int) $quantity, wc_get_product( $target_id ) );
		if ( '' !== $message ) {
			wc_add_notice( $message, 'error' );
			return false;
		}
		return $passed;
	}

	/**
	 * Validates every cart line (cart and checkout), e.g. for lines added before a campaign started.
	 *
	 * @since 1.1.18
	 * @return void
	 */
	public function validate_cart_items() {
		if ( ! function_exists( 'WC' ) || ! isset( WC()->cart ) || null === WC()->cart ) {
			return;
		}
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! empty( $cart_item['is_campaignbay_free_product'] ) ) {
				continue;
			}
			$target_id = ! empty( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : (int) $cart_item['product_id'];
			$limits    = $this->get_limits( $target_id );
			if ( null === $limits ) {
				continue;
			}
			$message = $this->get_violation( $limits, (int) $cart_item['quantity'], wc_get_product( $target_id ) );
			if ( '' !== $message ) {
				wc_add_notice( $message, 'error' );
			}
		}
	}

	/**
	 * Store API (block cart / checkout) minimum quantity.
	 *
	 * @since 1.1.18
	 * @param int        $value     The current minimum.
	 * @param WC_Product $product   The product.
	 * @param array|null $cart_item The cart item, if any.
	 * @return int The filtered minimum.
	 */
	public function filter_store_api_minimum( $value, $product, $cart_item = null ) {
		if ( ! empty( $cart_item['is_campaignbay_free_product'] ) ) {
			return $value;
		}
		$limits = $this->get_limits( $product );
		return null === $limits ? $value : max( (int) $value, $limits['min'] );
	}

	/**
	 * Store API (block cart / checkout) maximum quantity.
	 *
	 * @since 1.1.18
	 * @param int        $value     The current maximum.
	 * @param WC_Product $product   The product.
	 * @param array|null $cart_item The cart item, if any.
	 * @return int The filtered maximum.
	 */
	public function filter_store_api_maximum( $value, $product, $cart_item = null ) {
		if ( ! empty( $cart_item['is_campaignbay_free_product'] ) ) {
			return $value;
		}
		$limits = $this->get_limits( $product );
		if ( null === $limits || null === $limits['max'] ) {
			return $value;
		}
		return $value ? min( (int) $value, $limits['max'] ) : $limits['max'];
	}

	/**
	 * Store API (block cart / checkout) quantity step.
	 *
	 * @since 1.1.18
	 * @param int        $value     The current step.
	 * @param WC_Product $product   The product.
	 * @param array|null $cart_item The cart item, if any.
	 * @return int The filtered step.
	 */
	public function filter_store_api_multiple_of( $value, $product, $cart_item = null ) {
		if ( ! empty( $cart_item['is_campaignbay_free_product'] ) ) {
			return $value;
		}
		$limits = $this->get_limits( $product );
		if ( null === $limits || $limits['step'] <= 1 ) {
			return $value;
		}
		return $this->lcm( max( 1, (int) $value ), $limits['step'] );
	}

	/**
	 * Gets the quantity of a product/variation already in the cart (free BOGO items excluded).
	 *
	 * @since 1.1.18
	 * @access private
	 * @param int $product_id   The product ID.
	 * @param int $variation_id The variation ID, or 0.
	 * @return int The quantity in the cart.
	 */
	private function get_cart_quantity( $product_id, $variation_id ) {
		$quantity = 0;
		if ( ! function_exists( 'WC' ) || ! isset( WC()->cart ) || null === WC()->cart ) {
			return $quantity;
		}
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! empty( $cart_item['is_campaignbay_free_product'] ) ) {
				continue;
			}
			if ( (int) $cart_item['product_id'] === $product_id && (int) ( $cart_item['variation_id'] ?? 0 ) === $variation_id ) {
				$quantity += (int) $cart_item['quantity'];
			}
		}
		return $quantity;
	}

	/**
	 * Least common multiple of two positive integers.
	 *
	 * @since 1.1.18
	 * @access private
	 * @param int $a First number.
	 * @param int $b Second number.
	 * @return int The least common multiple.
	 */
	private function lcm( $a, $b ) {
		$a = max( 1, $a );
		$b = max( 1, $b );
		$x = $a;
		$y = $b;
		while ( 0 !== $y ) {
			$t = $y;
			$y = $x % $y;
			$x = $t;
		}
		return (int) ( ( $a / $x ) * $b );
	}
}
