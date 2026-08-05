<?php
/**
 * The main Helper class.
 *
 * This file defines the Helper class, a collection of static utility methods used
 * throughout the CampaignBay plugin. It handles tasks like data filtering, price
 * calculation, HTML sanitization, and message generation.
 *
 * @link       https://wpanchorbay.com/campaignbay
 * @since      1.0.0
 *
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBay/Helper
 */

namespace WpabCampaignBay\Helper;

use DateTime;
use DateTimeZone;
use Exception;
use WpabCampaignBay\Core\Common;
use WpabCampaignBay\Engine\CampaignManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Helper Class.
 *
 * This class provides a collection of static utility methods accessible globally
 * within the plugin. It is designed as a central place for reusable logic,
 * including fetching and filtering campaigns by type, calculating discount prices,
 * generating promotional messages, and creating frontend HTML elements.
 *
 * @since      1.0.0
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBay/Helper
 * @author     WP Anchor Bay <wpanchorbay@gmail.com>
 */
class Helper {

	/**
	 * Retrieves a specific setting value for the CampaignBay plugin.
	 *
	 * Delegates to the Common core class to fetch configuration options
	 * stored in the database.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param string $name The unique key of the setting to retrieve.
	 *
	 * @return mixed The setting value, or null/default if not found.
	 */
	public static function get_settings( $name ) {
		return Common::get_instance()->get_settings( $name );
	}

	/**
	 * Sanitizes and cleans HTML content.
	 *
	 * Removes potentially malicious tags like script, style, and iframe.
	 * Allows a specific set of safe HTML tags (br, strong, span, div, p, table related).
	 * Useful for safely outputting user-generated or dynamic content.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param string $html The raw HTML content to be cleaned.
	 * @param bool   $echo Optional. Whether to echo the output directly. Default false.
	 *
	 * @return string|void The sanitized HTML string, or void if $echo is true.
	 */
	public static function get_clean_html( $html, $should_echo = false ) {
		try {
			$html         = html_entity_decode( $html );
			$html         = preg_replace( '/(<(script|style|iframe)\b[^>]*>).*?(<\/\2>)/is', '$1$3', $html );
			$allowed_html = array(
				'br'     => array(),
				'strong' => array(),
				'span'   => array(
					'class' => array(),
					'style' => array(),
				),
				'div'    => array(
					'class' => array(),
					'style' => array(),
				),
				'p'      => array(
					'class' => array(),
					'style' => array(),
				),
				'table'  => array(
					'class' => array(),
					'style' => array(),
				),
				'tr'     => array(
					'class' => array(),
					'style' => array(),
				),
				'tbody'  => array(
					'class' => array(),
					'style' => array(),
				),
				'td'     => array(
					'class' => array(),
					'style' => array(),
				),
				'th'     => array(
					'class' => array(),
					'style' => array(),
				),
				'thead'  => array(
					'class' => array(),
					'style' => array(),
				),
			);
			if ( $should_echo ) {
				echo wp_kses( $html, $allowed_html );
				return;
			}
			return wp_kses( $html, $allowed_html );
		} catch ( \Exception $e ) {
			return '';
		}
	}
	/**
	 * Generate message.
	 *
	 * This method generates a message by replacing placeholders in a format string with provided arguments.
	 *
	 * @since 1.0.0
	 *
	 * @param string $format The format string containing placeholders.
	 * @param array $args The arguments to replace placeholders with.
	 * @return string The generated message.
	 */
	/**
	 * Generates a formatted message with placeholder replacements.
	 *
	 * Replaces placeholders (keys in $args) with values in the format string.
	 * The result is sanitized before being returned.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param string $format The message string containing placeholders (e.g., "Hello {name}").
	 * @param array  $args   Associative array where keys are placeholders and values are replacements.
	 *
	 * @return string The formatted and sanitized message.
	 */
	public static function generate_message( $format, $args ) {
		$format = self::get_clean_html( $format );
		if ( '' === $format ) {
			return '';
		}
		return self::get_clean_message( str_replace( array_keys( $args ), array_values( $args ), $format ) );
	}

	/**
	 * Sanitizes a string to ensure it is safe for display.
	 *
	 * Currently a passthrough, but intended as a safety net for any
	 * content that might have missed prior sanitization.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param string $message The raw message string.
	 *
	 * @return string The sanitized message.
	 */
	public static function get_clean_message( $message ) {
		/**
		 * We will implement it later. It is for failsafe if anyone forgot to clean before printing.
		 *
		 * Currently cleaned before printing
		 */
		return $message;
	}

