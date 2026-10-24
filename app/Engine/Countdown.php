<?php
/**
 * File.
 *
 * @package campaignbay
 */

namespace WpabCampaignBay\Engine;

use WpabCampaignBay\Core\Base;
use WpabCampaignBay\Core\Campaign;
use WpabCampaignBay\Core\Common;
use WpabCampaignBay\Helper\Helper;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Countdown class (storefront display feature).
 *
 * Renders a live urgency timer for active campaigns that have an end date, on the
 * single product page, shop/category loop, cart (block + classic) and through the
 * [campaignbay_countdown] shortcode.
 *
 * @since 1.1.19
 */
class Countdown extends Base {

	/**
	 * Per-request cache of the chosen campaign per product.
	 *
	 * @var array<int, Campaign|null>
	 */
	private $product_cache = array();

	/**
	 * Whether the assets have been enqueued.
	 *
	 * @var bool
	 */
	private $assets_enqueued = false;

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
			array( 'action', 'init', 'register_shortcode', 10, 0 ),
			array( 'action', 'woocommerce_single_product_summary', 'render_product', 25, 0 ),
			array( 'action', 'woocommerce_after_shop_loop_item', 'render_loop', 11, 0 ),
			array( 'filter', 'woocommerce_cart_item_name', 'cart_item_name', 20, 3 ),
			array( 'action', 'wp_enqueue_scripts', 'maybe_enqueue_for_cart', 20, 0 ),
			array( 'action', 'woocommerce_blocks_loaded', 'register_store_api_data', 10, 0 ),
		);
		foreach ( $hooks as $hook ) {
			$this->add_hook( ...$hook );
		}
	}

	/**
	 * Registers the [campaignbay_countdown] shortcode.
	 *
	 * @since 1.1.19
	 * @return void
	 */
	public function register_shortcode() {
		add_shortcode( 'campaignbay_countdown', array( $this, 'shortcode' ) );
	}

	/**
	 * Shortcode handler. Usage: [campaignbay_countdown id="12" format="Hurry! {countdown}"].
	 *
	 * @since 1.1.19
	 * @param array|string $atts Shortcode attributes.
	 * @return string Markup, or an empty string when there is nothing to show.
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'     => 0,
				'format' => '',
			),
			$atts,
			'campaignbay_countdown'
		);

		$campaign = $this->get_active_campaign( absint( $atts['id'] ) );
		if ( ! $campaign || ! self::campaign_has_countdown( $campaign ) ) {
			return '';
		}
		$format = '' !== trim( (string) $atts['format'] ) ? (string) $atts['format'] : $this->get_format( 'shortcode' );
		return self::render( $campaign, $format, 'shortcode' );
	}

	/**
	 * Echoes the timer on the single product page.
	 *
	 * @since 1.1.19
	 * @return void
	 */
	public function render_product() {
		if ( ! $this->is_surface_enabled( 'product' ) ) {
			return;
		}
		global $product;
		$this->echo_for_product( $product, 'product' );
	}

	/**
	 * Echoes the timer inside a shop/category loop card.
	 *
	 * @since 1.1.19
	 * @return void
	 */
	public function render_loop() {
		if ( ! $this->is_surface_enabled( 'loop' ) ) {
			return;
		}
		global $product;
		$this->echo_for_product( $product, 'loop' );
	}

	/**
	 * Appends the timer to the cart item name (classic cart, checkout and the classic mini
	 * cart). The block cart/checkout builds item names from the product title and does not
	 * run this filter, so it is served by register_store_api_data() + countdown.js instead.
	 *
	 * @since 1.1.19
	 * @param string $name          Item name.
	 * @param array  $cart_item     Cart item.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public function cart_item_name( $name, $cart_item, $cart_item_key ) {
		unset( $cart_item_key );
		if ( is_admin() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $name;
		}
		if ( ! $this->is_surface_enabled( 'cart' ) || ! is_array( $cart_item ) || empty( $cart_item['data'] ) ) {
			return $name;
		}
		if ( ! empty( $cart_item['is_campaignbay_free_product'] ) ) {
			return $name;
		}
		$campaign = $this->get_campaign_for_product( $cart_item['data'] );
		if ( ! $campaign ) {
			return $name;
		}
		$html = self::render( $campaign, $this->get_format( 'cart' ), 'cart' );
		return '' === $html ? $name : $name . '<br/>' . $html;
	}

	/**
	 * Extends the Store API cart item schema (namespace "campaignbay") with countdown data
	 * so the block cart/checkout can show the timer. countdown.js reads it from the
	 * wc/store/cart data store.
	 *
	 * @since 1.1.19
	 * @return void
	 */
	public function register_store_api_data() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'cart-item',
				'namespace'       => 'campaignbay',
				'data_callback'   => array( $this, 'store_api_item_data' ),
				'schema_callback' => array( $this, 'store_api_item_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * Store API data callback: countdown data for a cart item.
	 *
	 * @since 1.1.19
	 * @param array $cart_item Cart item.
	 * @return array Empty keys when no timer applies.
	 */
	public function store_api_item_data( $cart_item ) {
		$empty = array(
			'end'       => 0,
			'now'       => 0,
			'campaign'  => 0,
			'key'       => '',
			'permalink' => '',
			'text'      => '',
			'expired'   => '',
		);
		if ( ! $this->is_surface_enabled( 'cart' ) || ! is_array( $cart_item ) || empty( $cart_item['data'] ) || ! empty( $cart_item['is_campaignbay_free_product'] ) ) {
			return $empty;
		}
		$campaign = $this->get_campaign_for_product( $cart_item['data'] );
		if ( ! $campaign ) {
			return $empty;
		}
		$format = $this->get_format( 'cart' );
		if ( '' === trim( $format ) ) {
			return $empty;
		}
		// Assets are enqueued on page load (maybe_enqueue_for_cart); never from a Store API response.
		$end_date = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $campaign->get_end_timestamp() );
		return array(
			'end'       => (int) $campaign->get_end_timestamp(),
			'now'       => time(),
			'campaign'  => (int) $campaign->get_id(),
			'key'       => isset( $cart_item['key'] ) ? (string) $cart_item['key'] : '',
			'permalink' => (string) $cart_item['data']->get_permalink(),
			// Plain text; the client builds the DOM with textContent. {countdown} is kept as a token.
			'text'      => str_replace(
				array( '{end_date}', '{campaign_title}' ),
				array( (string) $end_date, $campaign->get_title() ),
				$format
			),
			'expired'   => (string) Common::get_instance()->get_settings( 'countdown_expired_message' ),
		);
	}

	/**
	 * Store API schema callback.
	 *
	 * @since 1.1.19
	 * @return array
	 */
	public function store_api_item_schema() {
		$prop = function ( $type, $desc ) {
			return array(
				'description' => $desc,
				'type'        => $type,
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			);
		};
		return array(
			'end'       => $prop( 'integer', 'Countdown end (Unix timestamp).' ),
			'now'       => $prop( 'integer', 'Server time (Unix timestamp).' ),
			'campaign'  => $prop( 'integer', 'Campaign ID.' ),
			'key'       => $prop( 'string', 'Cart item key.' ),
			'permalink' => $prop( 'string', 'Product permalink.' ),
			'text'      => $prop( 'string', 'Countdown format text with a {countdown} token.' ),
			'expired'   => $prop( 'string', 'Message shown when the countdown ends.' ),
		);
	}

	/**
	 * Enqueues the assets up front when the cart holds items with a countdown, so timers
	 * injected later by the block cart / mini cart are initialised.
	 *
	 * @since 1.1.19
	 * @return void
	 */
	public function maybe_enqueue_for_cart() {
		if ( is_admin() || ! $this->is_surface_enabled( 'cart' ) ) {
			return;
		}
		// Block cart/checkout fill their items client side (even from an empty server cart), so load
		// the script whenever any active campaign could show a timer.
		if ( ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() ) ) {
			foreach ( CampaignManager::get_instance()->get_active_campaigns() as $campaign ) {
				if ( self::campaign_has_countdown( $campaign ) ) {
					self::enqueue_assets();
					return;
				}
			}
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! empty( $item['data'] ) && $this->get_campaign_for_product( $item['data'] ) ) {
				self::enqueue_assets();
				return;
			}
		}
	}

	/**
	 * Returns the campaign whose timer should be shown for a product (soonest ending).
	 *
	 * @since 1.1.19
	 * @param \WC_Product|mixed $product Product.
	 * @return Campaign|null
	 */
	public function get_campaign_for_product( $product ) {
		if ( ! $product instanceof \WC_Product ) {
			return null;
		}
		$key = $product->get_id();
		if ( array_key_exists( $key, $this->product_cache ) ) {
			return $this->product_cache[ $key ];
		}
		$best      = null;
		$best_time = null;
		foreach ( CampaignManager::get_instance()->get_active_campaigns() as $campaign ) {
			if ( ! self::campaign_has_countdown( $campaign ) ) {
				continue;
			}
			$end = (int) $campaign->get_end_timestamp();
			if ( null !== $best_time && $end >= $best_time ) {
				continue;
			}
			if ( $campaign->is_applicable_to_product( $product ) ) {
				$best      = $campaign;
				$best_time = $end;
			}
		}
		/**
		 * Filters the campaign chosen for a product's countdown.
		 *
		 * @since 1.1.19
		 * @param Campaign|null $best    Chosen campaign or null.
		 * @param \WC_Product   $product Product.
		 */
		$best                        = apply_filters( 'campaignbay_countdown_product_campaign', $best, $product );
		$this->product_cache[ $key ] = $best;
		return $best;
	}

	/**
	 * Whether a campaign can show a timer right now.
	 *
	 * @since 1.1.19
	 * @param Campaign $campaign Campaign.
	 * @return bool
	 */
	public static function campaign_has_countdown( $campaign ) {
		if ( ! $campaign instanceof Campaign || 'active' !== $campaign->get_status() ) {
			return false;
		}
		// A used-up Early Bird no longer discounts, so it must not advertise a timer.
		if ( 'earlybird' === $campaign->get_type() && null === Helper::earlybird_current_tier( $campaign ) ) {
			return false;
		}
		$settings = $campaign->get_settings();
		if ( is_array( $settings ) && isset( $settings['show_countdown'] ) && false === filter_var( $settings['show_countdown'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) ) {
			return false;
		}
		// The engine ignores dates when scheduling is off, so a stored end date is stale and there is
		// no timer (a countdown needs a real end date).
		if ( ! $campaign->get_schedule_enabled() ) {
			return false;
		}
		$end = $campaign->get_end_timestamp();
		return ! empty( $end ) && $end > time();
	}

	/**
	 * Builds the timer markup for a campaign.
	 *
	 * @since 1.1.19
	 * @param Campaign $campaign Campaign.
	 * @param string   $format   Format string with {countdown} {end_date} {campaign_title}.
	 * @param string   $surface  product|loop|cart|shortcode.
	 * @return string Escaped HTML, or empty string.
	 */
	public static function render( $campaign, $format, $surface = 'shortcode' ) {
		if ( ! self::campaign_has_countdown( $campaign ) || '' === trim( (string) $format ) ) {
			return '';
		}
		$end       = (int) $campaign->get_end_timestamp();
		$now       = time();
		$remaining = $end - $now;
		$expired   = (string) Common::get_instance()->get_settings( 'countdown_expired_message' );

		$time_html = '<span class="campaignbay-countdown__time">' . esc_html( self::format_remaining( $remaining ) ) . '</span>';
		$end_date  = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $end );
		$text      = esc_html( (string) $format );
		$text      = str_replace(
			array( '{countdown}', '{end_date}', '{campaign_title}' ),
			array( '%%CBCD_TIME%%', esc_html( (string) $end_date ), esc_html( $campaign->get_title() ) ),
			$text
		);
		$text      = str_replace( '%%CBCD_TIME%%', $time_html, $text );

		$html = sprintf(
			'<span class="campaignbay-countdown campaignbay-countdown--%1$s" data-end="%2$d" data-now="%3$d" data-campaign="%4$d" data-expired="%5$s">%6$s</span>',
			esc_attr( $surface ),
			$end,
			$now,
			(int) $campaign->get_id(),
			esc_attr( $expired ),
			$text
		);

		self::enqueue_assets();

		/**
		 * Filters the countdown markup.
		 *
		 * @since 1.1.19
		 * @param string   $html     Markup.
		 * @param Campaign $campaign Campaign.
		 * @param string   $surface  Surface.
		 */
		return apply_filters( 'campaignbay_countdown_html', $html, $campaign, $surface );
	}

	/**
	 * Formats a number of seconds like "2d 03:14:09" (days omitted when zero).
	 *
	 * @since 1.1.19
	 * @param int $seconds Seconds remaining.
	 * @return string
	 */
	public static function format_remaining( $seconds ) {
		$seconds = max( 0, (int) $seconds );
		$days    = intdiv( $seconds, DAY_IN_SECONDS );
		$hours   = intdiv( $seconds % DAY_IN_SECONDS, HOUR_IN_SECONDS );
		$minutes = intdiv( $seconds % HOUR_IN_SECONDS, MINUTE_IN_SECONDS );
		$secs    = $seconds % MINUTE_IN_SECONDS;
		$clock   = sprintf( '%02d:%02d:%02d', $hours, $minutes, $secs );
		return $days > 0 ? $days . 'd ' . $clock : $clock;
	}

	/**
	 * Enqueues countdown.js and countdown.css (once).
	 *
	 * @since 1.1.19
	 * @return void
	 */
	public static function enqueue_assets() {
		$self = self::get_instance();
		if ( $self->assets_enqueued ) {
			return;
		}
		$self->assets_enqueued = true;
		wp_enqueue_style( 'campaignbay-countdown', CAMPAIGNBAY_URL . 'assets/css/countdown.css', array(), CAMPAIGNBAY_VERSION );
		wp_enqueue_script( 'campaignbay-countdown', CAMPAIGNBAY_URL . 'assets/js/countdown.js', array(), CAMPAIGNBAY_VERSION, true );
	}

	/**
	 * Echoes the timer for a product.
	 *
	 * @param mixed  $product Product.
	 * @param string $surface Surface.
	 * @return void
	 */
	private function echo_for_product( $product, $surface ) {
		$campaign = $this->get_campaign_for_product( $product );
		if ( ! $campaign ) {
			return;
		}
		$html = self::render( $campaign, $this->get_format( $surface ), $surface );
		if ( '' !== $html ) {
			// Already escaped in render().
			echo '<div class="campaignbay-countdown-wrap">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Gets an active campaign by ID.
	 *
	 * @param int $id Campaign ID.
	 * @return Campaign|null
	 */
	private function get_active_campaign( $id ) {
		$campaigns = CampaignManager::get_instance()->get_active_campaigns();
		return $id && isset( $campaigns[ $id ] ) ? $campaigns[ $id ] : null;
	}

	/**
	 * Whether a surface is enabled in settings.
	 *
	 * @param string $surface product|loop|cart.
	 * @return bool
	 */
	private function is_surface_enabled( $surface ) {
		return (bool) Common::get_instance()->get_settings( 'countdown_show_' . $surface );
	}

	/**
	 * Gets the configured format string for a surface.
	 *
	 * @param string $surface Surface.
	 * @return string
	 */
	private function get_format( $surface ) {
		return (string) Common::get_instance()->get_settings( 'countdown_format_' . $surface );
	}
}
