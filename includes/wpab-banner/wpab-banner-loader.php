<?php
/**
 * WPAnchorBay campaign banner loader.
 *
 * Ship this file next to wpab-banner.html in every WPAnchorBay plugin and require
 * it from the plugin's admin bootstrap. wpab-banner.html is the chosen banner
 * design (copy the final banner file here under that name).
 *
 * Several plugins can include this file on the same site: the first one to load
 * wins and the rest do nothing, so there is only ever one banner, one dismissal
 * flag and one AJAX handler.
 *
 * Behaviour:
 *  - Shown to administrators only, and only between the start and end times below.
 *  - Shown on the Plugins screen, on the active plugins' own screens and on their
 *    WooCommerce > Settings tab. The Dashboard is off unless a filter turns it on.
 *  - Hidden automatically after the end time, even from a cached page.
 *  - Closing the banner saves the dismissal for that user and that campaign in
 *    user meta, so it stays closed on every browser and device. A new campaign
 *    (new WPAB_BANNER_CAMPAIGN value) shows again.
 *
 * To run the next campaign: change the three constants below and swap in that
 * campaign's wpab-banner.html.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WPAB_BANNER_LOADED' ) ) {
	return;
}
define( 'WPAB_BANNER_LOADED', true );

// Campaign id. Changing it makes the banner show again for users who dismissed the last one.
define( 'WPAB_BANNER_CAMPAIGN', 'blackfriday26' );

// Start: 25 Nov 2026, 00:00:01 UTC (24 Nov 2026, 7:00:01 PM EST).
define( 'WPAB_BANNER_STARTS', 1795564801 );

// End: 5 Dec 2026, 23:59:59 UTC (5 Dec 2026, 6:59:59 PM EST). The banner is not printed after this, and hides itself on a cached page.
define( 'WPAB_BANNER_ENDS', 1796515199 );

// The chosen banner design.
define( 'WPAB_BANNER_FILE', __DIR__ . '/wpab-banner.html' );

// User meta key that holds the list of dismissed campaign ids.
define( 'WPAB_BANNER_META', 'wpab_banner_dismissed' );

if ( ! function_exists( 'wpab_banner_active_plugins' ) ) {
	/**
	 * Slugs of the WPAnchorBay plugins that are active on this site.
	 * Matched on the exact plugin folder name (the WordPress.org slug).
	 *
	 * @return string[]
	 */
	function wpab_banner_active_plugins() {
		// Short brand key (used by the banner) => the plugin's real WordPress.org folder slug.
		$brands = array(
			'campaignbay' => 'campaignbay',
			'productbay'  => 'productbay',
			'tubebay'     => 'tubebay',
			'optionbay'   => 'optionbay-product-options-addons-woo',
			'cartbay'     => 'cartbay-abandoned-cart-recovery-for-woocommerce',
			'pointbay'    => 'pointbay-loyalty-points-referral-rewards-woo',
			'notifybay'   => 'notifybay-waitlist-and-stock-alert-woo',
			'upsellbay'   => 'upsellbay-order-bump-for-woocommerce',
			'spacebay'    => 'spacebay',
		);
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}

		$found = array();
		foreach ( $brands as $key => $folder ) {
			foreach ( $active as $plugin_file ) {
				// Exact folder match, so other vendors' plugins with a similar name are not counted.
				if ( dirname( (string) $plugin_file ) === $folder ) {
					$found[] = $key;
					break;
				}
			}
		}
		return $found;
	}
}

