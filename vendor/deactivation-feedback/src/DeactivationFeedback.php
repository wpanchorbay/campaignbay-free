<?php

namespace WPAB\DeactivationFeedback;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shows a feedback modal when a specific plugin is deactivated from
 * the Plugins screen, instead of letting WordPress deactivate it
 * immediately. Never blocks deactivation — skipping or a failed
 * AJAX request both fall through to normal deactivation.
 */
class DeactivationFeedback {

    /** @var array */
    protected $config;

    public function __construct( array $config = [] ) {
        $config = wp_parse_args( $config, $this->defaults() );

        if ( empty( $config['plugin_file'] ) || empty( $config['plugin_slug'] ) ) {
            if ( function_exists( '_doing_it_wrong' ) ) {
                _doing_it_wrong(
                    __CLASS__,
                    'Both "plugin_file" and "plugin_slug" are required config keys.',
                    '1.0.0'
                );
            }
            return; // Refuse to run half-configured — no assets, no AJAX handler, no modal.
        }

        $this->config = $this->namespace_config( $config );

        add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_assets' ] );
        add_action( 'admin_footer', [ $this, 'maybe_render_modal' ] );

        ( new Ajax( $this->config ) )->register();

        if ( ! empty( $this->config['review_notice_enabled'] ) ) {
            ( new ReviewNotice( $this->config ) )->register();
        }
    }

    protected function defaults() {
        return [
            // Required: plugin_basename( __FILE__ ) from your main plugin file.
            'plugin_file'     => '',
            'plugin_name'     => 'This plugin',

            // Required: a short, stable, unique identifier for this plugin
            // (e.g. 'optionbay'). Used to namespace ajax_action/nonce_action/
            // option_name so several plugins can share this package on one
            // site without colliding. Set this explicitly rather than relying
            // on plugin_file's folder name, which can change (renamed,
            // forked, symlinked, or installed differently per environment).
            'plugin_slug'     => '',

            // Optional overrides — leave blank to auto-derive from plugin_slug.
            'ajax_action'     => '',
            'nonce_action'    => '',
            'option_name'     => '',

            // Namespaced asset handle so plugins don't conflict.
            'asset_handle'    => '',

            // Local storage (wp_options) of every response.
            'save_locally'    => true,

            // Optional: forward each response to a remote server too.
            // Leave empty to disable. Request is fire-and-forget (non-blocking).
            'remote_endpoint' => '',
            'remote_api_key'  => '',

            // Copy.
            'modal_title'     => 'Quick question before you go',
            'modal_subtitle'  => "If you have a moment, let us know why you're deactivating so we can improve.",
            'cancel_label'    => 'Cancel',
            'skip_label'      => 'Skip & Deactivate',
            'submit_label'    => 'Submit & Deactivate',

            // array of ['id'=>string, 'label'=>string, 'has_input'=>bool, 'placeholder'=>string]
            'reasons'         => [],

            // Review/Report Notice Config
            'review_notice_enabled' => false,
            'review_delay_days'     => 3,
            'review_snooze_days'    => 7,
            'review_url'            => '',
            'support_url'           => '',
            'review_notice_title'   => 'Enjoying {plugin_name}?',
            'review_notice_message' => "You've been using {plugin_name} for a few days. If it's been helpful, we'd love a review!",
        ];
    }

    /**
     * Fills in ajax_action/nonce_action/option_name from plugin_slug
     * whenever they're left blank, so each of several plugins sharing this
     * package on one site gets unique values just by setting plugin_slug.
     */
    protected function namespace_config( array $config ) {
        $slug = sanitize_key( $config['plugin_slug'] );
        $config['plugin_slug'] = $slug;

        if ( empty( $config['ajax_action'] ) ) {
            $config['ajax_action'] = 'wpab_deactivation_feedback_' . $slug;
        }

        if ( empty( $config['nonce_action'] ) ) {
            $config['nonce_action'] = $config['ajax_action'] . '_nonce';
        }

        if ( empty( $config['option_name'] ) ) {
            $config['option_name'] = 'wpab_deactivation_feedback_log_' . $slug;
        }

        if ( empty( $config['asset_handle'] ) ) {
            $config['asset_handle'] = 'wpab_deactivation_feedback_' . $slug;
        }

        return $config;
    }

