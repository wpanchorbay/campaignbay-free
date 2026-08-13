# OptionBay Deactivation Feedback

PSR-4 package that shows a feedback modal when a plugin is deactivated
from the Plugins screen, instead of deactivating immediately. Skipping
the modal, or a failed AJAX request, both fall through to normal
deactivation — it never blocks the action.

## Getting it into your plugin's `vendor/`

This package isn't on Packagist, so the cleanest way to pull it into
your existing PSR-4 setup is a **path repository** pointing at wherever
you keep this folder (e.g. inside your plugin repo under `packages/`,
or a separate git repo you maintain).

1. Put this folder somewhere accessible, e.g.:
   ```
   your-plugin/
     packages/deactivation-feedback/   <- this package
     src/
     vendor/
     composer.json
   ```

2. In your plugin's root `composer.json`, add a path repository and
   require it:
   ```json
   {
       "repositories": [
           {
               "type": "path",
               "url": "packages/deactivation-feedback"
           }
       ],
       "require": {
           "wpab/deactivation-feedback": "*"
       }
   }
   ```

3. Run:
   ```bash
   composer update wpab/deactivation-feedback
   ```
   Composer will symlink (or copy, with `"symlink": false` in the
   repository config) it into `vendor/wpab/deactivation-feedback`
   and regenerate the autoloader — `WPAB\DeactivationFeedback\...`
   classes are then available anywhere `vendor/autoload.php` is loaded.

   If you'd rather version it independently, push this folder to its
   own git repo and use a `"type": "vcs"` repository instead of
   `"type": "path"`, requiring a tagged version.

### Manual alternative (no path repository)

