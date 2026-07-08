<?php

namespace WpabCampaignBay\Core;

/**
 * The file that defines the Base class.
 *
 * All classes that manage hooks and require a single instance should extend this class.
 * It provides a reliable Singleton pattern and a centralized hook registration system.
 *
 * @link       https://wpanchorbay.com
 * @since      1.0.0
 *
 * @package    WPAB_CampaignBay
 * @subpackage WPAB_CampaignBay/includes
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Base Hook Manager class.
 *
 * Provides a Singleton pattern that works correctly with inheritance and a system
 * for child classes to register their actions and filters.
 *
 * @since      1.0.0
 * @package    WPAB_CampaignBay
 * @author     WP Anchor Bay <wpanchorbay@gmail.com>
 */
abstract class Base {


	/**
	 * An array of instances of the extending classes.
	 * This is the key to the inheritable Singleton pattern.
	 *
	 * @since 1.0.0
	 * @var   array
	 * @access private
	 */
	private static $instances = array();

	/**
	 * The array of hooks to be registered for a specific instance.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @var array
	 */
	protected $hooks = array();


	/**
	 * Gets a single instance of the called class.
	 *
	 * This factory method ensures that we only have one instance of each child class.
	 *
	 * @static
	 * @access public
	 * @since 1.0.0
	 * @return object The single instance of the calling class.
	 */
	public static function get_instance() {
		// `static::class` gets the name of the class that called this method
		$class = static::class;
		if ( ! isset( self::$instances[ $class ] ) ) {
			// `new static()` creates an instance of the calling class.
			self::$instances[ $class ] = new static();
		}
		return self::$instances[ $class ];
	}

	/**
	 * Protected constructor to prevent direct creation of object.
	 * The child class constructor will call this.
	 *
	 * @since    1.0.0
	 */
	protected function __construct() {
		// The child class will define its hooks here.
	}

	/**
	 * Adds a new action to the hooks array.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @param string $hook The hook name.
	 * @param string $callback The callback method on this object.
	 * @param int    $priority The priority.
	 * @param int    $accepted_args The number of accepted arguments.
	 */
	protected function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->add_hook( 'action', $hook, $callback, $priority, $accepted_args );
	}

	/**
	 * Adds a new filter to the hooks array.
	 *
	 * @since 1.0.0
	 * @access protected
	 * @param string $hook The hook name.
	 * @param string $callback The callback method on this object.
	 * @param int    $priority The priority.
	 * @param int    $accepted_args The number of accepted arguments.
	 */
	protected function add_filter( $hook, $callback, $priority = 10, $accepted_args = 2 ) {
		$this->add_hook( 'filter', $hook, $callback, $priority, $accepted_args );
	}

	/**
	 * A private helper method to add hooks to the hooks array.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param string $type 'action' or 'filter'.
	 * @param string $hook The hook name.
	 * @param string $callback The callback method on this object.
	 * @param int    $priority The priority.
	 * @param int    $accepted_args The number of accepted arguments.
	 */
	protected function add_hook( $type, $hook, $callback, $priority, $accepted_args ) {
		$this->hooks[] = array(
			'type'          => $type,
			'hook'          => $hook,
			'component'     => $this, // The component is the instance of the child class itself.
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Returns the complete array of hooks to be registered by the main loader.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return array
	 */
	public function get_hooks() {
		return $this->hooks;
	}


	/**
	 * Prevents the instance from being cloned.
	 *
	 * @since 1.0.0
	 */
	private function __clone() {
	}

	/**
	 * Prevents the instance from being unserialized.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
	}
}
