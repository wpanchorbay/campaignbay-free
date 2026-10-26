<?php
/**
 * File.
 *
 * @package campaignbay
 */

namespace WpabCampaignBay\Engine;

use WC_Product;
use WP_Error;
use WpabCampaignBay\Core\Common;
use WpabCampaignBay\Helper\Helper;
use WpabCampaignBay\Helper\Woocommerce;

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
class CartDiscount {


	/**
	 * checking cart_allow_campaign_stacking
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function cart_allow_campaign_stacking() {
		$settings = Common::get_instance()->get_settings();
		if ( ! isset( $settings['cart_allowCampaignStacking'] ) || null === $settings['cart_allowCampaignStacking'] ) {
			return true;
		}
		return $settings['cart_allowCampaignStacking'];
	}

	/**
	 * Public entry point to begin the cart discount calculation process.
	 *
	 * @since 1.0.0
	 * @param \WC_Cart $cart The main WooCommerce cart object.
	 * @return array An array of virtual coupon codes that were applied.
	 */
	public static function calculate_cart_discount( $cart ) {
		return self::calculate_discounts( $cart );
	}

	/**
	 * The main worker method that calculates and applies all discounts to the cart.
	 *
	 * Iterates through each item in the cart, determines the applicable simple, quantity,
	 * and BOGO discounts, and then applies them as either a direct price change,
	 * a virtual coupon, or a negative fee.
	 *
	 * @since 1.0.0
	 * @param \WC_Cart|null $cart The main WooCommerce cart object.
	 * @return array An array of virtual coupon codes that were applied.
	 */
	public static function calculate_discounts( $cart = null ) {
		/**
		 * Fires before any of CampaignBay's cart discount calculations begin.
		 *
		 * This is the primary entry point for a Pro version or third-party add-on to
		 * run its own complete set of cart discount rules. An add-on can use this hook
		 * to calculate its own discounts and add them to a custom property on the $cart object
		 * before the Free version's logic starts.
		 *
		 * @since 1.0.0
		 * @hook campaignbay_before_cart_discount_calculation
		 *
		 * @param \WC_Cart $cart The main WooCommerce cart object.
		 */
		do_action( 'campaignbay_before_cart_discount_calculation', $cart );
		$cart->campaignbay  = array(
			'coupon' => array(),
			'fee'    => array(),
		);
		$discount_breakdown = array();
		$free_products      = array();

		/**
		 * Fires before the cart discount calculations begin.
		 *
		 * This is the primary entry point for a Pro version or third-party add-on to
		 * run its own complete set of cart discount rules. An add-on can use this hook
		 * to calculate its own discounts and add them to a custom property on the $cart object
		 * before the Free version's logic starts.
		 *
		 * @since 1.0.8
		 * @hook campaignbay_before_cart_discount_calculation_loop
		 *
		 * @param \WC_Cart $cart The main WooCommerce cart object.
		 * @param array    $discount_breakdown The discount breakdown array.
		 * @param array    $free_products The free products array.
		 */
		do_action( 'campaignbay_before_cart_discount_calculation_loop', $cart, $discount_breakdown, $free_products );

		if ( isset( $cart->cart_contents ) && ! empty( $cart->cart_contents ) ) {
			foreach ( $cart->cart_contents as $key => $cart_item ) {
				wpab_campaignbay_log( 'Processing cart item: ' . $cart_item['data']->get_name() . ' (Key: ' . $key . ')', 'INFO' );
				// skip it if it is a free product with bogo or any other type of discount
				if ( isset( $cart_item['is_campaignbay_free_product'] ) && true === $cart_item['is_campaignbay_free_product'] ) {
					continue;
				}
				// meta contains simple and cart level discount data
				$meta = self::get_cart_discount( $cart_item );
				// flag to check if simple discount is applied
				$simple_applied = false;
				// cart quantity -> quantity of the product in the cart
				$cart_quantity = $cart_item['quantity'];

				/**
				 * Fires just before the discount logic is processed for a single cart item.
				 *
				 * This allows an add-on to perform specific actions or modify the item's
				 * metadata (`$meta`) before the standard Quantity or BOGO rules are checked.
				 * For example, a Pro version could use this to apply a "Free Gift" flag
				 * to this specific cart item.
				 *
				 * @since 1.0.0
				 * @hook campaignbay_before_cart_single_discount_calculation
				 *
				 * @param array    $cart_item        The cart item being processed.
				 * @param int      $cart_quantity    The quantity of the item in the cart.
				 * @param array    $meta             The pre-calculated CampaignBay metadata for this item.
				 * @param \WC_Cart $cart             The main WooCommerce cart object.
				 * @param string   $key              The unique key for the cart item.
				 */
				do_action( 'campaignbay_before_cart_single_discount_calculation', $cart_item, $cart_quantity, $meta, $simple_applied, $discount_breakdown, $cart, $key );

				if ( null === $meta ) {
					continue;
				}
				// bogo discount
				if ( isset( $meta['bogo'] ) && true === $meta['is_bogo'] ) {
					$bogo_data                = $meta['bogo'];
					$bogo_data['parent_id']   = $cart_item['data']->get_id();
					$bogo_data['parent_name'] = $cart_item['data']->get_name();
					// add bogo free product to the cart
					$added_product_id = self::add_bogo_free_product( $cart, $key, $bogo_data, $discount_breakdown );
					if ( $added_product_id ) {
						// store the added product id for later verification
						$free_products[ $key ]['campaignbay_free_products'][ $added_product_id ] = true;

					}
				}
				// save the meta data to the cart item
				$cart->cart_contents[ $key ]['campaignbay'] = $meta;
				if ( isset( $meta['simple'] ) && isset( $meta['simple']['price'] ) ) {
					$simple_applied = true;
					if ( $meta['simple']['display_as_regular_price'] ) {
						$cart->cart_contents[ $key ]['data']->set_regular_price( $meta['simple']['price'] );
					} else {
						$cart->cart_contents[ $key ]['data']->set_regular_price( $meta['base_price'] );
					}
					$cart->cart_contents[ $key ]['data']->set_price( $meta['simple']['price'] );
				}

				if ( self::quantity_tier_applies( $meta ) ) {
					// campaign stacking not allowed
					if ( ! self::cart_allow_campaign_stacking() ) {
						// seting orginal price as base price
						$cart->cart_contents[ $key ]['data']->set_regular_price( $meta['quantity']['base_price'] );
						$simple_applied = false;
					}
					$cart->cart_contents[ $key ]['data']->set_price( $meta['quantity']['base_price'] );
					$apply_as = $meta['quantity']['settings']['apply_as'] ?? 'line_total';
					if ( 'line_total' === $apply_as ) {
						$cart->cart_contents[ $key ]['data']->set_price( $meta['quantity']['price'] );
					} elseif ( 'coupon' === $apply_as ) {
						$data_to_add             = array(
							'campaign'       => $meta['quantity']['campaign'],
							'old_price'      => $cart_quantity * $meta['quantity']['base_price'],
							'product_id'     => $cart_item['data']->get_id(),
							'type'           => 'percentage' === $meta['quantity']['type'] ? 'percent' : 'fixed_product',
							'campaign_title' => $meta['quantity']['campaign_title'],
						);
						$data_to_add['discount'] = $meta['quantity']['value'];
						if ( 'percentage' !== $meta['quantity']['type'] ) {
							// A fixed_product coupon amount is per unit; WooCommerce multiplies it by the quantity itself.
							$data_to_add['discount'] = $meta['quantity']['discount'];
						}

						Helper::add_data(
							$cart,
							$data_to_add
						);
					} else {
						$fee_discount = $meta['quantity']['discount'] * $cart_quantity;
						// WooCommerce treats a negative fee as tax-exclusive and credits tax on top of it. When
						// prices are entered tax-inclusive, strip the tax so the customer gets exactly the
						// discount shown (as the coupon and line-total modes already do).
						if ( wc_tax_enabled() && wc_prices_include_tax() && $cart_item['data']->is_taxable() ) {
							$fee_discount = (float) wc_get_price_excluding_tax(
								$cart_item['data'],
								array(
									'qty'   => 1,
									'price' => $fee_discount,
								)
							);
						}
						self::add_fee(
							$cart,
							array(
								'campaign'       => $meta['quantity']['campaign'],
								'campaign_title' => $meta['quantity']['campaign_title'],
								'discount'       => $fee_discount,
							)
						);
					}

					if ( 'coupon' !== $apply_as ) {
						$campaign_id = $meta['quantity']['campaign'];
						if ( ! isset( $discount_breakdown[ $campaign_id ] ) ) {
							$discount_breakdown[ $campaign_id ] = array(
								'campaign_title' => $meta['quantity']['campaign_title'],
								'old_price'      => 0,
								'discount'       => 0,
							);
						}
						$discount_breakdown[ $campaign_id ]['old_price'] = $discount_breakdown[ $campaign_id ]['old_price'] + ( $cart_quantity * $meta['quantity']['base_price'] );
						$discount_breakdown[ $campaign_id ]['discount']  = $discount_breakdown[ $campaign_id ]['discount'] + ( $cart_quantity * $meta['quantity']['discount'] );
					}
				}

				if ( $simple_applied && isset( $meta['simple'] ) ) {

					$campaign_id = $meta['simple']['campaign'];
					if ( ! isset( $discount_breakdown[ $campaign_id ] ) ) {
						$discount_breakdown[ $campaign_id ] = array(
							'campaign_title' => $meta['simple']['campaign_title'],
							'old_price'      => 0,
							'discount'       => 0,
						);
					}
					$discount_breakdown[ $campaign_id ]['old_price'] = $discount_breakdown[ $campaign_id ]['old_price'] + ( $cart_quantity * $meta['base_price'] );
					$discount_breakdown[ $campaign_id ]['discount']  = $discount_breakdown[ $campaign_id ]['discount'] + ( $cart_quantity * $meta['simple']['discount'] );
				}

				/**
				 * Fires just after the discount logic is processed for a single cart item.
				 *
				 * This allows an add-on to perform specific actions or modify the item's
				 * metadata (`$meta`) after the standard Quantity or BOGO rules are checked.
				 * For example, a Pro version could use this to apply a "Free Gift" flag
				 * to this specific cart item.
				 *
				 * @since 1.0.0
				 * @hook campaignbay_after_cart_single_discount_calculation
				 *
				 * @param array    $cart_item        The cart item being processed.
				 * @param int      $cart_quantity    The quantity of the item in the cart.
				 * @param array    $meta             The pre-calculated CampaignBay metadata for this item.
				 * @param \WC_Cart $cart             The main WooCommerce cart object.
				 * @param string   $key              The unique key for the cart item.
				 */
				do_action( 'campaignbay_after_cart_single_discount_calculation', $cart_item, $cart_quantity, $meta, $simple_applied, $discount_breakdown, $cart, $key );

			}
			// loop for free prodcuts verifications
			foreach ( $cart->cart_contents as $key => $cart_item ) {
				// if not free product then continue
				if ( ! isset( $cart_item['is_campaignbay_free_product'] ) || false === $cart_item['is_campaignbay_free_product'] ) {
					continue;
				}
				$parent = wpab_campaignbay_get_value( $cart_item, 'campaignbay_parent' );
				if ( ! $parent || false === $parent ) {
					self::remove_from_cart( $cart, $key );
					continue;
				}
				$parent_cart_id = wpab_campaignbay_get_value( $parent, 'cart_id' );
				if ( true !== wpab_campaignbay_get_value( $free_products, $parent_cart_id . '.campaignbay_free_products.' . $key ) ) {
					self::remove_from_cart( $cart, $key );
					continue;
				}
			}
		}

		/**
		 * Fires before the discount coupons and fees are applied to the cart.
		 *
		 * This hook is useful for add-ons that need to perform a final action based on
		 * the fully discounted cart, such as logging the discount details or sending
		 * an email notification.
		 *
		 * @since 1.0.0
		 * @hook campaignbay_before_cart_coupon_and_fee_application
		 *
		 * @param \WC_Cart $cart               The main WooCommerce cart object.
		 * @param array    $discount_breakdown The discount breakdown array for the entire cart.
		 */
		do_action( 'campaignbay_before_cart_coupon_and_fee_application', $cart, $discount_breakdown );

		/**
		 * Filters the discount breakdown array for a single cart item after it
		 * has been calculated.
		 *
		 * This allows an add-on to modify the discount details for a specific item
		 * before the final cart-wide breakdown is assembled. For example, a Pro
		 * version could add a "Free Gift" entry to the breakdown for this item.
		 *
		 * @since 1.0.0
		 * @hook campaignbay_discount_breakdown
		 *
		 * @param array    $discount_breakdown The current discount breakdown for the entire cart.
		 * @param array    $cart_item          The cart item being processed.
		 * @param array    $meta               The CampaignBay metadata for this item.
		 * @param \WC_Cart $cart               The main WooCommerce cart object.
		 * @param string   $key                The unique key for the cart item.
		 *
		 * @return array The modified discount breakdown.
		 */
		$cart->campaignbay_discount_breakdown = apply_filters( 'campaignbay_discount_breakdown', $discount_breakdown ?? array(), $cart );

		foreach ( $cart->campaignbay['coupon'] as $key => $coupon ) {
			self::apply_fake_coupons( $key, $cart );
		}
		// apply the fee
		foreach ( $cart->campaignbay['fee'] as $key => $fee ) {
			$cart->add_fee( $fee['campaign_title'], $fee['discount'] * -1 );
		}
		/**
		 * Fires after all CampaignBay discount calculations are complete and have been
		 * applied to the cart as either coupons or fees.
		 *
		 * This is the final hook in the calculation sequence. It's useful for add-ons
		 * that need to perform a final action based on the fully discounted cart, such
		 * as updating a session variable or triggering a third-party analytics event.
		 *
		 * @since 1.0.0
		 * @hook campaignbay_after_cart_discount_calculation
		 *
		 * @param \WC_Cart $cart The fully processed WooCommerce cart object.
		 */
		do_action( 'campaignbay_after_cart_discount_calculation', $cart );

		return $cart->campaignbay['coupon'];
	}

