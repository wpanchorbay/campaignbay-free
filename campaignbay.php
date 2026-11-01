<?php
/**
 * Plugin Name:       CampaignBay - Automated Discount Campaigns & Flash Sales for WooCommerce
 * Plugin URI:        https://wpanchorbay.com/campaignbay
 * Source URI:        https://github.com/dipta-sdd/campaignbay
 * Description:       Automated Discount Campaigns & Flash Sales for WooCommerce.
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 6.1
 * Version:           1.2.1
 * Stable tag:        1.2.1
 * Author:            WPAnchorBay
 * Author URI:        https://wpanchorbay.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       campaignbay
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'CAMPAIGNBAY_PATH', plugin_dir_path( __FILE__ ) );
define( 'CAMPAIGNBAY_DIR', plugin_dir_path( __FILE__ ) );
define( 'CAMPAIGNBAY_URL', plugin_dir_url( __FILE__ ) );
define( 'CAMPAIGNBAY_VERSION', '1.2.1' );
define( 'CAMPAIGNBAY_PLUGIN_NAME', 'campaignbay' );
define( 'CAMPAIGNBAY_TEXT_DOMAIN', 'campaignbay' );
define( 'CAMPAIGNBAY_OPTION_NAME', 'campaignbay' );
define( 'CAMPAIGNBAY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'CAMPAIGNBAY_DEV_MODE', true );

spl_autoload_register(
	function ( $class_name ) {
		// Autoload DeactivationFeedback
		if ( 0 === strpos( $class_name, 'WPAB\\DeactivationFeedback\\' ) ) {
			$file = CAMPAIGNBAY_PATH . 'vendor/deactivation-feedback/src/' . str_replace( '\\', '/', substr( $class_name, 25 ) ) . '.php';
			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}

		// Only handle our plugin's classes
		if ( 0 !== strpos( $class_name, 'WpabCampaignBay\\' ) ) {
			return;
		}
		// Convert namespace to file path
		$file = CAMPAIGNBAY_PATH . 'app/' . str_replace( '\\', '/', substr( $class_name, 16 ) ) . '.php';

		// Load the file if it exists
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

require_once CAMPAIGNBAY_PATH . 'app/functions.php';
if ( ! function_exists( 'is_plugin_active' ) ) {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';
}

register_activation_hook( __FILE__, 'wpab_campaignbay_activate' );
register_deactivation_hook( __FILE__, 'wpab_campaignbay_deactivate' );
/**
 * Begins execution of the plugin.
 *
 * @since    1.0.0
 */
if ( ! function_exists( 'wpab_campaignbay_run' ) ) {
	/**
	 * Function.
	 */
	function wpab_campaignbay_run() {
		if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$plugin = \WpabCampaignBay\Core\Plugin::get_instance();
			add_action( 'plugins_loaded', array( $plugin, 'run' ) );
		} else {
			add_action( 'admin_notices', 'wpab_campaignbay_woocommerce_not_active_notice' );
		}

		// Initialize Deactivation Feedback on init to avoid early translation loading
		add_action(
			'init',
			function () {
				if ( is_admin() && class_exists( '\WPAB\DeactivationFeedback\DeactivationFeedback' ) ) {
					new \WPAB\DeactivationFeedback\DeactivationFeedback(
						array(
							'plugin_file'           => CAMPAIGNBAY_PLUGIN_BASENAME,
							'plugin_slug'           => CAMPAIGNBAY_PLUGIN_NAME,
							// The receiver only answers on this route; the bare origin returns
							// 400, and the POST is non-blocking so that failure never surfaced.
							'remote_endpoint'       => 'https://dfs.wpanchorbay.com/wp-json/wpab/v1/feedback',
							'disclosure_label'      => __( 'What information is sent?', 'campaignbay' ),
							'disclosure_text'       => __( 'Pressing submit sends the reason you select and any note you type, together with your site address, the email address of your account, and your WordPress version. Choose "Skip & Deactivate" to send nothing.', 'campaignbay' ),
							'reasons'               => array(
								array(
									'id'          => 'couldnt_get_working',
									'label'       => __( "I couldn't get it working", 'campaignbay' ),
									'has_input'   => true,
									'placeholder' => __( 'What were you trying to configure?', 'campaignbay' ),
								),
								array(
									'id'          => 'missing_feature',
									'label'       => __( "It's missing a feature I need", 'campaignbay' ),
									'has_input'   => true,
									'placeholder' => __( 'What feature?', 'campaignbay' ),
								),
								array(
									'id'          => 'conflict',
									'label'       => __( 'It conflicted with my theme or another plugin', 'campaignbay' ),
									'has_input'   => true,
									'placeholder' => __( 'Which theme/plugin?', 'campaignbay' ),
								),
								array(
									'id'          => 'slowed_site',
									'label'       => __( 'It slowed down my site', 'campaignbay' ),
									'has_input'   => true,
									'placeholder' => __( 'Please describe (we will investigate your site automatically)', 'campaignbay' ),
								),
								array(
									'id'          => 'better_alternative',
									'label'       => __( 'I found a better alternative', 'campaignbay' ),
									'has_input'   => true,
									'placeholder' => __( 'Which plugin?', 'campaignbay' ),
								),
								array(
									'id'          => 'too_complicated',
									'label'       => __( "It's too complicated to set up", 'campaignbay' ),
									'has_input'   => true,
									'placeholder' => __( 'What part was confusing?', 'campaignbay' ),
								),
								array(
									'id'        => 'no_longer_needed',
									'label'     => __( 'I no longer need discount campaigns on my store', 'campaignbay' ),
									'has_input' => false,
								),
								array(
									'id'        => 'temporary',
									'label'     => __( "It's temporary — I'm troubleshooting/debugging", 'campaignbay' ),
									'has_input' => false,
								),
								array(
									'id'          => 'other',
									'label'       => __( 'Other', 'campaignbay' ),
									'has_input'   => true,
									'placeholder' => __( 'Please share the reason', 'campaignbay' ),
								),
							),
							'review_notice_enabled' => true,
							'review_delay_days'     => 3,
							'review_snooze_days'    => 7,
							'review_url'            => 'https://wordpress.org/support/plugin/campaignbay/reviews/#new-post',
							'support_url'           => 'https://wordpress.org/support/plugin/campaignbay/',
							'review_notice_title'   => __( 'Enjoying CampaignBay?', 'campaignbay' ),
							'review_notice_message' => __( "You've been using CampaignBay for a few days now. If it's been helpful, would you mind leaving a quick review? It really helps!", 'campaignbay' ),
						)
					);
				}
			}
		);
	}
}
wpab_campaignbay_run();

/**
 * Function.
 */
function wpab_campaignbay_activate() {
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	\WpabCampaignBay\Core\Activator::activate();

	// Record activation time for the review notice logic.
	if ( ! get_option( 'wpab_activated_at_campaignbay' ) ) {
		update_option( 'wpab_activated_at_campaignbay', time(), false );
	}
}

/**
 * Function.
 */
function wpab_campaignbay_deactivate() {
	\WpabCampaignBay\Core\Deactivator::deactivate();
}




/**
 * Function.
 */
function wpab_campaignbay_woocommerce_not_active_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: 1: The name of our plugin (CampaignBay). 2: The name of the required plugin (e.g., WooCommerce). */
				esc_html__( '%1$s requires the %2$s plugin to be installed and activated. Please activate %2$s to continue.', 'campaignbay' ),
				'<strong>CampaignBay</strong>',
				'<strong>WooCommerce</strong>'
			);
			?>
		</p>
	</div>
	<?php
}