<?php
/**
 * WPAnchorBay banner: shared uninstall cleanup.
 *
 * The banner remembers "this user closed it" in one user meta key that is shared by every
 * WPAnchorBay plugin. If each plugin deleted that key on its own uninstall, removing one plugin
 * would make the banner show again in the plugins that are still installed.
 *
 * So the key is removed only when the LAST WPAnchorBay plugin is uninstalled.
 *
 * Copy this file into each plugin (for example includes/wpab-banner-uninstall.php), require it from
 * that plugin's uninstall.php and call wpab_banner_uninstall() as shown at the bottom.
 *
 * Rules:
 *  - Another WPAnchorBay plugin is still installed (active or not): keep the meta. If this plugin's
 *    "delete data on uninstall" option is on, remember that someone asked for deletion.
 *  - This is the last one: delete the meta if this plugin's option is on, or if an earlier plugin
 *    asked for deletion.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wpab_banner_is_ours' ) ) {
	/**
	 * Is this installed plugin a WPAnchorBay plugin?
	 *
	 * @param string $file Plugin basename, e.g. campaignbay/campaignbay.php.
	 * @param array  $data Plugin header data from get_plugins().
	 * @return bool
	 */
	function wpab_banner_is_ours( $file, $data ) {
		$uri = isset( $data['AuthorURI'] ) ? (string) $data['AuthorURI'] : '';
		if ( false !== stripos( $uri, 'wpanchorbay.com' ) ) {
			return true;
		}
		$author = isset( $data['Author'] ) ? (string) $data['Author'] : '';
		if ( false !== stripos( $author, 'wpanchorbay' ) ) {
			return true;
		}
		$folders = array(
			'campaignbay',
			'productbay',
			'tubebay',
			'optionbay-product-options-addons-woo',
			'cartbay-abandoned-cart-recovery-for-woocommerce',
			'pointbay-loyalty-points-referral-rewards-woo',
			'notifybay-waitlist-and-stock-alert-woo',
			'upsellbay-order-bump-for-woocommerce',
			'spacebay',
		);
		return in_array( dirname( $file ), $folders, true );
	}
}

if ( ! function_exists( 'wpab_banner_other_plugins_installed' ) ) {
	/**
	 * Is any other WPAnchorBay plugin still installed (active or inactive)?
	 *
	 * @param string $current Basename of the plugin being uninstalled.
	 * @return bool
	 */
	function wpab_banner_other_plugins_installed( $current ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		// When several plugins are deleted in one go, WordPress may hold an old list in cache.
		if ( function_exists( 'wp_clean_plugins_cache' ) ) {
			wp_clean_plugins_cache( false );
		}
		foreach ( get_plugins() as $file => $data ) {
			if ( $file !== $current && wpab_banner_is_ours( $file, $data ) ) {
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'wpab_banner_uninstall' ) ) {
	/**
	 * Call from each plugin's uninstall.php.
	 *
	 * @param string $current   Basename of the plugin being uninstalled (use WP_UNINSTALL_PLUGIN).
	 * @param bool   $delete_me True when this plugin's "delete data on uninstall" option is on.
	 */
	function wpab_banner_uninstall( $current, $delete_me ) {
		$flag = 'wpab_banner_cleanup_requested';

		if ( wpab_banner_other_plugins_installed( $current ) ) {
			if ( $delete_me ) {
				update_site_option( $flag, 1 );
			}
			return;
		}

		if ( ! $delete_me && ! get_site_option( $flag ) ) {
			return;
		}

		// Remove the "banner closed" flag for every user, then our own bookkeeping.
		delete_metadata( 'user', 0, 'wpab_banner_dismissed', '', true );
		delete_site_option( $flag );
	}
}

/*
 * Example, inside a plugin's uninstall.php:
 *
 *   if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }
 *   require_once __DIR__ . '/includes/wpab-banner-uninstall.php';
 *   $delete = (bool) get_option( 'campaignbay_delete_data_on_uninstall' ); // that plugin's own option
 *   // ... the plugin's own cleanup here ...
 *   wpab_banner_uninstall( WP_UNINSTALL_PLUGIN, $delete );
 */
