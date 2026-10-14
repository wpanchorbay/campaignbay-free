<?php
/**
 * The Condition helper class.
 *
 * This file is responsible for defining the Condition class, which contains all the logic
 * for matching a product against a campaign's targeting rules and conditions.
 * It is a core component of the discount engine.
 *
 * @link       https://wpanchorbay.com/campaignbay
 * @since      1.0.0
 *
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBay/Helper
 */

namespace WpabCampaignBay\Helper;

use WpabCampaignBay\Engine\PricingEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Condition Class.
 *
 * Handles the evaluation of specific conditions and rules defined within a campaign.
 * This class serves as a static helper to validate products against complex user
 * or cart-level rules.
 *
 * @since      1.0.7
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBay/Helper
 */
class Condition {

	/**
	 * Evaluates product-level conditions for a campaign.
	 *
	 * Iterates through all configured rules in the campaign and determines if the product
	 * matches based on the match type ('any' or 'all').
	 *
	 * @since 1.0.7
	 * @access public
	 * @static
	 *
	 * @param \WpabCampaignBay\Model\Campaign $campaign The campaign configuration object.
	 * @param \WC_Product                     $product  The WooCommerce product to evaluate.
	 *
	 * @return bool True if conditions are met, false otherwise.
	 */
	public static function check_product_level_conditions( $campaign, $product = null ) {

		$conditions                     = $campaign->get_conditions();
		$rules                          = $conditions['rules'] ?? array();
		$match_type                     = $conditions['match_type'] ?? 'any';
		$is_cart_level_condition        = false;
		$checking_cart_level_conditions = PricingEngine::get_instance()->is_calculating_cart_total_for_cart_level_conditions();
		wpab_campaignbay_log( 'Evaluating conditions for campaign: ' . $campaign->get_id() . ' - ' . $campaign->get_title(), 'INFO' );
		if ( $product ) {
			wpab_campaignbay_log( 'Target product: ' . $product->get_id() . ' - ' . $product->get_name(), 'INFO' );
		}
		if ( $checking_cart_level_conditions ) {
			wpab_campaignbay_log( 'Evaluating cart level conditions.', 'INFO' );
		} else {
			wpab_campaignbay_log( 'Not evaluating cart level conditions yet (pre-calculation).', 'INFO' );
		}
		if ( 'any' === $match_type && ! $checking_cart_level_conditions ) {
			foreach ( $rules as $rule ) {
				if ( self::is_cart_level_condition( $rule ) ) {
					$is_cart_level_condition = true;
					continue;
				}
				$result = self::pass_product_level_rule( $rule, $product );
				if ( $result ) {
					return true;
				}
			}
			// save cart level conditions in session
			if ( $is_cart_level_condition ) {
				PricingEngine::get_instance()->add_cart_level_condition_campaigns( $campaign );
			}
			// none of the product level are met
			return false;
		} elseif ( 'all' === $match_type && ! $checking_cart_level_conditions ) { // $match type === 'all'
			foreach ( $rules as $rule ) {
				if ( self::is_cart_level_condition( $rule ) ) {
					$is_cart_level_condition = true;
					continue;
				}
				$result = self::pass_product_level_rule( $rule, $product );
				if ( ! $result ) {
					return false;
				}
			}
			// save cart level conditions in session
			if ( $is_cart_level_condition ) {
				PricingEngine::get_instance()->add_cart_level_condition_campaigns( $campaign );
				// returning false we will will apply it after it matches all cart level conditions
				return false;
			}
			return true;
		} elseif ( 'any' === $match_type && $checking_cart_level_conditions ) {
			foreach ( $rules as $rule ) {
				if ( ! self::is_cart_level_condition( $rule ) ) {
					continue;
				}
				$result = self::pass_cart_level_rule( $rule, $product );
				if ( $result ) {
					return true;
				}
			}
			// none of the cart level are met
			return false;
		} elseif ( 'all' === $match_type && $checking_cart_level_conditions ) {
			foreach ( $rules as $rule ) {
				if ( ! self::is_cart_level_condition( $rule ) ) {
					continue;
				}
				$result = self::pass_cart_level_rule( $rule, $product );
				if ( ! $result ) {
					return false;
				}
			}
			return true;
		}
	}



