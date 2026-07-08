<?php

namespace WPAB\DeactivationFeedback;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles rendering an admin notice asking for a review or bug report,
 * after the plugin has been active for a certain number of days.
 */
class ReviewNotice {

    /** @var array */
    protected $config;

    public function __construct( array $config ) {
        $this->config = $config;
    }

    public function register() {
        add_action( 'admin_notices', [ $this, 'maybe_render_notice' ] );
        add_action( 'wp_ajax_wpab_dismiss_review_' . $this->config['plugin_slug'], [ $this, 'handle_dismiss_ajax' ] );
    }

    public function maybe_render_notice() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        $slug           = $this->config['plugin_slug'];
        $activated_at   = get_option( 'wpab_activated_at_' . $slug );
        $dismissed_time = get_user_meta( get_current_user_id(), 'wpab_review_dismissed_' . $slug, true );

        // If never activated or activated recently, do not show.
        if ( ! $activated_at ) {
            return;
        }

        $delay_seconds = (int) $this->config['review_delay_days'] * DAY_IN_SECONDS;
        if ( time() < ( $activated_at + $delay_seconds ) ) {
            return;
        }

        // If dismissed permanently, do not show.
        if ( 'permanent' === $dismissed_time ) {
            return;
        }

        // If snoozed, check if snooze time has passed.
        if ( $dismissed_time && is_numeric( $dismissed_time ) ) {
            $snooze_seconds = (int) $this->config['review_snooze_days'] * DAY_IN_SECONDS;
            if ( time() < ( (int) $dismissed_time + $snooze_seconds ) ) {
                return;
            }
        }

        $this->render_notice();
    }

    protected function render_notice() {
        $title       = str_replace( '{plugin_name}', $this->config['plugin_name'], $this->config['review_notice_title'] );
        $message     = str_replace( '{plugin_name}', $this->config['plugin_name'], $this->config['review_notice_message'] );
        $ajax_action = 'wpab_dismiss_review_' . $this->config['plugin_slug'];
        $nonce       = wp_create_nonce( $ajax_action . '_nonce' );
        $wrapper_id  = 'wpab-review-notice-' . esc_attr( $this->config['plugin_slug'] );
        ?>
        <div id="<?php echo esc_attr( $wrapper_id ); ?>" class="notice notice-info is-dismissible" style="border-left-color: #2271b1; padding: 15px; display: flex; flex-direction: column; gap: 10px;">
            <div style="font-size: 16px; font-weight: 600;"><?php echo esc_html( $title ); ?></div>
            <div style="font-size: 14px; margin-bottom: 5px;"><?php echo esc_html( $message ); ?></div>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <?php if ( ! empty( $this->config['review_url'] ) ) : ?>
                    <a href="<?php echo esc_url( $this->config['review_url'] ); ?>" target="_blank" class="button button-primary wpab-review-action" data-type="permanent" style="background: #2271b1; border-color: #2271b1;">
                        <?php esc_html_e( 'Leave a Review ★', 'wpab-deactivation-feedback' ); ?>
                    </a>
                <?php endif; ?>

                <?php if ( ! empty( $this->config['support_url'] ) ) : ?>
                    <a href="<?php echo esc_url( $this->config['support_url'] ); ?>" target="_blank" class="button button-secondary wpab-review-action" data-type="permanent">
                        <?php esc_html_e( 'Report an Issue', 'wpab-deactivation-feedback' ); ?>
                    </a>
                <?php endif; ?>

                <a href="#" class="wpab-review-action" data-type="snooze" style="text-decoration: none; margin-left: 10px;">
                    <?php esc_html_e( 'Maybe Later', 'wpab-deactivation-feedback' ); ?>
                </a>
                
                <a href="#" class="wpab-review-action" data-type="permanent" style="text-decoration: none; margin-left: 10px; color: #a00;">
                    <?php esc_html_e( 'I already did / Don\'t show again', 'wpab-deactivation-feedback' ); ?>
                </a>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var wrapper = document.getElementById('<?php echo esc_js( $wrapper_id ); ?>');
                    if (!wrapper) return;

                    var actions = wrapper.querySelectorAll('.wpab-review-action');
                    actions.forEach(function(btn) {
                        btn.addEventListener('click', function(e) {
                            var isLink = btn.tagName.toLowerCase() === 'a' && btn.getAttribute('href') !== '#';
                            if (!isLink) {
                                e.preventDefault();
                            }

                            wrapper.style.display = 'none';

                            var formData = new FormData();
                            formData.append('action', '<?php echo esc_js( $ajax_action ); ?>');
                            formData.append('nonce', '<?php echo esc_js( $nonce ); ?>');
                            formData.append('type', btn.getAttribute('data-type'));

                            fetch(ajaxurl, {
                                method: 'POST',
                                body: formData
                            });
                        });
                    });

                    // Handle standard WP dismiss button (the "X")
                    var wpDismissBtn = wrapper.querySelector('.notice-dismiss');
                    if (wpDismissBtn) {
                        wpDismissBtn.addEventListener('click', function() {
                            var formData = new FormData();
                            formData.append('action', '<?php echo esc_js( $ajax_action ); ?>');
                            formData.append('nonce', '<?php echo esc_js( $nonce ); ?>');
                            formData.append('type', 'snooze');

                            fetch(ajaxurl, {
                                method: 'POST',
                                body: formData
                            });
                        });
                    }
                });
            </script>
        </div>
        <?php
    }

    public function handle_dismiss_ajax() {
        $ajax_action = 'wpab_dismiss_review_' . $this->config['plugin_slug'];
        
        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), $ajax_action . '_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }

        if ( ! current_user_can( 'activate_plugins' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'snooze';
        $meta_key = 'wpab_review_dismissed_' . $this->config['plugin_slug'];

        if ( 'permanent' === $type ) {
            update_user_meta( get_current_user_id(), $meta_key, 'permanent' );
        } else {
            update_user_meta( get_current_user_id(), $meta_key, time() );
        }

        wp_send_json_success();
    }
}
