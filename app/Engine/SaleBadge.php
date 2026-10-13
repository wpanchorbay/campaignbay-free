<?php
/**
 * File.
 *
 * @package campaignbay
 */

namespace WpabCampaignBay\Engine;

use WpabCampaignBay\Core\Base;

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
		$hooks = array();
		foreach ( $hooks as $hook ) {
			$this->add_hook( ...$hook );
		}
	}
}