	/**
	 * Validates a single product rule.
	 *
	 * Routes the validation logic to the appropriate handler based on the rule type
	 * (e.g., user_role).
	 *
	 * @since 1.0.7
	 * @access public
	 * @static
	 *
	 * @param array       $rule    The rule configuration array containing type and conditions.
	 * @param \WC_Product $product The product being checked.
	 *
	 * @return bool True if the rule is passed.
	 */
	public static function pass_product_level_rule( $rule, $product = null ) {
		$result    = true;
		$type      = $rule['type'];
		$condition = $rule['condition'];
		switch ( $type ) {
			case 'user_role':
				$result = self::check_user_role( $condition );
				break;
			case 'user_status':
				$result = self::check_user_status( $condition );
				break;
			case 'cart_items':
			case 'cart_total':
				wpab_campaignbay_log( 'Cart level condition deferred for post-calculation check.', 'INFO' );
				$result = true; // cannot run it now now just pass it , check it after cart total calculation
				break;
			default:
				$result = false;
				break;
		}

		/**
		 * Filters the result of a product level rule check.
		 *
		 * This filter allows plugins and themes to modify the result of a product level rule check.
		 *
		 * @since 1.0.7
		 *
		 * @param bool $result The result of the rule check.
		 * @param \WC_Product $product The product to check.
		 * @param string $type The type of the rule.
		 * @param array $condition The condition to check against.
		 *
		 * @return bool The filtered result of the rule check.
		 */
		return apply_filters( 'campaignbay_pass_product_level_condition', $result, $product, $type, $condition );
	}

	/**
	 * Verifies if the current user matches the required role condition.
	 *
	 * Checks the currently logged-in user's roles against the condition settings,
	 * supporting both inclusion ("is") and exclusion ("is not") logic.
	 * Handles edge cases for guests (non-logged-in users).
	 *
	 * @since 1.0.7
	 * @access public
	 * @static
	 *
	 * @param array $condition Array definition containing 'option' (role) and 'is_included' (bool).
	 *
	 * @return bool True if condition is satisfied.
	 */
	public static function check_user_role( $condition ) {
		$result      = true;
		$role        = $condition['option'] ?? '';
		$is_included = $condition['is_included'] ?? true;
		$user        = wp_get_current_user();
		if ( empty( $user ) && $is_included ) {
			return true;
		}
		if ( empty( $user ) && ! $is_included ) {
			return false;
		}

		$user_roles = $user->roles;
		if ( $is_included ) {
			$result = in_array( $role, $user_roles, true );
		} else {
			$result = ! in_array( $role, $user_roles, true );
		}
		return $result;
	}

	/**
	 * Verifies if the current visitor is a guest or a logged-in user, as the rule requires.
	 *
	 * @since 1.1.18
	 * @access public
	 * @static
	 *
	 * @param array $condition Array definition containing 'status' ('guest' or 'logged_in').
	 *
	 * @return bool True if condition is satisfied.
	 */
	public static function check_user_status( $condition ) {
		$status = $condition['status'] ?? '';
		if ( 'guest' === $status ) {
			return ! is_user_logged_in();
		}
		if ( 'logged_in' === $status ) {
			return is_user_logged_in();
		}
		return false;
	}