	/**
	 * Whether the quantity tier in the cart item metadata is the discount that gets charged.
	 *
	 * With campaign stacking on, or no simple discount, the tier always applies. With stacking off, the tier only
	 * applies when it is cheaper than the simple discount. The cart totals and the cart line display must agree on this.
	 *
	 * @since 1.2.0
	 * @param array $meta The CampaignBay metadata of a cart item.
	 * @return bool True when the quantity tier price is the one charged.
	 */
	public static function quantity_tier_applies( $meta ) {
		if ( ! is_array( $meta ) || ! isset( $meta['quantity'] ) ) {
			return false;
		}
		return self::cart_allow_campaign_stacking() ||
			! isset( $meta['simple']['price'] ) ||
			$meta['quantity']['price'] < $meta['simple']['price'];
	}

	/**
	 * Gathers all CampaignBay discount metadata for a single cart item.
	 *
	 * This function is a dispatcher that checks for all applicable campaign types
	 * (simple, quantity, BOGO, etc.) for a given product and compiles the results
	 * into a single metadata array.
	 *
	 * @since 1.0.0
	 * @param array $cart_item The cart item to be analyzed.
	 * @return array The compiled CampaignBay metadata for the item.
	 */
	public static function get_cart_discount( $cart_item ) {
		/**
		 * Fires after the free version has calculated all its discount metadata for a single cart item.
		 *
		 * This action hook allows a Pro version or other extensions to inspect the discount data
		 * calculated by the free version for a specific item in the cart. It can be used to
		 * log data or to trigger other actions based on the discounts found.
		 *
		 * @since 1.1.0
		 * @hook campaignbay_before_cart_discount_data
		 *
		 * @param array $cart_item The complete cart item array from WooCommerce.
		 * @param array $meta      The discount metadata array calculated by CampaignBay, including
		 *                         'simple', 'quantity', 'bogo', etc.
		 */
		do_action( 'campaignbay_before_cart_discount_data', $cart_item );
		$product             = $cart_item['data'];
		$tiers               = Helper::get_quantity_tiers_with_campaign( $product );
		$meta                = Woocommerce::get_product_from_data( $product )->get_meta( 'campaignbay' );
		$meta['is_quantity'] = false;
		$base_price          = $meta['base_price'];
		$quantity            = $cart_item['quantity'];
		if ( self::cart_allow_campaign_stacking() ) {
			if ( isset( $meta['simple'] ) && true === $meta['is_simple'] && isset( $meta['simple']['price'] ) ) {
				$base_price = $meta['simple']['price'];
			}
		}

		$tiers        = Helper::get_unique_quantity_tiers( $tiers, $base_price );
		$current_tier = null;
		$next_tier    = null;
		foreach ( $tiers as $key => $tier ) {
			// Per-line fixed tiers price depends on the actual cart quantity.
			// Note: the per-unit price derived from a once-per-line fixed value is rounded, so the line total can
			// drift by a cent or two at odd quantities. This is accepted.
			if ( Helper::is_per_line_tier( $tier ) ) {
				$tier['price'] = (float) Helper::get_quantity_price( $base_price, $tier, max( $quantity, $tier['min'] ) );
			}
			if ( ( null === $current_tier || $current_tier['price'] > $tier['price'] ) && $tier['min'] <= $quantity && ( null === $tier['max'] || $tier['max'] >= $quantity ) ) {
				$current_tier = $tier;
				$next_tier    = null;
			} elseif ( ( null === $current_tier || ( $current_tier['min'] < $tier['min'] && $current_tier['price'] > $tier['price'] ) ) && null === $next_tier && $tier['min'] > $quantity ) {
				$next_tier = $tier;
			}
		}

		if ( null !== $current_tier ) {
			$meta['on_discount'] = true;
			$meta['is_quantity'] = true;
			$meta['quantity']    = array(
				'campaign'       => $current_tier['campaign'],
				'campaign_title' => $current_tier['campaign_title'],
				'settings'       => $current_tier['settings'],
				'price'          => $current_tier['price'],
				'discount'       => round( (float) $base_price - $current_tier['price'], 8 ),
				'base_price'     => $base_price,
				'type'           => $current_tier['type'],
				'value'          => $current_tier['value'],
			);
		}
		if ( null !== $next_tier ) {
			$meta['quantity_next_tier'] = array(
				'settings'  => $next_tier['settings']['cart_quantity_message_format'] ?? '',
				'remaining' => $next_tier['min'] - $quantity,
				'type'      => $next_tier['type'],
				'value'     => $next_tier['value'],
			);
		}

		/**===================================
		 *=========== bogo discount ==========
		 *==================================*/
		$bogo_meta = Helper::get_bogo_meta( $product, $quantity );
		if ( null !== $bogo_meta || ! empty( $bogo_meta ) ) {
			if ( $bogo_meta['is_bogo'] ) {
				$meta['on_discount'] = true;
			}
			$meta['is_bogo'] = $bogo_meta['is_bogo'];
			if ( isset( $bogo_meta['bogo'] ) ) {
				$meta['bogo'] = $bogo_meta['bogo'];
			}
		}

		/**
		 * Fires after plugin has calculated all its discount metadata for a single cart item.
		 *
		 * This action hook allows a Pro version or other extensions to inspect the discount data
		 * calculated by the free version for a specific item in the cart. It can be used to
		 * log data or to trigger other actions based on the discounts found.
		 *
		 * @since 1.1.0
		 * @hook campaignbay_after_cart_discount_data
		 *
		 * @param array $cart_item The complete cart item array from WooCommerce.
		 * @param array $meta      The discount metadata array calculated by CampaignBay, including
		 *                         'simple', 'quantity', 'bogo', etc.
		 */
		do_action( 'campaignbay_after_cart_discount_data', $cart_item, $meta );

		/**
		 * Filters the final discount metadata array for a single cart item before it is returned.
		 *
		 * This is the primary hook for a Pro version to add its own discount data or
		 * modify the data calculated by the free version. For example, a Pro feature
		 * like "Free Gift" could add its own data to the `$meta` array here, which
		 * would then be processed by the main cart calculation engine.
		 *
		 * @since 1.1.0
		 * @hook campaignbay_get_cart_discount
		 *
		 * @param array $meta      The discount metadata array calculated by CampaignBay.
		 * @param array $cart_item The complete cart item array from WooCommerce.
		 *
		 * @return array The potentially modified metadata array.
		 */
		return apply_filters( 'campaignbay_get_cart_discount', $meta, $cart_item );
	}

