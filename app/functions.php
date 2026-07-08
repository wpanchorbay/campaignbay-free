<?php
/**
 * Reusable functions.
 *
 * @package    WPAB_CampaignBay
 * @since 1.0.0
 * @author     dipta-sdd <sankarsandipta@gmail.com>
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}




if ( ! function_exists( 'wpab_campaignbay_get_white_label' ) ) :
	/**
	 * Get white label options for this plugin.
	 *
	 * @since 1.0.0
	 * @param string $key optional option key.
	 * @return mixed All Options Array Or Options Value
	 * @author     dipta-sdd <sankarsandipta@gmail.com>
	 */
	function wpab_campaignbay_get_white_label( $key = '' ) {
		/**
		 * Filters the generic white-label plugin name.
		 *
		 * This filter provides a simple hook to change the plugin's name for white-labeling
		 * purposes. It's a general-purpose filter, often superseded by the more detailed
		 * array provided in the `_white_label` filter below.
		 *
		 * @since 1.0.0
		 * @hook campaignbay_white_label_plugin_name
		 *
		 * @param string The default plugin name string.
		 * @return string The filtered plugin name.
		 */
		$plugin_name = apply_filters(
			'campaignbay_white_label_plugin_name',
			esc_html( 'CampaignBay' )
		);

		/**
		 * Filters the entire array of white-label settings for the plugin.
		 *
		 * This is the main filter for re-branding the plugin. It allows developers to
		 * change all branding-related text and URLs that are used to build the admin menu,
		 * localize scripts for the React UI, and provide support links.
		 *
		 * @since 1.0.0
		 * @hook campaignbay_white_label
		 *
		 * @param array $options An associative array of white-label settings.
		 *    @type string $plugin_name The full, formal name of the plugin.
		 *    @type string $short_name The shorter name used in UI elements.
		 *    @type string $menu_label The text for the main admin menu item.
		 *    @type string $custom_icon The URL to the icon used in custom UI components.
		 *    @type string $menu_icon The URL to the icon for the admin menu, or a Dashicon slug.
		 *    @type string $author_name The name of the plugin author.
		 *    @type string $author_uri The URL for the plugin author's website.
		 *    @type string $support_uri The URL for the plugin's support forum or page.
		 *    @type string $docs_uri The URL for the plugin's documentation.
		 *    @type int $position The position of the menu item in the WordPress admin sidebar.
		 * @return array The filtered array of white-label settings.
		 */
		$options = apply_filters(
			'campaignbay_white_label',
			array(
				'plugin_name' => esc_html( 'CampaignBay - WooCommerce Smart Campaigns' ),
				'short_name'  => esc_html( 'CampaignBay' ),
				'menu_label'  => esc_html( 'CampaignBay' ),
				'custom_icon' => CAMPAIGNBAY_URL . 'assets/img/dash_icon_campaign_bay_light.svg',
				'menu_icon'   => CAMPAIGNBAY_URL . 'assets/img/dash_icon_campaign_bay_light.svg',
				'author_name' => 'WP Anchor Bay',
				'author_uri'  => 'https://wpanchorbay.com',
				'support_uri' => 'https://wpanchorbay.com/support',
				'docs_uri'    => 'https://docs.wpanchorbay.com',
				// 'menu_icon'        => 'dashicons-awards',
				'position'    => 57,
			)
		);
		if ( ! empty( $key ) ) {
			return $options[ $key ];
		} else {
			return $options;
		}
	}
endif;



if ( ! function_exists( 'wpab_campaignbay_log' ) ) {

	/**
	 * Log messages to the debug log.
	 *
	 * @param mixed  $message The message to log.
	 * @param string $level The log level (e.g.,'DEBUG', 'INFO', 'ERROR', 'NOTICE', 'WARNING', 'CRITICAL', 'ALERT', 'EMERGENCY' ).
	 * @param bool   $dev_mode Whether to log messages in development mode.
	 */
	function wpab_campaignbay_log( $message, $level = 'INFO', $dev_mode = true ) {
		$enable_logging = WpabCampaignBay\Core\Common::get_instance()->get_settings( 'debug_enableMode' );
		// if (!$enable_logging && !defined('WP_DEBUG') && !WP_DEBUG && $level !== 'error') {
		if ( ! $enable_logging && ( $level !== 'ERROR' && $level !== 'error' && $level !== 'DEBUG' && $level !== 'debug' ) ) {
			// error_log(print_r(array(
			// 'Message' => $message,
			// 'Level' => $level
			// ), true));
			return;
		}
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/' . CAMPAIGNBAY_TEXT_DOMAIN . '-logs/';

		if ( ! is_dir( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		$log_file = $log_dir . 'plugin-log-' . gmdate( 'Y-m-d' ) . '.log';

		$formatted_message = '';
		if ( is_array( $message ) || is_object( $message ) ) {
			// phpcs:ignore
			$formatted_message = print_r($message, true);
		} else {
			$formatted_message = $message;
		}
		// phpcs:ignore 
		$log_level = is_string($level) ? strtoupper($level) : (is_array($level) || is_object($level) ? print_r($level, true) : '');
		$log_entry = sprintf(
			"[%s] [%s]: %s\n",
			current_time( 'mysql' ),
			$log_level,
			$formatted_message
		);
		file_put_contents( $log_file, $log_entry, FILE_APPEND | LOCK_EX );
	}
}



if ( ! function_exists( 'wpab_campaignbay_get_value' ) ) {
	/**
	 * Safely retrieve a value from a nested array or object using dot notation.
	 * Returns default if key is missing OR if value is an empty string.
	 *
	 * @since 1.0.0
	 * @param array|object $target  The array or object to search.
	 * @param string|array $key     The key path (e.g., 'settings.color').
	 * @param mixed        $default The default value if key is not found or is empty string.
	 * @return mixed
	 */
	function wpab_campaignbay_get_value( $target, $key, $default = null ) {
		if ( is_null( $key ) || trim( $key ) == '' ) {
			return $target;
		}

		$keys = is_array( $key ) ? $key : explode( '.', $key );

		foreach ( $keys as $segment ) {
			if ( is_array( $target ) && isset( $target[ $segment ] ) ) {
				$target = $target[ $segment ];
			} elseif ( is_object( $target ) && isset( $target->{$segment} ) ) {
				$target = $target->{$segment};
			} else {
				// Key not found, return default
				return $default;
			}
		}

		// If the found value is exactly an empty string, return default.
		if ( $target === '' ) {
			return $default;
		}

		return $target;
	}
}
