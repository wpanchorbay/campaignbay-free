<?php

namespace WpabCampaignBay\Api;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Import WordPress REST API classes

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * The REST API Controller for viewing logs.
 *
 * @since      1.0.0
 * @package    WPAB_CampaignBay
 * @author     WP Anchor Bay <wpanchorbay@gmail.com>
 */
class LogsController extends ApiController {


	/**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 * @access private
	 * @var LogsController
	 */
	private static $instance = null;

	/**
	 * Gets an instance of this object.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return LogsController
	 */
	public static function get_instance() {
		// Store the instance locally to avoid private static replication.
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function run() {
		$this->rest_base = 'logs';
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		// TODO - will move this to cron jobs .
		add_action( 'admin_init', array( $this, 'run_log_cleanup_check' ) );
	}


	/**
	 * Checks if it's time to run the log cleanup task.
	 *
	 * This method is hooked to `admin_init` and uses a transient to ensure
	 * the actual cleanup logic only runs once per day.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function run_log_cleanup_check() {
		// The transient acts as a "lock" to prevent this from running on every page load.
		if ( get_transient( CAMPAIGNBAY_TEXT_DOMAIN . '_log_cleanup_check' ) ) {
			return;
		}

		// If the transient doesn't exist, it's time to run our cleanup.
		// We immediately set the transient to "lock" it for the next 24 hours.
		set_transient( CAMPAIGNBAY_TEXT_DOMAIN . '_log_cleanup_check', true, DAY_IN_SECONDS );

		// Call the actual cleanup logic.
		$this->cleanup_old_log_files();
	}

	/**
	 * Cleans up log files older than a specified number of days.
	 * This method is now triggered by our transient-based check.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function cleanup_old_log_files() {
		if ( ! is_admin() && ! wp_doing_cron() ) {
			return;
		}

		wpab_campaignbay_log( 'Running daily log file cleanup task.', 'INFO' );

		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/' . CAMPAIGNBAY_TEXT_DOMAIN . '-logs/';

		if ( ! is_dir( $log_dir ) ) {
			return; // Nothing to do if the directory doesn't exist.
		}

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( is_wp_error( $wp_filesystem ) || ! $wp_filesystem ) {
			wpab_campaignbay_log( 'Failed to initialize WP_Filesystem in cleanup_old_log_files.', 'ERROR' );
			return;
		}

		$files         = $wp_filesystem->dirlist( $log_dir );
		$deleted_count = 0;

		/**
		 * Filters the number of days to retain log entries before they are automatically purged.
		 *
		 * This filter controls the duration for which campaign and activity logs are kept in the
		 * custom database table. Developers can use this to increase or decrease the log retention
		 * period to suit specific store needs (e.g., longer retention for auditing, or shorter
		 * retention to conserve database space on high-volume stores).
		 *
		 * @since 1.0.0
		 * @hook  campaignbay_log_retention_days
		 *
		 * @param int $days_to_keep The default number of days to keep logs (default: 7).
		 * @return int The filtered number of days.
		 */
		$days_to_keep = apply_filters( CAMPAIGNBAY_TEXT_DOMAIN . '_log_retention_days', 7 );
		// Calculate the cutoff timestamp. Any file older than this will be deleted.
		$cutoff_timestamp = time() - ( $days_to_keep * DAY_IN_SECONDS );

		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				// Security: Only process files that end with .log
				if ( '.log' !== substr( $file['name'], -4 ) ) {
					continue;
				}

				$file_path = $log_dir . $file['name'];

				// Check the file's last modified time.
				if ( $wp_filesystem->mtime( $file_path ) < $cutoff_timestamp ) {
					if ( $wp_filesystem->delete( $file_path ) ) {
						++$deleted_count;
						wpab_campaignbay_log( sprintf( 'Deleted old log file: %s', $file['name'] ), 'DEBUG' );
					} else {
						wpab_campaignbay_log( sprintf( 'Failed to delete old log file: %s', $file['name'] ), 'ERROR' );
					}
				}
			}
		}

		wpab_campaignbay_log( sprintf( 'Log cleanup complete. Deleted %d file(s).', $deleted_count ), 'INFO' );
	}

	/**
	 * Register the routes for the objects of the controller.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return void
	 */
	public function register_routes() {
		$namespace = $this->namespace . $this->version;

		register_rest_route(
			$namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_todays_log' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_log_files' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);

		// Route to get a list of all available log files.
		register_rest_route(
			$namespace,
			'/' . $this->rest_base . '/list',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_available_log_files' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
			)
		);

		// Route to get a specific log file by days ago
		register_rest_route(
			$namespace,
			'/' . $this->rest_base . '/(?P<date>[\d]{4}-[\d]{2}-[\d]{2})',
			array(
				'args' => array(
					'date' => array(
						'description' => __( 'The date of the log file in YYYY-MM-DD format.', 'campaignbay' ),
						'type'        => 'string',
						'required'    => true,
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_log_by_date' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Get the contents of a log file from a specific number of days ago.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_log_by_date( $request ) {
		$date = $request->get_param( 'date' );

		// Calculate the target date string based on the integer.
		$target_date = $date;

		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/' . CAMPAIGNBAY_TEXT_DOMAIN . '-logs/';
		$log_file   = $log_dir . 'plugin-log-' . $target_date . '.log';

		return $this->read_and_respond_with_log_content( $log_file, __( 'No logs found.', 'campaignbay' ) );
	}

	/**
	 * Get the contents of TODAY's log file.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_todays_log( $request ) {
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/' . CAMPAIGNBAY_TEXT_DOMAIN . '-logs/';
		// Use gmdate() to always get the UTC date for today.
		$log_file = $log_dir . 'plugin-log-' . gmdate( 'Y-m-d' ) . '.log';

		return $this->read_and_respond_with_log_content( $log_file, __( 'No logs recorded for today.', 'campaignbay' ) );
	}

	/**
	 * Get a list of all available log file dates.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_available_log_files( $request ) {
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/' . CAMPAIGNBAY_TEXT_DOMAIN . '-logs/';
		$log_dates  = array();

		if ( is_dir( $log_dir ) ) {
			$files = scandir( $log_dir, SCANDIR_SORT_DESCENDING ); // Sort newest first.
			foreach ( $files as $file ) {
				if ( preg_match( '/plugin-log-(\d{4}-\d{2}-\d{2})\.log/', $file, $matches ) ) {
					$log_dates[] = $matches[1]; // Extract the YYYY-MM-DD part.
				}
			}
		}

		return new WP_REST_Response( $log_dates, 200 );
	}

	/**
	 * A reusable helper function to read a log file and return a REST response.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param string $log_file_path The full path to the log file.
	 * @param string $not_found_message The message to return if the file doesn't exist.
	 * @return WP_REST_Response|WP_Error
	 */
	private function read_and_respond_with_log_content( $log_file_path, $not_found_message ) {
		if ( file_exists( $log_file_path ) ) {
			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . '/wp-admin/includes/file.php';
				WP_Filesystem();
			}
			$contents = $wp_filesystem->get_contents( $log_file_path );

			if ( false === $contents ) {
				return new WP_Error( 'rest_log_read_error', __( 'Could not read the log file.', 'campaignbay' ), array( 'status' => 500 ) );
			}

			$lines             = explode( "\n", $contents );
			$reversed_lines    = array_reverse( $lines );
			$reversed_contents = implode( "\n", $reversed_lines );

			return new WP_REST_Response( array( 'log_content' => $reversed_contents ), 200 );
		} else {
			return new WP_REST_Response( array( 'log_content' => $not_found_message ), 200 );
		}
	}


	/**
	 * Delete the log files.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_log_files( $request ) {
		$upload_dir = wp_upload_dir();
		$log_dir    = $upload_dir['basedir'] . '/' . CAMPAIGNBAY_TEXT_DOMAIN . '-logs/';

		if ( ! is_dir( $log_dir ) ) {
			// If the directory doesn't exist, there's nothing to clear.
			return new WP_REST_Response( array( 'message' => __( 'Log directory does not exist. Nothing to clear.', 'campaignbay' ) ), 200 );
		}

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$files         = $wp_filesystem->dirlist( $log_dir );
		$deleted_count = 0;

		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				// Security: Only delete files that end with .log
				if ( '.log' === substr( $file['name'], -4 ) ) {
					if ( $wp_filesystem->delete( $log_dir . $file['name'] ) ) {
						++$deleted_count;
					}
				}
			}
		}

		if ( $deleted_count > 0 ) {
			return new WP_REST_Response(
				array(
					'success' => true,
					/* translators: %d: number of log files deleted. */
					'message' => sprintf( _n( '%d log file cleared.', '%d log files cleared.', $deleted_count, 'campaignbay' ), $deleted_count ),
				),
				200
			);
		} else {
			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'No log files to clear.', 'campaignbay' ),
				),
				200
			);
		}
	}
}
