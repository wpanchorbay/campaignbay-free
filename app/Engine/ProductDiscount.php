<?php

namespace WpabCampaignBay\Engine;

use WC_Product;
use WP_Error;
use WpabCampaignBay\Core\Common;
use WpabCampaignBay\Engine\CampaignManager;
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
class ProductDiscount {


	/**
	 * Summary of settings
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $settings = array();

	/**
	 * Summary of product
	 *
	 * @since 1.0.0
	 * @var WC_Product
	 */
	private $product = null;

	/**
	 * Summary of campaigns
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $campaigns = array();

	/**
	 * Summary of data
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $data = array();

	/**
	 * Private constructor to prevent direct instantiation.
	 * Use the static `create()` method instead.
	 *
	 * @since 1.0.0
	 * @param WC_Product $product The product object to be processed.
	 */
	private function __construct( WC_Product $product ) {
		$this->product   = $product;
		$this->settings  = Common::get_instance()->get_settings();
		$this->campaigns = CampaignManager::get_instance()->get_active_campaigns();
	}

	/**
	 * Static factory method to begin the processing chain.
	 *
	 * This is the entry point. It creates an instance of the processor for a given product.
	 *
	 * @since 1.0.0
	 * @param WC_Product|int $product The product object or product ID.
	 * @return self|null Returns a new instance for chaining, or null if the product is invalid.
	 */
	public static function create( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product instanceof WC_Product ) {
			// Return null or a dummy object if the product is invalid to prevent fatal errors.
			return null;
		}