	/**
	 * Check if the rule is a cart level condition.
	 *
	 * @since 1.1.17
	 * @access public
	 * @static
	 *
	 * @param array $rule The rule configuration array.
	 *
	 * @return bool True if the rule is a cart level condition, false otherwise.
	 */
	public static function is_cart_level_condition( $rule ) {
		$type = $rule['type'];
		if ( in_array( $type, array( 'cart_total', 'cart_items' ), true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Verifies if a cart level rule is satisfied.
	 *
	 * @since 1.1.17
	 * @access public
	 * @static
	 *
	 * @param array       $condition The condition configuration array.
	 * @param \WC_Product $product The product to check.
	 *
	 * @return bool True if the condition is satisfied, false otherwise.
	 */
	public static function pass_cart_level_rule( $condition, $product = null ) {
		wpab_campaignbay_log( 'Evaluating cart level rule.', 'INFO' );
		$result    = true;
		$type      = $condition['type'];
		$condition = $condition['condition'];
		switch ( $type ) {
			case 'cart_total':
				$result = self::check_cart_total( $condition, $product );
				break;
			case 'cart_items':
				$result = self::check_cart_items( $condition, $product );
				break;
			default:
				$result = false;
				break;
		}
		if ( $result ) {
			wpab_campaignbay_log( 'Cart level rule passed.', 'INFO' );
		} else {
			wpab_campaignbay_log( 'Cart level rule failed.', 'INFO' );
		}
		return $result;
	}

	/**
	 * Verifies if the cart total condition is satisfied.
	 *
	 * @since 1.1.17
	 * @access public
	 * @static
	 *
	 * @param array       $condition The condition configuration array.
	 * @param \WC_Product $product The product to check.
	 *
	 * @return bool True if the condition is satisfied, false otherwise.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public static function check_cart_total( $condition, $product = null ) {
		$result     = true;
		$operator   = $condition['operator'];
		$value      = $condition['value'];
		$cart       = PricingEngine::get_instance()->get_calculated_cart();
		$cart_total = $cart['total'];
		wpab_campaignbay_log( sprintf( 'Checking cart total condition: Cart Total (%s) %s Target Value (%s)', $cart_total, $operator, $value ), 'INFO' );
		switch ( $operator ) {
			case '>=':
				$result = $cart_total >= $value;
				break;
			case '<=':
				$result = $cart_total <= $value;
				break;
			case '>':
				$result = $cart_total > $value;
				break;
			case '<':
				$result = $cart_total < $value;
				break;
			case '==':
				// Strict float equality is unreliable for a computed cart total (rounding
				// noise); compare at the store's configured price precision instead.
				$result = Woocommerce::round( (float) $cart_total ) === Woocommerce::round( (float) $value );
				break;
			default:
				$result = false;
				break;
		}
		return $result;
	}

	/**
	 * Verifies if the cart item count condition is satisfied.
	 *
	 * Compares the total quantity of units in the cart (free BOGO items excluded)
	 * against the configured value.
	 *
	 * @since 1.1.18
	 * @access public
	 * @static
	 *
	 * @param array       $condition The condition configuration array.
	 * @param \WC_Product $product   The product to check.
	 *
	 * @return bool True if the condition is satisfied, false otherwise.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public static function check_cart_items( $condition, $product = null ) {
		$operator   = $condition['operator'];
		$value      = (int) $condition['value'];
		$item_count = 0;
		if ( function_exists( 'WC' ) && isset( WC()->cart ) && null !== WC()->cart ) {
			// Count straight from the live cart, leaving out BOGO free lines.
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( ! empty( $cart_item['is_campaignbay_free_product'] ) ) {
					continue;
				}
				$item_count += (int) $cart_item['quantity'];
			}
		}
		wpab_campaignbay_log( sprintf( 'Checking cart items condition: Cart Items (%s) %s Target Value (%s)', $item_count, $operator, $value ), 'INFO' );
		switch ( $operator ) {
			case '>=':
				return $item_count >= $value;
			case '<=':
				return $item_count <= $value;
			case '>':
				return $item_count > $value;
			case '<':
				return $item_count < $value;
			case '==':
				return $item_count === $value;
			default:
				return false;
		}
	}
}