if ( ! function_exists( 'wpab_banner_on_screen' ) ) {
	/**
	 * Where the banner may appear:
	 *  1. the Plugins screen,
	 *  2. the active WPAnchorBay plugins' own admin pages (their dashboards and submenu screens),
	 *  3. a plugin's tab inside WooCommerce > Settings,
	 *  4. the main WordPress Dashboard, only when a site or plugin opts in (off by default).
	 *
	 * @param string[] $slugs Active plugin slugs.
	 * @return bool
	 */
	function wpab_banner_on_screen( $slugs ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return false;
		}

		if ( 'plugins' === $screen->id ) {
			return true;
		}

		foreach ( $slugs as $slug ) {
			if ( false !== stripos( $screen->id, $slug ) ) {
				return true;
			}
		}

		// All WooCommerce settings share one screen id, so the tab in the URL decides. The tab slug
		// defaults to the plugin slug; use the filter when a plugin's tab slug is different.
		if ( 'woocommerce_page_wc-settings' === $screen->id ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reads a tab name only, changes nothing.
			$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
			$tabs = apply_filters( 'wpab_banner_wc_tabs', array_combine( $slugs, $slugs ), $slugs );
			if ( $tab && in_array( $tab, (array) $tabs, true ) ) {
				return true;
			}
		}

		// Off by default: Guideline 11 says advertising within the WordPress dashboard should be avoided.
		if ( 'dashboard' === $screen->id && apply_filters( 'wpab_banner_show_on_dashboard', false ) ) {
			return true;
		}

		/**
		 * Let a plugin opt in admin screens whose id does not contain its brand key.
		 *
		 * @param bool      $show   Whether to show on this screen.
		 * @param WP_Screen $screen Current screen.
		 * @param string[]  $slugs  Active WPAnchorBay plugin keys.
		 */
		if ( apply_filters( 'wpab_banner_show_on_screen', false, $screen, $slugs ) ) {
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'wpab_banner_is_dismissed' ) ) {
	/**
	 * Has the current user already closed this campaign's banner?
	 *
	 * @return bool
	 */
	function wpab_banner_is_dismissed() {
		$done = get_user_meta( get_current_user_id(), WPAB_BANNER_META, true );
		return is_array( $done ) && in_array( WPAB_BANNER_CAMPAIGN, $done, true );
	}
}

if ( ! function_exists( 'wpab_banner_print' ) ) {
	/**
	 * Print the banner with the active slugs and deadline filled in.
	 */
	function wpab_banner_print() {
		if ( time() < WPAB_BANNER_STARTS || time() >= WPAB_BANNER_ENDS || ! current_user_can( 'manage_options' ) || wpab_banner_is_dismissed() ) {
			return;
		}

		$slugs = wpab_banner_active_plugins();
		if ( ! $slugs || ! wpab_banner_on_screen( $slugs ) ) {
			return;
		}

		/**
		 * Return false to hide the banner, for example on sites that already hold a Pro licence.
		 *
		 * @param bool     $show  Whether to show the banner.
		 * @param string[] $slugs Active WPAnchorBay plugin slugs.
		 */
		if ( ! apply_filters( 'wpab_banner_show', true, $slugs ) ) {
			return;
		}

		if ( ! is_readable( WPAB_BANNER_FILE ) ) {
			return;
		}

		$html = (string) file_get_contents( WPAB_BANNER_FILE );
		$html = preg_replace( '/data-plugins="[^"]*"/', 'data-plugins="' . esc_attr( implode( ',', $slugs ) ) . '"', $html, 1 );
		$html = preg_replace( '/data-ends="[^"]*"/', 'data-ends="' . esc_attr( gmdate( 'Y-m-d\TH:i:s\Z', WPAB_BANNER_ENDS ) ) . '"', $html, 1 );

		$config = array(
			'url'      => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wpab_dismiss_banner' ),
			'campaign' => WPAB_BANNER_CAMPAIGN,
		);

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static bundled file, dynamic values escaped above.
		?>
		<script>
		(function () {
			var cfg = <?php echo wp_json_encode( $config ); ?>;
			window.addEventListener('wpab:banner-dismissed', function () {
				var body = new URLSearchParams();
				body.set('action', 'wpab_dismiss_banner');
				body.set('campaign', cfg.campaign);
				body.set('_wpnonce', cfg.nonce);
				try { fetch(cfg.url, { method: 'POST', credentials: 'same-origin', body: body }); } catch (e) { /* the banner is already closed for this page view */ }
			});
		})();
		</script>
		<?php
	}
}
add_action( 'admin_notices', 'wpab_banner_print' );

if ( ! function_exists( 'wpab_banner_dismiss' ) ) {
	/**
	 * AJAX: save that the current user closed this campaign's banner.
	 */
	function wpab_banner_dismiss() {
		check_ajax_referer( 'wpab_dismiss_banner', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		$campaign = isset( $_POST['campaign'] ) ? sanitize_key( wp_unslash( $_POST['campaign'] ) ) : '';
		if ( WPAB_BANNER_CAMPAIGN !== $campaign ) {
			wp_send_json_error( null, 400 );
		}

		$user_id = get_current_user_id();
		$done    = get_user_meta( $user_id, WPAB_BANNER_META, true );
		$done    = is_array( $done ) ? $done : array();
		if ( ! in_array( $campaign, $done, true ) ) {
			$done[] = $campaign;
			update_user_meta( $user_id, WPAB_BANNER_META, array_slice( $done, -20 ) );
		}
		wp_send_json_success();
	}
}
add_action( 'wp_ajax_wpab_dismiss_banner', 'wpab_banner_dismiss' );
