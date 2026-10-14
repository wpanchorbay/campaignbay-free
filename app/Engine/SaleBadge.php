<?php
/**
 * File.
 *
 * @package campaignbay
 */

namespace WpabCampaignBay\Engine;

use WpabCampaignBay\Core\Base;
use WpabCampaignBay\Core\Common;
use WpabCampaignBay\Helper\Helper;
use WpabCampaignBay\Helper\Woocommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The SaleBadge class (storefront display feature).
 *
 * @since 1.1.19
 */
class SaleBadge extends Base {

	/**
	 * Per-request cache of computed discount figures, keyed by product ID.
	 *
	 * @var array
	 */
	private $figures_cache = array();

	/**
	 * Per-request cache of rendered badge text, keyed by product ID.
	 *
	 * @var array
	 */
	private $text_cache = array();

	/**
	 * Constructor to define and build the hooks array.
	 *
	 * @since 1.1.19
	 */
	protected function __construct() {
		parent::__construct();
		$this->define_hooks();
	}

	/**
	 * Defines all hooks this class needs to run.
	 *
	 * @since 1.1.19
	 * @access private
	 * @return void
	 */
	private function define_hooks() {
		$hooks = array(
			array( 'filter', 'woocommerce_sale_flash', 'filter_sale_flash', 20, 3 ),
			array( 'filter', 'render_block_woocommerce/product-sale-badge', 'filter_sale_badge_block', 20, 3 ),
			array( 'action', 'wp_enqueue_scripts', 'enqueue_styles', 20, 0 ),
		);
		foreach ( $hooks as $hook ) {
			$this->add_hook( ...$hook );
		}
	}

	/**
	 * Whether the badge feature is switched on.
	 *
	 * @since 1.1.19
	 * @return bool
	 */
	private function is_enabled() {
		return (bool) Common::get_instance()->get_settings( 'badge_enabled' );
	}

	/**
	 * Adds a tiny stylesheet for the badge to the plugin's public style handle.
	 *
	 * @since 1.1.19
	 * @return void
	 */
	public function enqueue_styles() {
		if ( ! $this->is_enabled() ) {
			return;
		}
		$css = '.campaignbay-sale-badge{line-height:1.4;}'
			. '.wc-block-components-product-sale-badge .campaignbay-sale-badge{display:inline-block;padding:.25em .75em;border-radius:3px;font-size:.875em;font-weight:600;}';
		wp_add_inline_style( CAMPAIGNBAY_OPTION_NAME . 'public', $css );
	}

	/**
	 * Replaces WooCommerce's sale flash with the CampaignBay badge for CampaignBay products.
	 *
	 * @since 1.1.19
	 * @param string           $html    Existing badge HTML.
	 * @param mixed            $post    Post object (unused).
	 * @param \WC_Product|null $product The product.
	 * @return string
	 */
	public function filter_sale_flash( $html, $post = null, $product = null ) {
		if ( ! $this->is_enabled() ) {
			return $html;
		}
		if ( false !== strpos( (string) $html, 'campaignbay-sale-badge' ) ) {
			return $html;
		}
		if ( ! $product instanceof \WC_Product ) {
			$product = isset( $GLOBALS['product'] ) ? $GLOBALS['product'] : null;
		}
		if ( ! $product instanceof \WC_Product ) {
			return $html;
		}
		$badge = $this->get_badge_html( $product, true );
		return '' === $badge ? $html : $badge;
	}

	/**
	 * Replaces the text inside the block-based product sale badge.
	 *
	 * @since 1.1.19
	 * @param string         $content Rendered block HTML.
	 * @param array          $parsed_block Parsed block (unused).
	 * @param \WP_Block|null $block Block instance.
	 * @return string
	 */
	public function filter_sale_badge_block( $content, $parsed_block = array(), $block = null ) {
		if ( '' === trim( (string) $content ) || ! $this->is_enabled() || false !== strpos( $content, 'campaignbay-sale-badge' ) ) {
			return $content;
		}
		$post_id = ( $block instanceof \WP_Block && ! empty( $block->context['postId'] ) ) ? (int) $block->context['postId'] : (int) get_the_ID();
		$product = $post_id ? wc_get_product( $post_id ) : null;
		if ( ! $product instanceof \WC_Product ) {
			return $content;
		}
		$badge = $this->get_badge_html( $product, false );
		if ( '' === $badge ) {
			return $content;
		}
		$replaced = preg_replace_callback(
			'#(<div[^>]*wc-block-components-product-sale-badge[^>]*>).*?(</div>)#s',
			function ( $m ) use ( $badge ) {
				return $m[1] . $badge . $m[2];
			},
			$content,
			1,
			$count
		);
		return ( $count && null !== $replaced ) ? $replaced : $content;
	}