		return new self( $product );
	}


	/**
	 * Calculates and applies the best discount to the product object.
	 *
	 * This is the main "worker" method. It loops through campaigns, finds the best
	 * price, sets the sale price on the product object, and attaches custom
	 * metadata about the discount.
	 *
	 * @since 1.0.0
	 * @return self Returns the instance to allow for further chaining.
	 */
	public function apply_discounts() {

		// if variable product just apply on variations
		if ( Woocommerce::product_type_is( $this->product, 'variable' ) ) {
			return $this->apply_discounts_on_variations();
		}
		$original_price   = (float) Woocommerce::get_product_regular_price( $this->product );
		$base_price       = (float) Woocommerce::get_product_base_price( $this->product );
		$best_price       = null;
		$applied_campaign = null;
		$applied_tier     = null;
		$this->data       = array(
			'original_price' => $original_price,
			'base_price'     => $base_price,
			'on_discount'    => false,
			'is_on_sale'     => false,
		);

		foreach ( $this->campaigns as $campaign ) {
			if ( $campaign->get_type() !== 'scheduled' && $campaign->get_type() !== 'earlybird' ) {
				/**
				 * Fires when a product-level campaign type not native to the free version is being processed.
				 *
				 * This hook is the primary entry point for Pro add-ons to integrate their own
				 * campaign logic into the pricing engine. A function hooked here should
				 * perform its calculations and can modify the `$product_discount_processor`
				 * object directly if needed (since it's passed by reference).
				 *
				 * @since 1.0.0
				 * @hook campaignbay_product_campaign_discount_calculation
				 *
				 * @param \WC_Product $product The product object currently being processed.
				 * @param \WpabCampaignBay\Engine\ProductDiscount $product_discount_processor The instance of the current pricing processor object.
				 * @param object $campaign The campaign object that needs to be processed.
				 * @param float $original_price The product's original regular price.
				 * @param float $base_price The price being used as the starting point for calculations (could be sale price).
				 * @param float|null $best_price The best discounted price found so far in the loop.
				 * @param object|null $applied_campaign The campaign object corresponding to the current best price.
				 * @param array|null $applied_tier The tier data (if any) corresponding to the current best price.
				 */
				do_action( 'campaignbay_product_campaign_discount_calculation', $this->product, $this, $campaign, $original_price, $base_price, $best_price, $applied_campaign, $applied_tier );

				continue;
			}
			if ( ! $campaign->is_applicable_to_product( $this->product ) ) {
				continue;
			}
			if ( $campaign->get_type() === 'earlybird' ) {
				$tier      = Helper::earlybird_current_tier( $campaign );
				$new_price = $this->calculate_earlybird_price( $tier, $base_price );
			} elseif ( $campaign->get_type() === 'scheduled' ) {
				$new_price = $this->calculate_scheduled_price( $campaign, $base_price );
			}

			if ( $this->is_better_price( $new_price, $best_price ) ) {
				$best_price       = $new_price;
				$applied_campaign = $campaign;
				$applied_tier     = $tier ?? null;
			}
		}
		if ( $applied_campaign !== null ) {

			/**
			 * Fires within the campaign loop, just before the current campaign's price is compared to the best price.
			 *
			 * This action hook is a powerful entry point for Pro add-ons to run their own logic for custom
			 * campaign types. A Pro add-on could hook here, check if `$campaign` is a "Pro" type,
			 * run its own price calculation, and then use the 'campaignbay_product_best_price' filter
			 * to inject its calculated price into the competition.
			 *
			 * @since 1.0.0
			 * @hook campaignbay_product_discount_before_appling
			 *
			 * @param WC_Product $product          The WooCommerce product object currently being processed.
			 * @param object     $product_discount The instance of the ProductDiscount class.
			 * @param object     $campaign         The specific campaign object being evaluated in this loop iteration.
			 * @param float      $original_price   The product's original regular price.
			 * @param float      $base_price       The price being used as the base for calculations (could be regular or sale price).
			 * @param float|null $best_price       The best discounted price found so far from previous campaigns in the loop.
			 * @param object|null $applied_campaign The campaign object that corresponds to the current best price.
			 * @param object|null $applied_tier     The specific tier (if any) of the applied campaign.
			 */
			do_action( 'campaignbay_product_discount_before_appling', $this->product, $this, $campaign, $original_price, $base_price, $best_price, $applied_campaign, $applied_tier );

			$this->data['on_discount'] = true;
			$this->data['is_simple']   = true;
			$data                      = array(
				'campaign'                 => $applied_campaign->get_id(),
				'campaign_title'           => $applied_campaign->get_title(),
				'price'                    => $best_price,
				'discount'                 => $base_price - $best_price,
				'message_format'           => $applied_campaign->get_settings()['message_format'] ?? '',
				'show_product_message'     => $applied_campaign->get_settings()['show_product_message'] ?? true,
				'display_as_regular_price' => $applied_campaign->get_settings()['display_as_regular_price'] ?? false,
			);
			if ( $applied_campaign->get_type() === 'scheduled' ) {
				$data['value'] = $applied_campaign->get_discount_value() ?? null;
				$data['type']  = $applied_campaign->get_discount_type() ?? null;
			}
			if ( $applied_campaign->get_type() === 'earlybird' ) {
				$data['value'] = $applied_tier['value'];
				$data['type']  = $applied_tier['type'];
			}
			$this->data['simple'] = $data;

			$this->product->set_price( $this->data['simple']['price'] );

			$this->product->set_regular_price( $this->data['base_price'] );
			// add on sale badge
			if ( ! $this->data['simple']['display_as_regular_price'] ) {
				$this->data['is_on_sale'] = true;
			} else {
				$this->data['is_on_sale'] = false;
				$this->product->set_regular_price( $this->data['simple']['price'] );
			}

			/**
			 * Fires within the campaign loop, just before the current campaign's price is compared to the best price.
			 *
			 * This action hook is a powerful entry point for Pro add-ons to run their own logic for custom
			 * campaign types. A Pro add-on could hook here, check if `$campaign` is a "Pro" type,
			 * run its own price calculation, and then use the 'campaignbay_product_best_price' filter
			 * to inject its calculated price into the competition.
			 *
			 * @since 1.0.0
			 * @hook campaignbay_product_discount_before_appling
			 *
			 * @param WC_Product $product          The WooCommerce product object currently being processed.
			 * @param object     $product_discount The instance of the ProductDiscount class.
			 * @param object     $campaign         The specific campaign object being evaluated in this loop iteration.
			 * @param float      $original_price   The product's original regular price.
			 * @param float      $base_price       The price being used as the base for calculations (could be regular or sale price).
			 * @param float|null $best_price       The best discounted price found so far from previous campaigns in the loop.
			 * @param object|null $applied_campaign The campaign object that corresponds to the current best price.
			 * @param object|null $applied_tier     The specific tier (if any) of the applied campaign.
			 */
			do_action( 'campaignbay_product_discount_after_appling', $this->product, $this, $campaign, $original_price, $base_price, $best_price, $applied_campaign, $applied_tier );

		}

		/**
		 * Filters the final array of calculated discount metadata before it is attached to the product object.
		 *
		 * This is the primary filter for extending or modifying the data that gets stored with the product.
		 * A Pro add-on could use this to add its own flags or data (e.g., 'free_shipping_applied' => true)
		 * to the metadata, which can then be used by other parts of the plugin, like the cart.
		 *
		 * @since 1.0.0
		 * @hook campaignbay_product_discount_meta
		 *
		 * @param array      $data    The array of calculated discount data for the product.
		 * @param WC_Product $product The WooCommerce product object this data will be attached to.
		 *
		 * @return array The modified data array.
		 */
		$this->product->add_meta_data( 'campaignbay', apply_filters( 'campaignbay_product_discount_meta', $this->data, $this->product ), true );
		return $this;
	}

	/**
	 * Summary of apply_discounts_on_variations
	 *
	 * @since 1.0.0
	 * @return self Returns the instance to allow for further chaining.
	 */
	public function apply_discounts_on_variations() {
		$prices     = Woocommerce::get_variation_prices( $this->product );
		$this->data = array(
			'on_discount' => false,
		);

		if ( empty( $prices['price'] ) ) {
			return $this;
		}
		$base_price     = array();
		$original_price = array();
		foreach ( $prices['price'] as $key => $value ) {
			$meta = Woocommerce::get_product( $key )->get_meta( 'campaignbay' );
			if ( $meta['on_discount'] ) {
				$this->data['on_discount'] = true;
			}

			if ( $meta['is_on_sale'] ) {
				$this->data['is_on_sale'] = true;
			}

			if ( isset( $meta['is_simple'] ) && $meta['is_simple'] ) {
				$this->data['is_simple'] = $meta['is_simple'];
				if ( ! isset( $meta['simple'] ) ) {
					$this->data['simple'] = array();
				}
				$this->data['simple'][ $key ] = $meta['simple'];
			}
			$base_price[ $key ]     = $meta['base_price'];
			$original_price[ $key ] = $meta['original_price'];
		}

		$this->data['base_price']     = $base_price;
		$this->data['original_price'] = $original_price;
		$this->product->add_meta_data( 'campaignbay', $this->data, true );
		return $this;
	}

	/**
	 * Returns the final, modified product object.
	 *
	 * @since 1.0.0
	 * @return WC_Product The processed product object.
	 */
	public function get_product() {
		return $this->product;
	}


	/**
	 * Handles the price calculation for "scheduled" type campaigns.
	 * This is a sub-dispatcher that checks for percentage or fixed discounts.
	 *
	 * @since 1.0.0
	 * @param object $campaign The campaign object.
	 * @param float  $base_price The price before discount.
	 * @return float The calculated price.
	 */
	public function calculate_scheduled_price( $campaign, $base_price ) {
		$discount_type    = $campaign->get_discount_type();
		$discount_value   = $campaign->get_discount_value();
		$calculated_price = $base_price;

		if ( $discount_type === 'percentage' ) {
			$calculated_price = $this->calculate_percentage_price( $base_price, $discount_value );
		} elseif ( $discount_type === 'fixed' ) {
			$calculated_price = $this->calculate_fixed_price( $base_price, $discount_value );
		}

		return $calculated_price;
	}

	/**
	 * Handles the price calculation for "earlybird" type campaigns.
	 * This is a sub-dispatcher that checks for percentage or fixed discounts.
	 *
	 * @since 1.0.0
	 * @param array $tier The tier data.
	 * @param float $base_price The price before discount.
	 * @return float The calculated price.
	 */
	public function calculate_earlybird_price( $tier, $base_price ) {
		$discount_type    = $tier['type'];
		$discount_value   = $tier['value'];
		$calculated_price = $base_price;
		if ( $discount_type === 'percentage' ) {
			$calculated_price = $this->calculate_percentage_price( $base_price, $discount_value );
		} elseif ( $discount_type === 'fixed' || $discount_type === 'currency' ) {
			$calculated_price = $this->calculate_fixed_price( $base_price, $discount_value );
		}
		return $calculated_price;
	}

	/**
	 * Calculates a price based on a fixed amount discount.
	 *
	 * @since 1.0.0
	 * @param float $base_price The price before discount.
	 * @param float $discount_value The fixed amount to subtract.
	 * @return float The final price, ensuring it's not below zero.
	 */
	public function calculate_fixed_price( $base_price, $discount_value ) {
		return max( 0, $base_price - $discount_value );
	}

	/**
	 * Calculates a price based on a percentage discount.
	 *
	 * @since 1.0.0
	 * @param float $base_price The price before discount.
	 * @param float $discount_value The percentage to subtract (e.g., 10 for 10%).
	 * @return float The final price, ensuring it's not below zero.
	 */
	public function calculate_percentage_price( $base_price, $discount_value ) {
		$discount_amount = ( $discount_value / 100 ) * $base_price;
		return max( 0, $base_price - $discount_amount );
	}

	/**
	 * Determines if a new price is better than the current best price based on the priority method.
	 *
	 * @since 1.0.0
	 * @param float $new_price The new price to compare.
	 * @param float $current_best_price The current best price.
	 * @return bool True if the new price is better, false otherwise.
	 */
	public function is_better_price( $new_price, $current_best_price ) {
		if ( $new_price === null ) {
			return false;
		}
		if ( $current_best_price === null ) {
			return true;
		}
		$setting = Common::get_instance()->get_settings( 'product_priorityMethod' );

		if ( $setting === 'apply_highest' ) {
			return $new_price < $current_best_price;
		} elseif ( $setting === 'apply_lowest' ) {
			return $new_price > $current_best_price;
		} elseif ( $setting === 'apply_first' ) {
			return $current_best_price === null;
		}
		return false;
	}
}
