<?php
/**
 * File.
 *
 * @package campaignbay
 */

namespace WpabCampaignBay\Engine;

use WpabCampaignBay\Core\Base;
use WpabCampaignBay\Core\Common;
use WpabCampaignBay\Helper\Filter;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The OfferMessages class (storefront display feature).
 *
 * @since 1.1.19
 */
class OfferMessages extends Base {

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
		// Priority 15: after WooCommerce notices (10) and before the result count (20).
		$this->add_action( 'woocommerce_before_shop_loop', 'render_archive_messages', 15, 0 );
		$this->add_action( 'woocommerce_single_product_summary', 'render_product_promos', 26, 0 );
		$this->add_action( 'init', 'register_shortcodes', 10, 0 );
	}

	/**
	 * Registers the shortcodes.
	 *
	 * @since 1.1.19
	 * @return void
	 */
	public function register_shortcodes() {
		add_shortcode( 'campaignbay_promo', array( $this, 'shortcode_promo' ) );
		add_shortcode( 'campaignbay_offers', array( $this, 'shortcode_offers' ) );
	}

	/**
	 * Enqueues the stylesheet. Only called when something is actually rendered.
	 *
	 * @since 1.1.19
	 * @return void
	 */
	private function enqueue_styles() {
		wp_enqueue_style( 'campaignbay-messages', CAMPAIGNBAY_URL . 'assets/css/messages.css', array(), CAMPAIGNBAY_VERSION );
	}

	/**
	 * Gets a global setting value.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	private function setting( $key ) {
		return Common::get_instance()->get_settings( $key );
	}

	/**
	 * Returns active campaigns that are really running right now.
	 *
	 * The active list is status based (the Scheduler flips status); dates are re-checked
	 * defensively so a stale cache never advertises an ended or not-yet-started offer.
	 *
	 * @return \WpabCampaignBay\Core\Campaign[]
	 */
	private function get_running_campaigns() {
		$now    = time();
		$result = array();
		foreach ( CampaignManager::get_instance()->get_active_campaigns() as $campaign ) {
			$end = $campaign->get_end_datetime_utc();
			if ( $end && strtotime( $end . ' UTC' ) < $now ) {
				continue;
			}
			$start = $campaign->get_start_datetime_utc();
			if ( $campaign->get_schedule_enabled() && $start && strtotime( $start . ' UTC' ) > $now ) {
				continue;
			}
			$result[ $campaign->get_id() ] = $campaign;
		}
		return $result;
	}

	/**
	 * Does the campaign target this product category (or the whole store)?
	 *
	 * Mirrors Filter::compareWithCategories(): exact category ids only, no ancestors/descendants,
	 * so the message never claims more than the product matcher would discount. With
	 * is_exclude the offer applies to every category not in the list.
	 *
	 * @param \WpabCampaignBay\Core\Campaign $campaign Campaign.
	 * @param int                            $term_id  Category term id (0 = shop page).
	 * @return bool
	 */
	private function campaign_targets_category( $campaign, $term_id ) {
		$type = $campaign->get_target_type();
		if ( 'entire_store' === $type ) {
			return true;
		}
		if ( 'category' !== $type || ! $term_id ) {
			return false;
		}
		$ids = array_map( 'intval', (array) $campaign->get_target_ids() );
		$in  = in_array( (int) $term_id, $ids, true );
		return $campaign->get_is_exclude() ? ! $in : $in;
	}

	/**
	 * Builds a human readable discount string for a campaign.
	 *
	 * Scheduled: "20%" / "$5.00". Quantity and early bird: best tier, prefixed "up to" when
	 * there is more than one tier. BOGO has no sensible "x off" value, so it returns an empty
	 * string and callers skip it (extensible through the campaignbay_offer_discount_text filter).
	 *
	 * @param \WpabCampaignBay\Core\Campaign $campaign Campaign.
	 * @return string
	 */
	private function discount_text( $campaign ) {
		$type = $campaign->get_type();
		$text = '';
		if ( 'scheduled' === $type ) {
			$text = $this->format_value( (float) $campaign->get_discount_value(), 'percentage' === $campaign->get_discount_type() ? 'percentage' : 'currency' );
		} elseif ( 'quantity' === $type || 'earlybird' === $type ) {
			$tiers = $campaign->get_tiers();
			$best  = null;
			foreach ( $tiers as $tier ) {
				if ( ! isset( $tier['value'] ) || (float) $tier['value'] <= 0 ) {
					continue;
				}
				$is_pct = ( $tier['type'] ?? 'percentage' ) === 'percentage';
				// Percentages outrank fixed amounts (cannot be compared without a price).
				$rank = ( $is_pct ? 1000000 : 0 ) + (float) $tier['value'];
				if ( null === $best || $rank > $best['rank'] ) {
					$best = array(
						'rank'   => $rank,
						'value'  => (float) $tier['value'],
						'is_pct' => $is_pct,
					);
				}
			}
			if ( $best ) {
				$text = $this->format_value( $best['value'], $best['is_pct'] ? 'percentage' : 'currency' );
				if ( count( $tiers ) > 1 ) {
					/* translators: %s: discount, e.g. "20%" */
					$text = sprintf( __( 'up to %s', 'campaignbay' ), $text );
				}
			}
		}
		/**
		 * Filters the discount text used in offer messages. Return an empty string to skip the campaign.
		 *
		 * @since 1.1.19
		 * @param string                           $text     Discount text.
		 * @param \WpabCampaignBay\Core\Campaign $campaign Campaign.
		 */
		return (string) apply_filters( 'campaignbay_offer_discount_text', $text, $campaign );
	}

	/**
	 * Formats a numeric discount.
	 *
	 * @param float  $value Value.
	 * @param string $type  percentage|currency.
	 * @return string
	 */
	private function format_value( $value, $type ) {
		if ( 'percentage' === $type ) {
			return rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' ) . '%';
		}
		return html_entity_decode( wp_strip_all_tags( wc_price( $value ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Formatted end date (site timezone) or empty string.
	 *
	 * @param \WpabCampaignBay\Core\Campaign $campaign Campaign.
	 * @return string
	 */
	private function end_date_text( $campaign ) {
		$end = $campaign->get_end_datetime_utc();
		if ( ! $end ) {
			return '';
		}
		$ts = strtotime( $end . ' UTC' );
		return $ts ? wp_date( get_option( 'date_format' ), $ts ) : '';
	}

	/**
	 * Replaces placeholders with escaped values inside a kses-filtered template.
	 *
	 * @param string $template Template.
	 * @param array  $values   Placeholder => raw value.
	 * @return string Safe HTML.
	 */
	private function fill( $template, $values ) {
		$template = wp_kses_post( (string) $template );
		$safe     = array();
		foreach ( $values as $key => $value ) {
			$safe[ $key ] = esc_html( (string) $value );
		}
		return str_replace( array_keys( $safe ), array_values( $safe ), $template );
	}

	/**
	 * Builds the category offer message HTML for a campaign.
	 *
	 * @param \WpabCampaignBay\Core\Campaign $campaign      Campaign.
	 * @param string                         $category_name Category (or shop) name.
	 * @return string HTML, empty when the campaign should be skipped.
	 */
	private function offer_text( $campaign, $category_name ) {
		$discount = $this->discount_text( $campaign );
		if ( '' === $discount ) {
			return '';
		}
		if ( '' === trim( (string) $category_name ) ) {
			$category_name = get_bloginfo( 'name' );
			if ( '' === trim( (string) $category_name ) ) {
				$category_name = __( 'our store', 'campaignbay' );
			}
		}
		return $this->fill(
			$this->setting( 'category_offer_format' ),
			array(
				'{campaign_title}' => $campaign->get_title(),
				'{discount}'       => $discount,
				'{category_name}'  => $category_name,
				'{end_date}'       => $this->end_date_text( $campaign ),
			)
		);
	}

	/**
	 * Builds the promo blurb HTML for a campaign.
	 *
	 * @param \WpabCampaignBay\Core\Campaign $campaign Campaign.
	 * @return string HTML, empty when promotion is off or has no text.
	 */
	private function promo_html( $campaign ) {
		$settings = $campaign->get_settings();
		if ( empty( $settings['promote_enabled'] ) || empty( $settings['promote_text'] ) ) {
			return '';
		}
		$values = array(
			'{campaign_title}' => $campaign->get_title(),
			'{end_date}'       => $this->end_date_text( $campaign ),
			'{discount}'       => $this->discount_text( $campaign ),
		);
		$promo  = $this->fill( $settings['promote_text'], $values );
		$format = (string) $this->setting( 'promote_format' );
		if ( '' === trim( $format ) ) {
			$format = '{promo_text}';
		}
		// The promo text is already escaped; substitute it last so it is not escaped twice.
		$out = $this->fill(
			$format,
			array(
				'{campaign_title}' => $campaign->get_title(),
				'{end_date}'       => $values['{end_date}'],
			)
		);
		$out = str_replace( '{promo_text}', $promo, $out );
		return '<div class="campaignbay-promo campaignbay-promo-' . esc_attr( $campaign->get_id() ) . '">' . $out . '</div>';
	}

	/**
	 * Resolves the current archive context.
	 *
	 * @return array|null array( term_id, name, is_shop ) or null when not applicable.
	 */
	private function archive_context() {
		if ( is_product_category() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				return array(
					'term_id' => (int) $term->term_id,
					'name'    => $term->name,
					'is_shop' => false,
				);
			}
			return null;
		}
		if ( function_exists( 'is_shop' ) && is_shop() && ! is_search() ) {
			return array(
				'term_id' => 0,
				'name'    => get_the_title( wc_get_page_id( 'shop' ) ),
				'is_shop' => true,
			);
		}
		return null;
	}

	/**
	 * Prints category/shop offer messages and category promo blurbs.
	 *
	 * @return void
	 */
	public function render_archive_messages() {
		// Only on the first page of the archive, not every pagination page.
		if ( is_paged() ) {
			return;
		}
		$ctx = $this->archive_context();
		if ( ! $ctx ) {
			return;
		}
		$show_offer   = $ctx['is_shop'] ? $this->setting( 'category_offer_show_shop' ) : $this->setting( 'category_offer_show' );
		$show_promote = ! $ctx['is_shop'] && $this->setting( 'promote_show_category' );
		if ( ! $show_offer && ! $show_promote ) {
			return;
		}

		$html = '';
		foreach ( $this->get_running_campaigns() as $campaign ) {
			if ( ! $this->campaign_targets_category( $campaign, $ctx['term_id'] ) ) {
				continue;
			}
			if ( $show_offer ) {
				$text = $this->offer_text( $campaign, $ctx['name'] );
				if ( '' !== $text ) {
					$html .= '<div class="campaignbay-offer-message campaignbay-offer-' . esc_attr( $campaign->get_id() ) . '">' . $text . '</div>';
				}
			}
			if ( $show_promote ) {
				$html .= $this->promo_html( $campaign );
			}
		}
		if ( '' === $html ) {
			return;
		}
		$this->enqueue_styles();
		echo wp_kses_post( $html );
	}

	/**
	 * Prints promo blurbs for campaigns that apply to the current single product.
	 *
	 * @return void
	 */
	public function render_product_promos() {
		global $product;
		if ( ! $this->setting( 'promote_show_product' ) || ! $product instanceof \WC_Product ) {
			return;
		}
		$html = '';
		foreach ( $this->get_running_campaigns() as $campaign ) {
			$settings = $campaign->get_settings();
			if ( empty( $settings['promote_enabled'] ) || empty( $settings['promote_text'] ) ) {
				continue; // Cheap check first: skips the (logged) product matcher.
			}
			if ( ! Filter::get_instance()->match( $product, $campaign ) ) {
				continue;
			}
			$html .= $this->promo_html( $campaign );
		}
		if ( '' === $html ) {
			return;
		}
		$this->enqueue_styles();
		echo wp_kses_post( $html );
	}

	/**
	 * Shortcode [campaignbay_promo id="12"]: the promo blurb of one running campaign.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function shortcode_promo( $atts ) {
		$atts      = shortcode_atts( array( 'id' => 0 ), $atts, 'campaignbay_promo' );
		$campaigns = $this->get_running_campaigns();
		$id        = absint( $atts['id'] );
		if ( ! $id || ! isset( $campaigns[ $id ] ) ) {
			return '';
		}
		$html = $this->promo_html( $campaigns[ $id ] );
		if ( '' !== $html ) {
			$this->enqueue_styles();
		}
		return wp_kses_post( $html );
	}

	/**
	 * Shortcode [campaignbay_offers category="slug"]: list of running offers for a category
	 * (entire-store offers included). Without a category, lists entire-store offers only.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public function shortcode_offers( $atts ) {
		$atts    = shortcode_atts( array( 'category' => '' ), $atts, 'campaignbay_offers' );
		$term_id = 0;
		$name    = '';
		if ( '' !== $atts['category'] ) {
			$term = get_term_by( 'slug', sanitize_title( $atts['category'] ), 'product_cat' );
			if ( ! $term instanceof \WP_Term ) {
				return '';
			}
			$term_id = (int) $term->term_id;
			$name    = $term->name;
		}
		$items = '';
		foreach ( $this->get_running_campaigns() as $campaign ) {
			if ( ! $this->campaign_targets_category( $campaign, $term_id ) ) {
				continue;
			}
			$text = $this->offer_text( $campaign, $name );
			if ( '' !== $text ) {
				$items .= '<li>' . $text . '</li>';
			}
		}
		if ( '' === $items ) {
			return '';
		}
		$this->enqueue_styles();
		return wp_kses_post( '<ul class="campaignbay-offers-list">' . $items . '</ul>' );
	}
}
