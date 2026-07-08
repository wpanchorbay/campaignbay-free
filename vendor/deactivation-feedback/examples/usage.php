<?php
/**
 * Example: how to initialize the package from your main plugin file
 * (e.g. optionbay.php), AFTER requiring vendor/autoload.php.
 */

use WPAB\DeactivationFeedback\DeactivationFeedback;

add_action( 'plugins_loaded', function () {

    new DeactivationFeedback(
        [
            'plugin_file'     => plugin_basename( __FILE__ ), // e.g. optionbay-product-options-addons-woo/optionbay.php
            'plugin_name'     => 'OptionBay',
            'plugin_slug'     => 'optionbay', // required — keep this stable, don't derive it from the folder name

            // ajax_action, nonce_action, and option_name are all auto-derived from
            // plugin_slug above, so you don't need to set them separately.

            'save_locally'    => true,

            // Optional — leave blank to only store locally.
            'remote_endpoint' => 'https://your-server.com/api/deactivation-feedback',
            'remote_api_key'  => 'your-secret-token',

            'modal_title'     => 'Quick question before you go',
            'modal_subtitle'  => "If you have a moment, let us know why you're deactivating OptionBay so we can improve it.",
            'skip_label'      => 'Skip & Deactivate',
            'submit_label'    => 'Submit & Deactivate',

            'reasons'         => [
                [
                    'id'          => 'not_working',
                    'label'       => "I couldn't get it working",
                    'has_input'   => true,
                    'placeholder' => 'What were you trying to configure?',
                ],
                [
                    'id'          => 'missing_feature',
                    'label'       => "It's missing a feature I need",
                    'has_input'   => true,
                    'placeholder' => 'What feature?',
                ],
                [
                    'id'          => 'conflict',
                    'label'       => 'It conflicted with my theme or another plugin',
                    'has_input'   => true,
                    'placeholder' => 'Which theme/plugin?',
                ],
                [
                    'id'        => 'slow',
                    'label'     => 'It slowed down my site',
                    'has_input' => false,
                ],
                [
                    'id'          => 'alternative',
                    'label'       => 'I found a better alternative',
                    'has_input'   => true,
                    'placeholder' => 'Which plugin?',
                ],
                [
                    'id'          => 'complicated',
                    'label'       => "It's too complicated to set up",
                    'has_input'   => true,
                    'placeholder' => 'What part was confusing?',
                ],
                [
                    'id'        => 'no_longer_need',
                    'label'     => 'I no longer need product options on my store',
                    'has_input' => false,
                ],
                [
                    'id'        => 'temporary',
                    'label'     => "It's temporary — I'm troubleshooting/debugging",
                    'has_input' => false,
                ],
                [
                    'id'          => 'other',
                    'label'       => 'Other',
                    'has_input'   => true,
                    'placeholder' => 'Tell us more',
                ],
            ],
        ]
    );

} );