	/**
	 * Prepares and adds discount data to the cart object to be applied as a virtual coupon.
	 *
	 * This helper function organizes discount data into a structured format within the
	 * `$cart->campaignbay['coupon']` array, grouping discounts by type and campaign.
	 *
	 * @deprecated 1.0.8 This function has been moved to WpabCampaignBay\Helper\Helper.
	 *
	 * @since 1.0.0
	 * @param \WC_Cart $cart The main WooCommerce cart object.
	 * @param array    $data The discount data to add.
	 */


	/**
	 * Programmatically applies a coupon code to the cart.
	 *
	 * This function adds a coupon code to the cart's list of applied coupons,
	 * triggering WooCommerce's discount calculation for that coupon.
	 *
	 * @since 1.0.0
	 * @param string   $coupon_code The coupon code to apply.
	 * @param \WC_Cart $cart        The main WooCommerce cart object.
	 * @return bool|void False if the coupon cannot be applied.
	 */
	public static function apply_fake_coupons( $coupon_code, $cart ) {
		if ( is_object( $cart ) && method_exists( $cart, 'has_discount' ) ) {
			if ( ! $cart->has_discount( $coupon_code ) ) {
				if ( $cart->applied_coupons ) {
					foreach ( $cart->applied_coupons as $code ) {
						$coupon = new \WC_Coupon( $code );
						if ( true === $coupon->get_individual_use() ) {
							return false;
						}
					}
				}
				$cart->applied_coupons[] = $coupon_code;
				return true;
			}
		}
	}

