<?php
/**
 * The Filter helper class.
 *
 * This file is responsible for defining the Filter class, which contains all the logic
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

use WpabCampaignBay\Core\Common;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * Filter Class.
 *
 * This helper class provides methods to evaluate whether a given WooCommerce product
 * matches the targeting conditions of a CampaignBay campaign. It handles logic
 * for product, category, and sale status matching, including exclusion rules.
 *
 * It is designed as a singleton to ensure a single, consistent instance throughout a request.
 *
 * @since      1.0.0
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBay/Helper
 * @author     WP Anchor Bay <wpanchorbay@gmail.com>
 */
class Filter {

	/**
	 * The single instance of the class.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    Filter
	 */
	private static $instance = null;


	/**
	 * Gets the singleton instance of this class.
	 *
	 * Ensures only one instance of Filter exists in memory at any time.
	 * This is important for maintaining consistent state across the application.
	 *
	 * @since  1.0.0
	 * @access public
	 * @static
	 *
	 * @return Filter The singleton instance.
	 */
	public static function get_instance() {
		static $instance = null;
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 *
	 * Use Filter::get_instance() to get the singleton instance.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function __construct() {}



	/**
	 * Determines if a product matches a campaign's targeting rules.
	 *
	 * This is the main entry point for product-campaign matching logic.
	 * It evaluates sale item exclusions, product-level conditions, and
	 * targeting rules (entire store, specific products, categories, or tags).
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param \WC_Product                     $product  The WooCommerce product to evaluate.
	 * @param \WpabCampaignBay\Model\Campaign $campaign The campaign containing targeting rules.
	 *
	 * @return bool True if the product matches the campaign's rules, false otherwise.
	 */
	public function match( $product, $campaign ) {
		$result = false;

		if ( $this->exclude_sale_item_and_on_sale( $product, $campaign ) ) {
			return false;
		}

		if ( ! Condition::check_product_level_conditions( $campaign, $product ) ) {
			wpab_campaignbay_log( 'Product level conditions not matched: ' . $campaign->get_title() . ' -- -- -- ' . $product->get_name() );
			return false;
		}

		wpab_campaignbay_log( 'Product level conditions matched: ' . $campaign->get_title() . ' -- -- -- ' . $product->get_name(), 'INFO' );

		/**
		 * Fires before evaluating product against campaign targeting rules.
		 *
		 * Allows third-party code to perform actions or modify state
		 * before the main matching logic runs.
		 *
		 * @since 1.0.7
		 * @hook  campaignbay_before_filter_match
		 *
		 * @param bool                              $result   The initial result.
		 * @param \WC_Product                       $product  The product being evaluated.
		 * @param \WpabCampaignBay\Model\Campaign   $campaign The campaign being matched against.
		 */
		do_action( 'campaignbay_before_filter_match', $result, $product, $campaign );
		if ( is_a( $product, 'WC_Product' ) ) {

			$is_on_sale        = Woocommerce::is_product_in_sale( $product );
			$product_id        = Woocommerce::get_product_or_parent_id( $product );
			$type              = $campaign->get_target_type();
			$is_exclude        = $campaign->get_is_exclude();
			$exclude_sale_item = $campaign->get_exclude_sale_items();

			if ( $is_on_sale && $exclude_sale_item ) {
				$result = false;
			}
			if ( 'entire_store' === $type ) {
				$result = true;
			} elseif ( 'product' === $type ) {
				$result = $this->compareWithProducts( $campaign->get_target_ids(), $is_exclude, $product_id, $product );
			} elseif ( 'category' === $type ) {
				$result = $this->compareWithCategories( $product, $campaign->get_target_ids(), $is_exclude );
			} elseif ( 'tag' === $type ) {
				$result = $this->compareWithTags( $product, $campaign->get_target_ids(), $is_exclude );
			}
			// $product = Woocommerce::getParentProduct($product);
			// return $this->compareWithTags($product, $values, $method);
			// }
			// elseif ('product_attributes' === $type) {
			// return $this->compareWithAttributes($product, $values, $method, $cart_item);
			// } elseif ('product_sku' === $type) {
			// return $this->compareWithSku($product, $values, $method);
			// } elseif ('product_on_sale' === $type) {
			// return $this->compareWithOnSale($product, $method);
			// } elseif (in_array($type, array_keys(Woocommerce::getCustomProductTaxonomies()))) {
			// return $this->compareWithCustomTaxonomy($product_id, $values, $method, $type);
			// }

			/**
			 * Filters the result of product-campaign matching.
			 *
			 * Allows third-party code to override or modify the matching result
			 * based on custom logic or additional conditions.
			 *
			 * @since 1.0.7
			 * @hook  campaignbay_filter_match
			 *
			 * @param bool                              $result   The current matching result.
			 * @param \WC_Product                       $product  The product being evaluated.
			 * @param \WpabCampaignBay\Model\Campaign   $campaign The campaign being matched against.
			 *
			 * @return bool Modified matching result.
			 */
			$result = apply_filters( 'campaignbay_filter_match', $result, $product, $campaign );
		}
		/**
		 * Fires after evaluating product against campaign targeting rules.
		 *
		 * Allows third-party code to perform actions after
		 * the matching logic has completed.
		 *
		 * @since 1.0.7
		 * @hook  campaignbay_after_filter_match
		 *
		 * @param bool                              $result   The final matching result.
		 * @param \WC_Product                       $product  The product that was evaluated.
		 * @param \WpabCampaignBay\Model\Campaign   $campaign The campaign that was matched against.
		 */
		do_action( 'campaignbay_after_filter_match', $result, $product, $campaign );

		return $result;
	}



	/**
	 * Compares a product's tags against campaign tag filters.
	 *
	 * Checks if any of the product's tags (a variation uses its parent's tags) match the
	 * campaign's target tag IDs, respecting the inclusion/exclusion mode.
	 *
	 * @since  1.1.18
	 * @access protected
	 *
	 * @param \WC_Product $product    The product to check.
	 * @param array       $target_ids Array of tag IDs to match against.
	 * @param bool        $is_exclude If true, product must NOT have any of the tags.
	 *
	 * @return bool True if product matches the tag criteria.
	 */
	protected function compareWithTags( $product, $target_ids, $is_exclude ) {
		$tags               = array_map( 'intval', Woocommerce::get_product_tags( $product ) );
		$target_ids         = array_map( 'intval', (array) $target_ids );
		$is_product_has_tag =  count( array_intersect( $tags, $target_ids ) ) > 0;
		return $is_exclude ? ! $is_product_has_tag : $is_product_has_tag;
	}

	/**
	 * Compares a product's categories against campaign category filters.
	 *
	 * Checks if any of the product's categories match the campaign's target category IDs,
	 * respecting the inclusion/exclusion mode.
	 *
	 * @since  1.0.0
	 * @access protected
	 *
	 * @param \WC_Product $product    The product to check.
	 * @param array       $target_ids Array of category IDs to match against.
	 * @param bool        $is_exclude If true, product must NOT be in any of the categories.
	 *
	 * @return bool True if product matches the category criteria.
	 */
	protected function compareWithCategories( $product, $target_ids, $is_exclude ) {
		$categories             = Woocommerce::get_product_categories( $product );
		$is_product_in_category = count( array_intersect( $categories, $target_ids ) ) > 0;
		if ( false === $is_exclude ) {
			return $is_product_in_category;
		} elseif ( true === $is_exclude ) {
			return ! $is_product_in_category;
		}
		return false;
	}

	/**
	 * Compares a product against campaign product filters.
	 *
	 * Checks if the product ID is in the campaign's target product IDs list,
	 * respecting the inclusion/exclusion mode.
	 *
	 * @since  1.0.0
	 * @access protected
	 *
	 * @param array       $target_ids Array of product IDs to match against.
	 * @param bool        $is_exclude If true, product must NOT be in the list.
	 * @param int         $product_id The product ID to check.
	 * @param \WC_Product $product    The product object (for potential future use).
	 *
	 * @return bool True if product matches the product criteria.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	protected function compareWithProducts( $target_ids, $is_exclude, $product_id, $product ) {
		$result = $this->checkInList( $product_id, $is_exclude, $target_ids );
		return $result;
	}


	/**
	 * Checks if a product ID exists in a target list.
	 *
	 * Performs a type-safe comparison of the product ID against the target IDs array,
	 * and inverts the result when in exclusion mode.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int|string $product_id The product ID to search for.
	 * @param bool       $is_exclude If true, returns true when product is NOT in the list.
	 * @param array      $target_ids Array of target product IDs.
	 *
	 * @return bool True if product matches the list criteria.
	 */
	function checkInList( $product_id, $is_exclude, $target_ids ) {
		$result     = false;
		$target_ids = array_map( 'strval', $target_ids );
		$product_id = (string) ( $product_id );
		if ( ! $is_exclude ) {
			$result = ( in_array( $product_id, $target_ids, true ) );
		} else {
			$result = ! ( in_array( $product_id, $target_ids, true ) );
		}
		return $result;
	}

	/**
	 * Checks if a product should be excluded due to sale status.
	 *
	 * If the campaign has "exclude sale items" enabled and the product
	 * is currently on sale, the product should be excluded from the campaign.
	 *
	 * @since  1.0.0
	 * @access protected
	 *
	 * @param \WC_Product                     $product  The product to check.
	 * @param \WpabCampaignBay\Model\Campaign $campaign The campaign with exclusion settings.
	 *
	 * @return bool True if product should be excluded (is on sale and exclusion is enabled).
	 */
	protected function exclude_sale_item_and_on_sale( $product, $campaign ) {
		$exclude_sale_item = $campaign->get_exclude_sale_items();
		$is_on_sale        = Woocommerce::is_product_in_sale( $product );
		if ( $is_on_sale && $exclude_sale_item ) {
			return true;
		}

		return false;
	}
}