	/**
	 * Retrieves all 'quantity' type campaigns applicable to a product.
	 *
	 * Wrapper for get_type_of_campaign specialized for quantity discounts.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param object $product The WooCommerce product to check.
	 *
	 * @return array Array of matching Campaign attributes.
	 */
	public static function get_quantity_campaigns( $product ) {
		return self::get_type_of_campaign( 'quantity', $product );
	}
	/**
	 * Retrieves all 'bogo' type campaigns applicable to a product.
	 *
	 * Wrapper for get_type_of_campaign specialized for Buy One Get One offers.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param object $product The WooCommerce product to check.
	 *
	 * @return array Array of matching Campaign objects.
	 */
	public static function get_bogo_campaigns( $product ) {
		return self::get_type_of_campaign( 'bogo', $product );
	}

	/**
	 * Retrieves campaigns of a specific type for a specific product.
	 *
	 * Fetches all active campaigns and filters them by the requested type.
	 * If a product is provided, checks applicability against that product.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param string      $type    The campaign type identifier (e.g., 'quantity', 'bogo').
	 * @param object|null $product Optional. The WooCommerce product object to check against.
	 *
	 * @return array Array of Campaign objects matching the criteria.
	 */
	public static function get_type_of_campaign( $type, $product = null ) {
		$active_campaigns = CampaignManager::get_instance()->get_active_campaigns();
		$campaigns        = array();
		foreach ( $active_campaigns as $campaign ) {
			if ( $campaign->get_type() !== $type ) {
				continue;
			}
			if ( null === $product ) {
				$campaigns[] = $campaign;
			} else {
				$is_applicable = $campaign->is_applicable_to_product( $product );

				if ( $is_applicable ) {
					$campaigns[] = $campaign;
				}
			}
		}
		return $campaigns;
	}
	/**
	 * Combines quantity tiers with their parent campaign data.
	 *
	 * Iterates through all applicable quantity campaigns for a product and extract
	 * tier details, enriching them with campaign metadata (ID, title, settings).
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param object $product The WooCommerce product object.
	 *
	 * @return array Array of tiers with flattened campaign info.
	 */
	public static function get_quantity_tiers_with_campaign( $product ) {
		$quantity_campaigns = self::get_quantity_campaigns( $product );
		$tiers              = array();
		foreach ( $quantity_campaigns as $campaign ) {
			foreach ( $campaign->get_tiers() as $tier ) {
				$tiers[] = array(
					'campaign'       => $campaign->get_id(),
					'campaign_title' => $campaign->get_title(),
					'settings'       => $campaign->get_settings(),
					'min'            => $tier['min'],
					'max'            => $tier['max'],
					'value'          => $tier['value'],
					'type'           => $tier['type'],
				);
			}
		}
		return $tiers;
	}

	/**
	 * Filters and retrieves unique, most beneficial pricing tiers.
	 *
	 * Processes raw tier data to remove duplicates and keep only the best pricing
	 * options for overlapping ranges. Sorts tiers by minimum quantity.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param array $tiers Array of tier definitions.
	 * @param float $price The base price of the product to calculate discounts against.
	 *
	 * @return array Sorted array of unique, optimal pricing tiers.
	 */
	public static function get_unique_quantity_tiers( $tiers, $price ) {
		$unique_tiers = array();
		foreach ( $tiers as $tier ) {
			$key                = $tier['min'] . '-' . $tier['max'];
			$current_tier_price = (float) self::get_quantity_price( $price, $tier );
			if ( isset( $unique_tiers[ $key ] ) ) {
				$unique_tier_price = (float) $unique_tiers[ $key ]['price'];

				$is_better = self::is_better_price( $unique_tier_price, $current_tier_price );
				if ( $is_better ) {
					$unique_tiers[ $key ]          = $tier;
					$unique_tiers[ $key ]['price'] = (float) $current_tier_price;
				}
				continue;
			}
			$unique_tiers[ $key ]          = $tier;
			$unique_tiers[ $key ]['price'] = (float) $current_tier_price;
		}
		$unique_tiers = array_values( $unique_tiers );
		usort(
			$unique_tiers,
			function ( $a, $b ) {
				return $a['min'] - $b['min'];
			}
		);
		return $unique_tiers;
	}