	/**
	 * Prepares and adds discount data to the cart object to be applied as a negative fee.
	 *
	 * This helper function aggregates discount amounts into the `$cart->campaignbay['fee']` array.
	 *
	 * @since 1.0.0
	 * @param \WC_Cart $cart The main WooCommerce cart object.
	 * @param array    $data The discount data to add.
	 */
	public static function add_fee( $cart, $data ) {
		$code = 'campaignbay_' . $data['campaign'];
		if ( ! isset( $cart->campaignbay['fee'][ $code ] ) ) {
			$cart->campaignbay['fee'][ $code ] = array(
				'campaign_title' => $data['campaign_title'],
				'discount'       => 0,
			);
		}
		$cart->campaignbay['fee'][ $code ]['discount'] += $data['discount'];
	}


	/**
	 * Function.
	 */
	public static function add_bogo_free_product( $cart, $key, $bogo_data, &$discount_breakdown ) {
		$free_product_id = $bogo_data['free_product_id'];
		$quantity        = $bogo_data['free_quantity'];
		$discount        = $bogo_data['discount'];
		$discount_type   = $bogo_data['discount_type'];
		// get product data
		$product_data = Woocommerce::get_product( $free_product_id );

		// generating cart item key
		$cart_item_key      = $key . 'cb' . $bogo_data['campaign'] . 'fp' . $free_product_id;
		$campaignbay_parent = array(
			'cart_id' => $key,
			'data'    => $bogo_data,
		);

		$base_price = Woocommerce::get_product_base_price( $product_data );
		if ( 'percentage' === $discount_type && 100 === intval( $discount ) ) {
			$new_price = 0;
		} else {
			$new_price = Helper::calculate_price( $base_price, $discount, $discount_type );
		}
		$product_data->set_price( $new_price );
		$cart->cart_contents[ $cart_item_key ] = array(
			'key'                         => $cart_item_key,
			'product_id'                  => $free_product_id,
			'variation_id'                => null,
			'variation'                   => null,
			'quantity'                    => $quantity,
			'data'                        => $product_data,
			'data_hash'                   => Woocommerce::generate_cart_item_data_hash( $product_data ),
			'campaignbay_parent'          => $campaignbay_parent,
			'is_campaignbay_free_product' => true,
		);

		$campaign_id = $bogo_data['campaign'];
		if ( ! isset( $discount_breakdown[ $campaign_id ] ) ) {
			$discount_breakdown[ $campaign_id ] = array(
				'campaign_title' => $bogo_data['campaign_title'],
				'old_price'      => 0,
				'discount'       => 0,
			);
		}
		$discount_breakdown[ $campaign_id ]['old_price'] = $discount_breakdown[ $campaign_id ]['old_price'] + ( $quantity * $base_price );
		$discount_breakdown[ $campaign_id ]['discount']  = $discount_breakdown[ $campaign_id ]['discount'] + ( $quantity * ( $base_price - $new_price ) );

		wpab_campaignbay_log( sprintf( 'Added BOGO free product %s (ID: %d) to cart for campaign %d.', $product_data->get_name(), $free_product_id, $campaign_id ), 'INFO' );

		return $cart_item_key;
	}

	/**
	 * Function.
	 */
	public static function remove_from_cart( $cart, $cart_item_key ) {
		if ( isset( $cart->cart_contents[ $cart_item_key ] ) ) {
			$cart->removed_cart_contents[ $cart_item_key ] = $cart->cart_contents[ $cart_item_key ];

			unset( $cart->removed_cart_contents[ $cart_item_key ]['data'] );

			unset( $cart->cart_contents[ $cart_item_key ] );
			return true;
		}
		return false;
	}
}