	/**
	 * Builds the badge HTML for a product, or an empty string if it is not a CampaignBay product.
	 *
	 * @since 1.1.19
	 * @param \WC_Product $product    The product.
	 * @param bool        $with_onsale Whether to include the theme-facing "onsale" class.
	 * @return string
	 */
	public function get_badge_html( $product, $with_onsale = true ) {
		$figures = $this->get_figures( $product );
		if ( null === $figures ) {
			return '';
		}
		$pid = $product->get_id();
		if ( ! isset( $this->text_cache[ $pid ] ) ) {
			$template                 = (string) Common::get_instance()->get_settings( 'badge_text' );
			$pct                      = round( $figures['percentage'], 2 );
			$amount                   = $figures['amount'] > 0 ? wp_strip_all_tags( wc_price( $figures['amount'] ) ) : '';
			$this->text_cache[ $pid ] = str_replace(
				array( '{percentage_off}', '{amount_off}' ),
				array( rtrim( rtrim( number_format( $pct, 2, '.', '' ), '0' ), '.' ), html_entity_decode( $amount, ENT_QUOTES, 'UTF-8' ) ),
				$template
			);
		}
		$text = $this->text_cache[ $pid ];
		$bg   = (string) Common::get_instance()->get_settings( 'badge_bg_color' );
		$fg   = (string) Common::get_instance()->get_settings( 'badge_text_color' );
		$bg   = sanitize_hex_color( $bg ) ? $bg : '#e2401c';
		$fg   = sanitize_hex_color( $fg ) ? $fg : '#ffffff';

		$html = sprintf(
			'<span class="%1$s" style="background-color:%2$s;color:%3$s;">%4$s</span>',
			esc_attr( $with_onsale ? 'onsale campaignbay-sale-badge' : 'campaignbay-sale-badge' ),
			esc_attr( $bg ),
			esc_attr( $fg ),
			esc_html( $text )
		);

		/**
		 * Filters the CampaignBay sale badge HTML.
		 *
		 * @since 1.1.19
		 * @param string      $html    Badge HTML.
		 * @param \WC_Product $product The product.
		 * @param array       $figures Array with 'percentage' and 'amount' keys.
		 */
		return (string) apply_filters( 'campaignbay_sale_badge_html', $html, $product, $figures );
	}

	/**
	 * Computes the best percentage / amount off for a product, or null if no CampaignBay discount applies.
	 *
	 * @since 1.1.19
	 * @param \WC_Product $product The product.
	 * @return array|null Array with 'percentage' and 'amount', or null.
	 */
	private function get_figures( $product ) {
		// Quantity-tier-only products work too: PricingEngine::is_on_sale() returns true for them, so
		// themes call woocommerce_sale_flash, and the tier pairs below produce the figures.
		$pid = $product->get_id();
		if ( array_key_exists( $pid, $this->figures_cache ) ) {
			return $this->figures_cache[ $pid ];
		}
		$result = null;
		$meta   = Woocommerce::get_product( $pid )->get_meta( 'campaignbay' );
		$meta   = is_array( $meta ) ? $meta : array();
		$pairs  = array(); // Each: array( base, discounted ).
		$bases  = array();

		if ( isset( $meta['base_price'] ) ) {
			$bases = is_array( $meta['base_price'] ) ? $meta['base_price'] : array( $pid => $meta['base_price'] );
		}
		if ( ! empty( $meta['is_on_sale'] ) && ! empty( $meta['is_simple'] ) && isset( $meta['simple'] ) && is_array( $meta['simple'] ) ) {
			if ( isset( $meta['simple']['price'] ) ) {
				$pairs[] = array( (float) ( $bases[ $pid ] ?? 0 ), (float) $meta['simple']['price'] );
			} else {
				foreach ( $meta['simple'] as $vid => $simple ) {
					if ( is_array( $simple ) && isset( $simple['price'], $bases[ $vid ] ) ) {
						$pairs[] = array( (float) $bases[ $vid ], (float) $simple['price'] );
					}
				}
			}
		}

		$tiers = Helper::get_quantity_tiers_with_campaign( $product );
		if ( ! empty( $tiers ) ) {
			foreach ( $bases as $base ) {
				foreach ( $tiers as $tier ) {
					$price = Helper::get_quantity_price( (float) $base, $tier );
					if ( null !== $price ) {
						// Per-line fixed tiers take the value off once, so it is shown as is.
						$pairs[] = array( (float) $base, (float) $price, Helper::is_per_line_tier( $tier ) ? min( (float) $tier['value'], (float) $base * (int) $tier['min'] ) : null );
					}
				}
			}
		}

		if ( ! empty( $pairs ) || ! empty( $tiers ) || ! empty( $meta['is_on_sale'] ) ) {
			$best_pct = 0.0;
			$best_amt = 0.0;
			foreach ( $pairs as $pair ) {
				if ( $pair[0] <= 0 || $pair[1] >= $pair[0] ) {
					continue;
				}
				$best_pct = max( $best_pct, ( $pair[0] - $pair[1] ) / $pair[0] * 100 );
				$best_amt = max( $best_amt, $pair[2] ?? ( $pair[0] - $pair[1] ) );
			}
			// Tiers may exist without any real discount for this product: keep the default flash then.
			if ( $best_pct > 0 || $best_amt > 0 ) {
				$result = array(
					'percentage' => $best_pct,
					'amount'     => $best_amt,
				);
			}
		}
		$this->figures_cache[ $pid ] = $result;
		return $result;
	}
}