    protected function is_plugins_screen() {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        return $screen && in_array( $screen->id, [ 'plugins', 'plugins-network' ], true );
    }

    public function maybe_enqueue_assets( $hook_suffix ) {
        error_log('DeactivationFeedback: maybe_enqueue_assets called with hook_suffix: ' . $hook_suffix);
        if ( 'plugins.php' !== $hook_suffix ) {
            return;
        }

        $handle = $this->config['asset_handle'];

        if ( ! wp_style_is( $handle, 'registered' ) ) {
            wp_register_style(
                $handle,
                plugins_url( '../assets/css/deactivation-feedback.css', __FILE__ ),
                [],
                '1.0.0'
            );
        }

        if ( ! wp_script_is( $handle, 'registered' ) ) {
            wp_register_script(
                $handle,
                plugins_url( '../assets/js/deactivation-feedback.js', __FILE__ ),
                [],
                '1.0.0',
                true
            );
        }

        wp_enqueue_style( $handle );
        wp_enqueue_script( $handle );

        $inline = sprintf(
            'document.addEventListener("DOMContentLoaded",function(){window.DeactivationFeedback.init(%s);});',
            wp_json_encode(
                [
                    'pluginFile' => $this->config['plugin_file'],
                    'modalId'    => $this->modal_id(),
                    'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
                    'action'     => $this->config['ajax_action'],
                    'nonce'      => wp_create_nonce( $this->config['nonce_action'] ),
                ]
            )
        );

        // Unique key per-instance so multiple plugins sharing this asset don't overwrite each other's inline script.
        wp_add_inline_script( $handle, $inline );
    }

    public function maybe_render_modal() {
        error_log('DeactivationFeedback: maybe_render_modal called. is_plugins_screen: ' . ($this->is_plugins_screen() ? 'true' : 'false'));
        if ( ! $this->is_plugins_screen() ) {
            return;
        }

        $modal_id = $this->modal_id();
        ?>
        <div id="<?php echo esc_attr( $modal_id ); ?>" class="df-modal-overlay" style="display:none;">
            <div class="df-modal" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $modal_id ); ?>-title">
                <button type="button" class="df-close" aria-label="Close">&times;</button>
                <h2 id="<?php echo esc_attr( $modal_id ); ?>-title"><?php echo esc_html( $this->config['modal_title'] ); ?></h2>
                <p><?php echo esc_html( $this->config['modal_subtitle'] ); ?></p>

                <form class="df-modal-form">
                    <?php foreach ( $this->config['reasons'] as $reason ) : ?>
                        <label class="df-reason">
                            <input
                                type="radio"
                                name="df_reason"
                                value="<?php echo esc_attr( $reason['id'] ); ?>"
                                <?php echo ! empty( $reason['has_input'] ) ? 'data-has-input="1"' : ''; ?>
                            >
                            <?php echo esc_html( $reason['label'] ); ?>
                        </label>
                        <?php if ( ! empty( $reason['has_input'] ) ) : ?>
                            <input
                                type="text"
                                class="df-reason-input"
                                data-for="<?php echo esc_attr( $reason['id'] ); ?>"
                                placeholder="<?php echo esc_attr( $reason['placeholder'] ?? '' ); ?>"
                                style="display:none;"
                            >
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <div class="df-modal-actions">
                        <button type="button" class="button df-skip"><?php echo esc_html( $this->config['skip_label'] ); ?></button>
                        <button type="submit" class="button button-primary df-submit"><?php echo esc_html( $this->config['submit_label'] ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    protected function modal_id() {
        return 'df-modal-' . sanitize_key( $this->config['ajax_action'] );
    }
}
