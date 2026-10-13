<?php
/**
 * The WooCommerce helper class.
 *
 * This file is responsible for defining the Woocommerce class, which acts as a
 * centralized wrapper for interacting with WooCommerce functions and data. It provides
 * a consistent and simplified API for the rest of the plugin to use.
 *
 * @link       https://campaignbay.github.io
 * @since      1.0.0
 *
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBay/Helper
 */

namespace WpabCampaignBay\Helper;

use WC_Order;
use WC_Order_Refund;
use WC_Product;
use WP_Post;
use WpabCampaignBay\Core\Common;
use WpabCampaignBay\Helper\Helper;

if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

/**
 * Woocommerce Class.
 *
 * This helper class provides a set of static methods to abstract and simplify
 * common interactions with the WooCommerce plugin. It serves as a central utility
 * for retrieving product data, getting order information, formatting prices, and
 * handling other WooCommerce-specific tasks in a consistent manner throughout the plugin.
 *
 * @since      1.0.0
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBay/Helper
 * @author     WP Anchor Bay <wpanchorbay@gmail.com>
 */
class Woocommerce
{
    /**
     * Check product type is found in product
     * @param $product - Woocommerce product object
     * @param $type - product types 
     * @return bool
     */
    static $product_taxonomy_terms = array();
    static $custom_taxonomies;
    static $checkout_post = null;

    protected static $products = array();
    protected static $product_variations = array();

    static function product_type_is($product, $type)
    {
        if (!empty($product))
            if (is_object($product) && method_exists($product, 'is_type')) {
                return $product->is_type($type);
            }
        return false;
    }

    /**
     * Get price HTML.
     *
     * This method retrieves the HTML for the price of a product.
     * 
     * @since 1.0.0
     *
     * @param string $price_html The HTML for the price of the product.
     * @param object $product The product object.
     * @param float $original_price The original price of the product.
     * @param float $discount_price The discount price of the product.
     * @param bool $display_as_regular_price Whether to display the price as the regular price.
     * @return string The HTML for the price of the product.
     */
    static function get_price_html($price_html, $product, $original_price, $discount_price, $display_as_regular_price = false)
    {
        if ($display_as_regular_price || $original_price <= $discount_price) {
            return self::format_price($discount_price);
        }
        $new_price_html = self::format_sale_price(self::format_price($original_price), self::format_price($discount_price));
        if ($new_price_html)
            return $new_price_html;
        return $price_html;
    }

    /**
     * Generate product banner.
     *
     * This method generates the HTML for a product banner.
     *
     * @since 1.0.0
     *
     * @param float $value The value of the product banner.
     * @param string $type The type of the product banner.
     * @param string $format The format of the product banner.
     * @return string The HTML for the product banner.
     */
    static function generate_product_banner($value, $type = '', $format = null)
    {
        if ($value === null || $value === '' || $type === null || $type === '')
            return null;
        if ($format === null || $format === '') {
            $format = Common::get_instance()->get_settings(
                $type == 'percentage' ? 'product_message_format_percentage' : 'product_message_format_fixed'
            );
        }
        return Helper::generate_message($format, array(
            '{percentage_off}' => $value,
            '{amount_off}' => $value . self::get_currency_symbol(),
        ));
    }

    /**
     * Add notice
     *  
     * This method adds a notice to the WooCommerce cart.
     * 
     * @since 1.0.0
     *
     * @param string $message The message to add.
     * @param string $type The type of the notice.
     * @param array $data The data to pass to the notice.
     */
    public static function wc_add_notice($message, $type = 'success', $data = array())
    {
        if (function_exists('wc_add_notice')) {
            wc_add_notice($message, $type, $data);
        }
    }

    static function round($value)
    {
        if (function_exists('wc_get_price_decimals')) {
            return round($value, wc_get_price_decimals());
        } else {
            return round($value, get_option('woocommerce_price_num_decimals', 2));
        }
    }
    /**
     * Format the sale price
     * 
     * This method formats the sale price of a product.
     * 
     * @since 1.0.0
     * 
     * @param $price1
     * @param $price2
     * @return string|null
     */
    static function format_sale_price($price1, $price2)
    {
        if (function_exists('wc_format_sale_price')) {

            /**
             * Filters the HTML output for a sale price display (strikethrough price).
             *
             * This hook allows developers to completely override the final HTML string for a
             * product's sale price as formatted by the plugin. It passes the original regular
             * and sale prices, allowing for custom formatting, different separators, or the
             * addition of extra HTML elements.
             *
             * @since 1.0.0
             * @hook  campaignbay_format_sale_price
             *
             * @param string      $html_price    The default sale price HTML (e.g., '<del>...</del><ins>...</ins>').
             * @param string|float $regular_price The product's regular price.
             * @param string|float $sale_price    The product's sale price.
             *
             * @return string The modified sale price HTML.
             */
            return apply_filters('campaignbay_format_sale_price', wc_format_sale_price($price1, $price2), $price1, $price2);
        }
        return NULL;
    }