	/**
	 * Generates the promotional message text for a specific quantity tier.
	 *
	 * Uses the configured message format (from tier or global settings) to create
	 * a dynamic string indicating remaining quantity needed or discount amount.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param array $tier The tier data array including 'settings', 'type', 'value', 'remaining'.
	 *
	 * @return string The formatted message.
	 */
	public static function get_quantity_message( $tier ) {
		$message_format = $tier['settings']['cart_quantity_message_format'] ?? '';
		$type           = $tier['type'];
		$value          = $tier['value'];
		if ( '' === $message_format || null === $message_format ) {
			$message_format = self::get_settings( 'percentage' === $type ? 'cart_quantity_message_format_percentage' : 'cart_quantity_message_format_fixed' );
		}
		$message = self::generate_message(
			$message_format,
			array(
				'{remainging_quantity_for_next_offer}' => $tier['remaining'],
				'{percentage_off}'                     => $value,
				'{amount_off}'                         => $value,
			)
		);
		return $message;
	}

	/**
	 * Retrieves BOGO (Buy One Get One) tiers for a product.
	 *
	 * Fetches all applicable BOGO campaigns, calculates the 'ratio' of free items,
	 * removes duplicates based on 'buy quantity', and sorts by best value.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param object $product The WooCommerce product object.
	 *
	 * @return array Sorted array of BOGO tier data.
	 */
	public static function get_bogo_tiers( $product ) {
		// get all bogo campaigns
		$bogo_campaigns = self::get_bogo_campaigns( $product );
		$tiers          = array();
		foreach ( $bogo_campaigns as $campaign ) {
			// get all tiers with campaigns necessary data
			$tiers[] = array(
				'campaign'       => $campaign->get_id(),
				'campaign_title' => $campaign->get_title(),
				'settings'       => $campaign->get_settings(),
				'buy_quantity'   => $campaign->get_tiers()[0]['buy_quantity'],
				'get_quantity'   => $campaign->get_tiers()[0]['get_quantity'],
				'ratio'          => (int) ( ( $campaign->get_tiers()[0]['get_quantity'] / $campaign->get_tiers()[0]['buy_quantity'] ) * 100 ),
			);
		}
		// remove duplicates
		$tmp_tiers = array();
		foreach ( $tiers as $tier ) {
			// check if buy quantity is already in tmp tiers
			if ( isset( $tmp_tiers[ $tier['buy_quantity'] ] ) ) {
				continue;
			}
			// add to tmp tiers
			$tmp_tiers[ $tier['buy_quantity'] ] = $tier;
		}
		$tiers = array_values( $tmp_tiers );
		// sort tiers by ratio
		usort(
			$tiers,
			function ( $a, $b ) {
				return $b['ratio'] - $a['ratio'];
			}
		);
		return $tiers;
	}

	/**
	 * Render product bogo message.
	 *
	 * This method renders the message of a bogo campaign for a specific product.
	 *
	 * @since 1.0.0
	 *
	 * @param object $product The product object.
	 * @return string The rendered bogo message.
	 */
	public static function render_product_bogo_message( $product ) {
		$tiers = self::get_bogo_tiers( $product );
		usort(
			$tiers,
			function ( $a, $b ) {
				return $a['buy_quantity'] - $b['buy_quantity'];
			}
		);
		$message = '';
		$format  = '';
		$tier    = array();
		if ( ! empty( $tiers ) ) {
			$tier              = $tiers[0];
			$show_bogo_message = wpab_campaignbay_get_value( $tier, 'settings.show_bogo_message', true );
			if ( ! $show_bogo_message ) {
				return '';
			}
			$format  = wpab_campaignbay_get_value( $tier, 'settings.bogo_banner_message_format', Common::get_instance()->get_settings( 'bogo_banner_message_format' ) );
			$message = self::generate_message(
				$format,
				array(
					'{buy_quantity}' => $tier['buy_quantity'],
					'{get_quantity}' => $tier['get_quantity'],
				)
			);
		}

		/**
		 * Filter hook to allow modification of the final discount bogo message string.
		 *
		 * This is useful for developers who want to change the wording or add extra
		 * details to the promotional bogo banner on the product page.
		 *
		 * @since 1.0.0
		 * @deprecated 1.0.7
		 * @hook campaignbay_product_display_product_bogo_message
		 *
		 * @param string     $message The generated message HTML.
		 * @param WC_Product $product The current product object.
		 * @param string     $format  The original message format.
		 */
		$message = apply_filters_deprecated( 'campaignbay_product_display_product_bogo_message', array( $message, $product, $format ), '1.0.7', 'campaignbay_product_page_bogo_message', 'use campaignbay_product_page_bogo_message instead' );
		/**
		 * Filter hook to allow modification of the final discount bogo message string.
		 *
		 * This is useful for developers who want to change the wording or add extra
		 * details to the promotional bogo banner on the product page.
		 *
		 * @since 1.0.7
		 * @hook campaignbay_product_page_bogo_message
		 *
		 * @param string     $message The generated message HTML.
		 * @param WC_Product $product The current product object.
		 * @param string     $format  The original message format.
		 * @param array      $tier    The current tier.
		 */
		$message = apply_filters( 'campaignbay_product_page_bogo_message', $message, $product, $format, $tier );
		if ( '' === $message || null === $message ) {
			return '';
		}

		Woocommerce::print_notice(
			$message,
			'success'
		);
		return '';
	}