You can also just copy this folder directly to
`vendor/wpab/deactivation-feedback/` and run `composer dump-autoload`
once — Composer's PSR-4 autoloader picks up any package present under
`vendor/` as long as it's declared in `vendor/composer/installed.json`
(a plain copy without that won't be picked up automatically). The path
repository approach above handles this bookkeeping for you, so it's
the more reliable option long-term.

## Usage

See `examples/usage.php` for a full example with the reasons list
recommended for OptionBay. Minimal version:

```php
require_once __DIR__ . '/vendor/autoload.php';

use WPAB\DeactivationFeedback\DeactivationFeedback;

add_action( 'plugins_loaded', function () {
    new DeactivationFeedback( [
        'plugin_file' => plugin_basename( __FILE__ ),
        'plugin_slug' => 'optionbay', // required, keep this stable and unique per plugin
        'reasons'     => [
            [ 'id' => 'not_working', 'label' => "I couldn't get it working", 'has_input' => true, 'placeholder' => 'What were you trying to configure?' ],
            [ 'id' => 'other', 'label' => 'Other', 'has_input' => true ],
        ],
    ] );
} );
```

`plugin_slug` is required and namespaces `ajax_action`, `nonce_action`,
and `option_name` automatically. Dropping this into 10 different plugins
just means giving each one its own `plugin_slug` — everything else stays
unique and collision-free without extra config. If `plugin_file` or
`plugin_slug` is missing, the class calls `_doing_it_wrong()` (visible
with `WP_DEBUG` on) and does nothing — no assets, no AJAX handler, no
modal — rather than silently guessing.

## Config reference

## Configuration Options

Here's an example of a fully configured setup, including the optional review notice feature:

```php
new \WPAB\DeactivationFeedback\DeactivationFeedback([
    'plugin_file'     => 'my-plugin/my-plugin.php',
    'plugin_slug'     => 'my-plugin',
    'remote_endpoint' => 'https://example.com/wp-json/wpab/v1/feedback',
    
    // The options shown in the deactivation modal
    'reasons' => [
        [
            'id'        => 'no_longer_needed',
            'label'     => 'I no longer need the plugin',
            'has_input' => false,
        ],
        [
            'id'          => 'missing_feature',
            'label'       => 'It is missing a feature I need',
            'has_input'   => true,
            'placeholder' => 'What feature is missing?',
        ],
    ],

    // Review & Report Notice Config (Optional)
    // Asks active users for a review after they've used the plugin for a while.
    'review_notice_enabled' => true,
    'review_delay_days'     => 3,
    'review_snooze_days'    => 7,
    'review_url'            => 'https://wordpress.org/support/plugin/my-plugin/reviews/#new-post',
    'support_url'           => 'https://wordpress.org/support/plugin/my-plugin/',
    'review_notice_title'   => 'Enjoying {plugin_name}?',
    'review_notice_message' => "You've been using {plugin_name} for a few days now. If it's been helpful, would you mind leaving a quick review? It really helps!",
]);
```

*Note: For the review notice to calculate the delay correctly, you must save the activation timestamp when your plugin is activated. Add this to your plugin's activation hook:*
```php
if ( ! get_option( 'wpab_activated_at_my-plugin' ) ) {
    update_option( 'wpab_activated_at_my-plugin', time(), false );
}
```

| Key | Required | Description |
|---|---|---|
| `plugin_file` | yes | `plugin_basename( __FILE__ )` of your main plugin file |
| `plugin_slug` | yes | Short, stable, unique identifier for this plugin (e.g. `optionbay`). Namespaces `ajax_action`/`nonce_action`/`option_name`. Set this deliberately — don't rely on folder names, which can change. |
| `ajax_action` | no | Auto-derived as `wpab_deactivation_feedback_{plugin_slug}`. Override only if you need a specific name. |
| `nonce_action` | no | Auto-derived as `{ajax_action}_nonce`. |
| `option_name` | no | Auto-derived as `wpab_deactivation_feedback_log_{slug}` — keeps each plugin's local feedback log separate. |
| `asset_handle` | no | Auto-derived **per plugin** as `wpab_deactivation_feedback_{slug}`, so every instance registers and loads its own copy of the JS/CSS. |
| `save_locally` | no | Store responses in `wp_options` (default `true`) |
| `remote_endpoint` | no | URL to POST each response to (JSON body). Leave empty to skip. Point it at the receiver's full REST route (`.../wp-json/wpab/v1/feedback`) — the bare origin returns 400, and because the POST is non-blocking you will never see the failure. |
| `remote_api_key` | no | Sent as `Authorization: Bearer {key}` if set |
| `modal_title` / `modal_subtitle` | no | Modal copy |
| `skip_label` / `submit_label` | no | Button labels |
| `disclosure_label` | no | Label of the toggle that reveals the disclosure (default `What information is sent?`) |
| `disclosure_text` | no | Data-collection disclosure shown above the buttons. Set an empty string to omit the whole block — but if `remote_endpoint` is set, shipping without a disclosure puts you on the wrong side of WordPress.org guideline 7. Keep the wording in sync with what `Ajax::handle()` actually sends. |
| `reasons` | yes | Array of `['id', 'label', 'has_input', 'placeholder']` |

## How it works

- Assets only load on `plugins.php` (checked via the `admin_enqueue_scripts`
  hook suffix), not site-wide.
- The deactivate link is targeted via WordPress core's own
  `<tr data-plugin="...">` attribute on the plugin's row — no fragile
  ID-guessing.
- The click is intercepted client-side (`preventDefault`), the modal
  opens, and the original deactivate URL is followed afterward —
  either immediately (skip) or after the AJAX save resolves/fails
  (submit).
- Remote POSTs are fire-and-forget (`blocking => false`) so a slow or
  down server never delays deactivation. The flip side: a wrong URL, a
  404, or a dead host is completely invisible. Verify delivery by
  checking for a row on the receiver, never by reading the client code.
- Nothing is transmitted unless the user presses submit. "Skip &
  Deactivate", the close button, and the overlay all deactivate without
  sending anything.

## The disclosure block

`disclosure_text` renders above the action buttons behind a
keyboard-focusable toggle, and the submit button is `aria-describedby`-linked
to it. The text is **always** present in the DOM; the collapsed state
clips it visually rather than using `display:none`, because a
`display:none` element is dropped from the accessibility tree and that
would silently sever the `aria-describedby` reference. Don't "optimise"
that back into `display:none`.

## Note on running this in several plugins at once

Each plugin carries its own copy of this package in its own `vendor/`,
and `asset_handle` is derived per-slug, so every instance registers and
enqueues its own JS/CSS. Several instances on one `plugins.php` load are
independent — no shared handle, no first-one-wins, and no requirement
that the copies stay in version lockstep. The cost is one extra small
JS/CSS pair per active instance.