    /**
     * Get variation prices for variable product
     * 
     * This method retrieves the variation prices for a variable product.
     * 
     * @since 1.0.0
     * 
     * @param \WC_Product_Variable $product The variable product object.
     * @param bool $for_display Whether to return the prices for display.
     * @return array|false The variation prices for the variable product.
     */
    static function get_variation_prices($product, $for_display = false)
    {
        if (is_object($product) && method_exists($product, 'get_variation_prices')) {
            return $product->get_variation_prices($for_display);
        }
        return false;
    }

    /**
     * format the price
     * 
     * This method formats the price of a product.
     * 
     * @since 1.0.0
     * 
     * @param $price The price of the product.
     * @param $args The arguments for formatting the price.
     * @return string The formatted price.
     */
    static function format_price($price, $args = array())
    {
        if (function_exists('wc_price')) {
            return wc_price($price, $args);
        }
        return $price;
    }

    /**
     * format currency code
     * 
     * This method retrieves the currency symbol for a given currency code.
     * 
     * @since 1.0.0
     * 
     * @param string $code The currency code.
     * @return string The currency symbol.
     */
    static function get_currency_symbol($code = '')
    {
        if (function_exists('get_woocommerce_currency_symbol')) {
            return get_woocommerce_currency_symbol($code);
        }
        return $code;
    }
    /**
     * get the parent id of the particular product
     * 
     * This method retrieves the parent ID of a given product.
     * 
     * @since 1.0.0
     * 
     * @param $product The product object.
     * @return int The parent ID of the product.
     */
    static function get_product_parent_id($product)
    {
        $parent_id = 0;
        if (is_int($product)) {
            $product = self::get_product($product);
        }
        if (!empty($product))
            if (is_object($product) && method_exists($product, 'get_parent_id')) {
                $parent_id = $product->get_parent_id();
            }
        /**
         * Filters the parent ID of a given product.
         *
         * This hook is used primarily to identify the main variable product ID from a
         * variation object. Developers can use this filter to provide a custom parent ID,
         * which is useful for complex product types like bundles or composites that may
         * have their own unique parent/child relationships.
         *
         * @since 1.0.0
         * @hook  campaignbay_get_product_parent_id
         *
         * @param int        $parent_id The determined parent ID (0 if not a variation).
         * @param WC_Product $product   The product object being checked.
         *
         * @return int The filtered parent ID.
         */
        return apply_filters('campaignbay_get_product_parent_id', $parent_id, $product);
    }

    /**
     * print the notice
     * 
     * This method prints a notice to the WooCommerce cart.
     * 
     * @since 1.0.0
     * 
     * @param $message The message to print.
     * @param $type The type of the notice.
     */
    static function print_notice($message, $type)
    {
        if (function_exists('wc_print_notice')) {
            wc_print_notice(wp_unslash($message), $type);
        }
    }
    /**
     * get available product variations
     * 
     * This method retrieves the available product variations for a given product.
     * 
     * @since 1.0.0
     * 
     * @param $product The product object.
     * @return array The available product variations.
     */
    public static function available_product_variations($product)
    {
        $product_id = self::get_product_id($product);
        if (isset(self::$product_variations[$product_id])) {
            return self::$product_variations[$product_id];
        }
        $available_variations = array();
        $is_variable_product = self::product_type_is($product, 'variable');
        if (!empty($product))
            if ($is_variable_product && is_object($product) && method_exists($product, 'get_available_variations')) {
                $available_variations = $product->get_available_variations();
            }
        self::$product_variations[$product_id] = $available_variations;
        return $available_variations;
    }