	/**
	 * Calculates BOGO meta information for the current cart state.
	 *
	 * Determines if a product in the cart qualifies for a BOGO offer or if a
	 * better tier is available (next tier). Calculates free quantities if applicable.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param object   $product  The product object.
	 * @param int|null $quantity The current quantity in cart.
	 *
	 * @return array Meta data array containing 'is_bogo', 'bogo' details, or 'next_tier' info.
	 */
	public static function get_bogo_meta( $product, $quantity = null ) {

		$bogo_tiers   = self::get_bogo_tiers( $product );
		$meta         = array(
			'is_bogo' => false,
		);
		$current_tier = null;
		$next_tier    = null;
		foreach ( $bogo_tiers as $key => $tier ) {
			if ( $tier['buy_quantity'] <= $quantity ) {
				$current_tier = $tier;
				break;
			}
		}
		if ( null === $current_tier ) {
			foreach ( $bogo_tiers as $key => $tier ) {
				if ( null === $next_tier || $next_tier['buy_quantity'] > $tier['buy_quantity'] ) {
					$next_tier = $tier;
				}
			}
			if ( null !== $next_tier ) {
				$meta['on_discount']       = true;
				$meta['is_bogo']           = false;
				$meta['bogo']['next_tier'] = $next_tier;
			}
		} else {
			$meta['is_bogo'] = true;
			$free_quantity   = intval( ( intval( $quantity / $current_tier['buy_quantity'] ) ) * $current_tier['get_quantity'] );
			$meta['bogo']    = array(
				'campaign'        => $current_tier['campaign'],
				'campaign_title'  => $current_tier['campaign_title'],
				'settings'        => $current_tier['settings'],
				'buy_quantity'    => $current_tier['buy_quantity'],
				'get_quantity'    => $current_tier['get_quantity'],
				'discount'        => 100,
				'discount_type'   => 'percentage',
				'free_product_id' => $product->get_id(),
				'free_quantity'   => $free_quantity,
			);
		}

		return $meta;
	}

	/**
	 * Generates the cart message for a BOGO offer.
	 *
	 * Formats a message to be displayed in the cart, typically notifying the user
	 * about the applied Buy One Get One deal.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param array $data Array containing campaign data like 'settings', 'campaign_title', 'parent_name'.
	 *
	 * @return string The formatted BOGO cart message.
	 */
	public static function get_bogo_cart_message( $data ) {
		$default_message_format = self::get_settings( 'cart_bogo_message_format' );
		$message_format         = wpab_campaignbay_get_value( $data, 'settings.cart_bogo_message_format', $default_message_format );

		return self::generate_message(
			$message_format,
			array(
				'{title}'            => $data['campaign_title'],
				'{buy_product_name}' => $data['parent_name'],
			)
		);
	}

	/**
	 * Compares two prices to determine which is better based on settings.
	 *
	 * Uses the 'product_priorityMethod' setting (lowest, highest, or first) to
	 * decide if $new_price is preferable to $current_best_price.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param float|null $current_best_price The existing best price found so far.
	 * @param float|null $new_price          The new price candidate to compare.
	 *
	 * @return bool True if $new_price is considered better than $current_best_price.
	 */
	public static function is_better_price( $current_best_price, $new_price ) {
		if ( null === $new_price ) {
			return false;
		}
		if ( null === $current_best_price ) {
			return true;
		}
		$setting = Common::get_instance()->get_settings( 'product_priorityMethod' );
		if ( 'apply_highest' === $setting ) {
			return $new_price < $current_best_price;
		} elseif ( 'apply_lowest' === $setting ) {
			return $new_price > $current_best_price;
		} elseif ( 'apply_first' === $setting ) {
			return null === $current_best_price;
		}
		return false;
	}

