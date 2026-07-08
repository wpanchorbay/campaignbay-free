<?php

namespace WPAB\DeactivationFeedback;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ajax {

    /** @var array */
    protected $config;

    public function __construct( array $config ) {
        $this->config = $config;
    }

    public function register() {
        add_action( 'wp_ajax_' . $this->config['ajax_action'], [ $this, 'handle' ] );
    }

    public function handle() {
        check_ajax_referer( $this->config['nonce_action'], 'nonce' );

        if ( ! current_user_can( 'activate_plugins' ) ) {
            wp_send_json_error( [ 'message' => 'Insufficient permissions' ], 403 );
        }

        $reason  = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
        $details = isset( $_POST['details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['details'] ) ) : '';

        if ( '' === $reason ) {
            wp_send_json_error( [ 'message' => 'No reason provided' ], 400 );
        }

        $current_user = wp_get_current_user();

        $entry = [
            'plugin_slug' => $this->config['plugin_slug'],
            'plugin_file' => $this->config['plugin_file'],
            'reason'      => $reason,
            'details'     => $details,
            'site_url'    => home_url(),
            'user_email'  => $current_user ? $current_user->user_email : '',
            'wp_version'  => get_bloginfo( 'version' ),
            'timestamp'   => current_time( 'mysql' ),
        ];

        if ( ! empty( $this->config['save_locally'] ) ) {
            $this->save_locally( $entry );
        }

        if ( ! empty( $this->config['remote_endpoint'] ) ) {
            $this->send_remote( $entry );
        }

        wp_send_json_success();
    }

    protected function save_locally( array $entry ) {
        $option_name = $this->config['option_name'];
        $log         = get_option( $option_name, [] );

        if ( ! is_array( $log ) ) {
            $log = [];
        }

        $log[] = $entry;

        // Cap stored history so the option doesn't grow unbounded.
        if ( count( $log ) > 200 ) {
            $log = array_slice( $log, -200 );
        }

        update_option( $option_name, $log, false );
    }

    protected function send_remote( array $entry ) {
        $args = [
            'timeout'  => 5,
            'blocking' => false, // Fire-and-forget so it can never slow down deactivation.
            'body'     => wp_json_encode( $entry ),
            'headers'  => [ 'Content-Type' => 'application/json' ],
        ];

        if ( ! empty( $this->config['remote_api_key'] ) ) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->config['remote_api_key'];
        }

        wp_remote_post( $this->config['remote_endpoint'], $args );
    }
}