    /**
     * get the product ID
     * 
     * This method retrieves the ID of a given product.
     * 
     * @since 1.0.0
     * 
     * @param $product The product object.
     * @return null The ID of the product.
     */
    static function get_product_id($product)
    {
        if (!empty($product)) {
            if (is_object($product) && method_exists($product, 'get_id')) {
                return $product->get_id();
            } elseif (isset($product->id)) {
                $product_id = $product->id;
                if (isset($product->variation_id)) {
                    $product_id = $product->variation_id;
                }
                return $product_id;
            } else {
                return NULL;
            }
        }
        return NULL;
    }
    /**
     * get the product ID or parent ID if variation
     * 
     * This method retrieves the ID of a given product or its parent ID if it is a variation.
     * 
     * @since 1.0.0
     * 
     * @param $product The product object.
     * @return null The ID of the product or its parent ID if it is a variation.
     */
    static function get_product_or_parent_id($product)
    {
        if (self::product_type_is($product, 'variation')) {
            return self::get_product_parent_id($product);
        }
        return self::get_product_id($product);
    }



    /**
     * Get the product from product id
     * 
     * This method retrieves the product object from a given product ID.
     * 
     * @since 1.0.0
     * 
     * @param $product_id The ID of the product.
     * @return bool|false|WC_Product|null The product object.
     */
    static function get_product($product_id)
    {
        if (isset(self::$products[$product_id])) {
            return self::$products[$product_id];
        } else if (function_exists('wc_get_product')) {
            /**
             * Filters the WooCommerce product object retrieved by the plugin.
             *
             * This hook is applied after the plugin fetches a product object using `wc_get_product()`.
             * It allows developers to intercept and modify the product object before it is used
             * in any of the plugin's internal calculations or caching mechanisms. This can be used
             * for advanced scenarios, such as substituting a different product object for testing
             * or complex bundling logic.
             *
             * @since 1.0.0
             * @hook  campaignbay_get_product
             *
             * @param WC_Product|false $product_object The retrieved product object, or false if not found.
             * @param int              $product_id     The ID of the product that was requested.
             *
             * @return WC_Product|false The filtered product object.
             */
            self::$products[$product_id] = apply_filters('campaignbay_get_product', wc_get_product($product_id), $product_id);

            return self::$products[$product_id];
        }
        return false;
    }

    /**
     * Get the product from product data
     * 
     * This method retrieves the product object from a given product data.
     * 
     * @since 1.1.17
     * 
     * @param $product The product data.
     * @return bool|false|WC_Product|null The product object.
     */
    static function get_product_from_data($product)
    {

        /**
         * Filters the WooCommerce product object retrieved by the plugin.
         *
         * This hook is applied after the plugin fetches a product object using `wc_get_product()`.
         * It allows developers to intercept and modify the product object before it is used
         * in any of the plugin's internal calculations or caching mechanisms. This can be used
         * for advanced scenarios, such as substituting a different product object for testing
         * or complex bundling logic.
         *
         * @since 1.0.0
         * @hook  campaignbay_get_product
         *
         * @param WC_Product|false $product_object The retrieved product object, or false if not found.
         * @param int              $product_id     The ID of the product that was requested.
         *
         * @return WC_Product|false The filtered product object.
         */
        return apply_filters('campaignbay_get_product', $product, $product->get_id());

    }


    /**
     * Get the sale price of the product
     * 
     * This method retrieves the sale price of a given product.
     * 
     * @since 1.0.0
     * 
     * @param $product The product object.
     * @return bool The sale price of the product.
     */
    static function get_product_sale_price($product)
    {
        if (!empty($product))
            if (self::is_product_in_sale($product)) {
                if (is_object($product) && method_exists($product, 'get_sale_price')) {
                    $price = $product->get_sale_price();
                    return $price;
                }
                return false;
            }
        return false;
    }

    /**
     * Check the produt in sale
     * 
     * This method checks if a given product is on sale.
     * 
     * @since 1.0.0
     * 
     * @param $product The product object.
     * @return bool Whether the product is on sale.
     */
    static function is_product_in_sale($product)
    {
        if (!empty($product))
            if (is_object($product) && method_exists($product, 'is_on_sale') && method_exists($product, 'get_sale_price')) {
                if ($product->is_on_sale('')) {
                    if ($product->get_sale_price()) {
                        return true;
                    } else {
                        return false;
                    }
                }
            }
        return false;
    }

    /**
     * Get the regular price of the product
     * 
     * This method retrieves the regular price of a given product.
     * 
     * @since 1.0.0
     * 
     * @param $product The product object.
     * @return bool The regular price of the product.
     */
    static function get_product_regular_price($product)
    {
        if (!empty($product))
            if (is_object($product) && method_exists($product, 'get_regular_price')) {
                $price = $product->get_regular_price();
                return $price;
            }
        return false;
    }