	/**
	 * Calculates the discounted price for a specific quantity tier.
	 *
	 * Based on the tier's discount type (percentage or fixed), calculates
	 * the new price for the given base price.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param float      $base_price The product's original price.
	 * @param array|null $tier       The tier definition containing 'type' and 'value'.
	 *
	 * @return float|null The calculated price, or null if inputs are invalid.
	 */
	public static function get_quantity_price( $base_price, $tier ) {
		if ( null === $base_price || null === $tier || empty( $tier ) ) {
			return null;
		}
		$price = 'percentage' === $tier['type'] ? self::calculate_percentage_price( $base_price, $tier['value'] ) : self::calculate_fixed_price( $base_price, $tier['value'] );
		return max( 0, $price );
	}

	/**
	 * Calculates a price based on a fixed amount discount.
	 *
	 * Subtracts a fixed value from the base price.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param float $base_price     The price before discount.
	 * @param float $discount_value The fixed amount to subtract.
	 *
	 * @return float The final price, ensuring it's not below zero.
	 */
	public static function calculate_fixed_price( $base_price, $discount_value ) {
		return max( 0, $base_price - $discount_value );
	}

	/**
	 * Calculates a price based on a percentage discount.
	 *
	 * Subtracts a percentage from the base price.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param float $base_price     The price before discount.
	 * @param float $discount_value The percentage to subtract (e.g., 10 for 10%).
	 *
	 * @return float The final price, ensuring it's not below zero.
	 */
	public static function calculate_percentage_price( $base_price, $discount_value ) {
		$discount_amount = ( $discount_value / 100 ) * $base_price;
		return max( 0, $base_price - $discount_amount );
	}

	/**
	 * Generates an HTML table displaying quantity discount tiers.
	 *
	 * Renders a table with columns for Title, Range, and Discount based on the
	 * enabled fields in global settings.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param array $tiers Array of discount tier data to display.
	 *
	 * @return void Outputs the HTML directly if $echo is true (internal logic uses get_clean_html with echo=true).
	 */
	public static function generate_quantity_table( $tiers ) {
		$settings = Common::get_instance()->get_settings( 'discount_table_options' );
		$table    = '<table class="campaignbay-discount-table"> ';
		// show table header
		if (
			true === $settings['show_header'] && (
				true === $settings['title']['show'] ||
				true === $settings['range']['show'] ||
				true === $settings['discount']['show']
			)
		) {
			$table .= '<thead><tr>';
			if ( true === $settings['title']['show'] ) {
				$table .= '<th>' . ( $settings['title']['label'] ?? 'Title' ) . '</th>';
			}
			if ( true === $settings['range']['show'] ) {
				$table .= '<th>' . ( $settings['range']['label'] ?? 'Range' ) . '</th>';
			}
			if ( true === $settings['discount']['show'] ) {
				$table .= '<th>' . ( $settings['discount']['label'] ?? 'Discount' ) . '</th>';
			}
			$table .= '</tr></thead>';
		}

		$table .= '<tbody>';
		foreach ( $tiers as $tier ) {
			$table .= '<tr>';
			if ( true === $settings['title']['show'] ) {
				$table .= '<td>' . $tier['campaign_title'] . '</td>';
			}
			if ( true === $settings['range']['show'] ) {
				$table .= '<td>' . $tier['min'] . ' - ' . $tier['max'] . '</td>';
			}
			if ( true === $settings['discount']['show'] && 'price' === $settings['discount']['content'] ) {
				$table .= '<td>' . Woocommerce::format_price( $tier['price'] ) . '</td>';
			} else {
				$table .= '<td>' . ( 'currency' === $tier['type'] ? Woocommerce::format_price( $tier['value'] ) : $tier['value'] );
				$table .= 'percentage' === $tier['type'] ? '%' : ' /PCS';
				$table .= '</td>';
			}

			$table .= '</tr>';
		}
		$table .= '</tbody></table>';
		self::get_clean_html( $table, true );
	}

	/**
	 * Determines the current active tier for an earlybird campaign.
	 *
	 * Checks the campaign's total usage count against the defined tiers to find
	 * which tier is currently active (i.e., has remaining quantity).
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param object $campaign The earlybird campaign object.
	 *
	 * @return array|null The current active tier array, or null if all tiers are exhausted.
	 */
	public static function earlybird_current_tier( $campaign ) {
		$usage_count  = $campaign->get_usage_count();
		$current_tier = null;
		$tiers        = $campaign->get_tiers();
		foreach ( $tiers as $tier ) {

			if ( $usage_count >= $tier['quantity'] ) {
				$usage_count -= $tier['quantity'];
			} else {
				return $tier;
			}
		}
		return $current_tier;
	}