    /**
     * Get the base price of the product
     * 
     * This method retrieves the base price of a given product.
     * 
     * @since 1.0.0
     * 
     * @param $product The product object.
     * @return bool The base price of the product.
     */
    static function get_product_base_price($product)
    {


        $price = null;
        if (empty($product))
            return $price;
        if (self::product_type_is($product, 'variable')) {
            $children = self::get_product_children($product);
            foreach ($children as $child) {
                $child_product = wc_get_product($child);
                $price = self::get_product_base_price($child_product);
                return $price;
            }
        }
        $settings = Common::get_instance()->get_settings('global_calculate_discount_from');
        if ($settings == 'sale_price' && self::is_product_in_sale($product)) {
            $price = self::get_product_sale_price($product);
            if ($price !== false) {
                return $price;
            }
        }
        $price = self::get_product_regular_price($product);
        if ($price === false) {
            $price = self::get_product_sale_price($product);
        }
        return $price;
    }




    /**
     * Generate the cart item data hash
     * 
     * This method generates the hash of the cart item data.
     * 
     * @since 1.0.5
     * 
     * @param $product_data The product data.
     * @return string The hash of the cart item data.
     */
    static function generate_cart_item_data_hash($product_data)
    {
        if (function_exists('wc_get_cart_item_data_hash')) {
            return wc_get_cart_item_data_hash($product_data);
        }
        return null;
    }

    /**
     * Get the categories of the product
     * 
     * @since 1.0.5
     * 
     * @param $product
     * @return array
     */
    static function get_product_categories($product)
    {
        $categories = $variant = array();
        if (!empty($product))
            if (is_object($product) && method_exists($product, 'get_category_ids')) {
                if (self::product_type_is($product, 'variation')) {
                    $variant = $product;
                    $parent_id = self::get_product_parent_id($product);
                    // Use the raw WC lookup here, not self::get_product(): that goes through the
                    // campaignbay_get_product filter, which for variable products re-enters this
                    // same code path while the parent's cache slot is still unresolved, causing
                    // unbounded recursion.
                    $product = wc_get_product($parent_id);
                }
                $categories = $product->get_category_ids();
            }
        return $categories;
    }

    /**
     * Get the tag IDs of the product (variations use their parent's tags)
     *
     * @since 1.1.18
     *
     * @param WC_Product $product the product object
     * @return array the product_tag term IDs
     */
    static function get_product_tags($product)
    {
        $tags = array();
        if (!empty($product) && is_object($product) && method_exists($product, 'get_tag_ids')) {
            if (self::product_type_is($product, 'variation')) {
                // Raw WC lookup on purpose, see get_product_categories() (avoids filter recursion).
                $parent_id = (int) self::get_product_parent_id($product);
                if (!$parent_id && method_exists($product, 'get_parent_id')) {
                    $parent_id = (int) $product->get_parent_id();
                }
                // A failed parent lookup yields no tags, so a tag target (and its exclude mode) never grants the discount.
                $product = $parent_id ? wc_get_product($parent_id) : null;
            }
            if ($product && method_exists($product, 'get_tag_ids')) {
                $tags = $product->get_tag_ids();
            }
        }
        return $tags;
    }

    /**
     * Get the children of a product
     * 
     * @since 1.0.7
     * 
     * @param WC_Product $product the product object
     * @return array WC_Product[] the children of the product
     */
    static function get_product_children($product)
    {
        if (!empty($product)) {
            if (is_object($product) && method_exists($product, 'get_children')) {
                return $product->get_children();
            }
        }
        return array();
    }

    /**
     * Get the product id from the cart item
     * 
     * This method retrieves the product id from the cart item.
     * 
     * @since 1.0.8
     * 
     * @param mixed $cart_item
     * @return int|null the product id
     */
    public static function get_product_id_from_cart_item($cart_item)
    {
        $product_id = null;

        if (isset($cart_item['product_id'])) {
            $product_id = $cart_item['product_id'];
            if (isset($cart_item['variation_id']) && !empty($cart_item['variation_id'])) {
                $product_id = $cart_item['variation_id'];
            }
        } else if (isset($cart_item['data'])) {
            $product_id = self::get_product_id($cart_item['data']);
        }

        return $product_id;
    }
}