	/**
	 * Prepares cart data for session storage.
	 *
	 * Extracts relevant cart item data and removes the full `WC_Product` data object
	 * to keep the session size manageable.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param object $cart The WooCommerce cart object.
	 *
	 * @return array Associative array of cart items stripped of heavy objects.
	 */
	public static function get_cart_for_session( $cart ) {
		$cart_session = array();

		foreach ( $cart->cart_contents as $key => $values ) {
			$cart_session[ $key ] = $values;
			unset( $cart_session[ $key ]['data'] ); // Unset product object.
		}
		return $cart_session;
	}


	/**
	 * Calculates a discounted price.
	 *
	 * Route calculation to specific methods based on 'fixed' or 'percentage' type.
	 * Returns base price unmodified if type is unknown.
	 *
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 * @param float  $base_price    The original price.
	 * @param float  $discount_value The amount or percentage to deduct.
	 * @param string $discount_type  Type of discount: 'fixed' or 'percentage'.
	 *
	 * @return float The calculated final price.
	 */
	public static function calculate_price( $base_price, $discount_value, $discount_type ) {
		if ( 'fixed' === $discount_type ) {
			return self::calculate_fixed_price( $base_price, $discount_value );
		} elseif ( 'percentage' === $discount_type ) {
			return self::calculate_percentage_price( $base_price, $discount_value );
		}
		return $base_price;
	}

	/**
	 * Prepares and adds discount data to the cart object to be applied as a virtual coupon.
	 *
	 * This helper function organizes discount data into a structured format within the
	 * `$cart->campaignbay['coupon']` array, grouping discounts by type and campaign.
	 *
	 * Moved from WpabCampaignBay\Engine\CartDiscount
	 *
	 * @since 1.0.8
	 * @param \WC_Cart $cart The main WooCommerce cart object.
	 * @param array    $data The discount data to add.
	 */
	public static function add_data( $cart, $data = array() ) {
		if ( 'percent' === $data['type'] ) {
			$code = 'campaignbay_' . $data['campaign'] . '_' . $data['discount'];

			if ( ! isset( $cart->campaignbay['coupon'][ $code ] ) ) {
				$cart->campaignbay['coupon'][ $code ] = array(
					'campaign'       => $data['campaign'],
					'old_price'      => 0,
					'campaign_title' => $data['campaign_title'],
					'type'           => $data['type'],
					'product_ids'    => array(),
					'discount'       => $data['discount'],
				);
			}
			if ( is_array( $data['product_id'] ) ) {
				$cart->campaignbay['coupon'][ $code ]['product_ids'] = array_merge( $cart->campaignbay['coupon'][ $code ]['product_ids'], $data['product_id'] );
			} else {
				$cart->campaignbay['coupon'][ $code ]['product_ids'][] = $data['product_id'];
			}
			$cart->campaignbay['coupon'][ $code ]['old_price'] = $cart->campaignbay['coupon'][ $code ]['old_price'] + $data['old_price'];
		} else {
			if ( is_array( $data['product_id'] ) ) {
				$product_id_str = implode( '_', $data['product_id'] );
				$code           = 'campaignbay_' . $data['campaign'] . '_' . $product_id_str;
			} else {
				$code = 'campaignbay_' . $data['campaign'] . '_' . $data['product_id'];
			}
			$cart->campaignbay['coupon'][ $code ] = array(
				'campaign'       => $data['campaign'],
				'old_price'      => $data['old_price'],
				'campaign_title' => $data['campaign_title'],
				'type'           => $data['type'],
				'discount'       => $data['discount'],
				'product_ids'    => $data['product_id'],
			);
		}
	}


	/**
	 * Converts a timezone-aware datetime string to UTC.
	 *
	 * @since 1.1.1
	 * @access public
	 * @static
	 * @param string $date_time The datetime string to convert.
	 * @return string|null The UTC datetime string.
	 */
	public static function get_utc_time( $date_time ) {

		try {
			$date = new DateTime( $date_time, new DateTimeZone( wp_timezone_string() ) );
			$date->setTimezone( new DateTimeZone( 'UTC' ) );
			return $date->format( 'Y-m-d H:i:s' );
		} catch ( Exception $e ) {
			wpab_campaignbay_log( 'Invalid date_time format . ( Helper::get_utc_time )', 'ERROR' );
			return null;
		}
	}
}
