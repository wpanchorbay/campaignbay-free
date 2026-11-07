# WPAnchorBay Black Friday 2026 banner

Shows the Black Friday strip in wp-admin for every site that runs one of our plugins.

## Files

| File | What it does |
|---|---|
| `wpab-banner.html` | The banner (HTML, CSS, inline SVG, fonts built in, no external requests). |
| `wpab-banner-loader.php` | Decides when and where to show it, and remembers when a user closes it. |
| `wpab-banner-uninstall.php` | Removes the "banner closed" flag, but only when the last WPAnchorBay plugin is uninstalled. |

## 1. Add the folder to the plugin

Copy the whole `wpab-banner` folder into the plugin, for example:

```
your-plugin/
  includes/
    wpab-banner/
      wpab-banner.html
      wpab-banner-loader.php
      wpab-banner-uninstall.php
```

Do this in every WPAnchorBay plugin. When several of our plugins are active on one site, the first one that loads wins and the others stay silent, so there is only ever one banner.

## 2. Load it in wp-admin

In the plugin's main file (or wherever its admin code starts):

```php
if ( is_admin() ) {
	require_once __DIR__ . '/includes/wpab-banner/wpab-banner-loader.php';
}
```

That's all for showing it. The loader prints the banner through `admin_notices`.

## 3. Clean up on uninstall

In the plugin's `uninstall.php`. Read the "delete all data" flag first, run the plugin's own cleanup, then call `wpab_banner_uninstall()` last:

```php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/wpab-banner/wpab-banner-uninstall.php';

// 1. Read this plugin's own "delete data on uninstall" flag BEFORE any cleanup.
$delete = (bool) get_option( 'your_plugin_delete_data_on_uninstall' );

// 2. The plugin's own cleanup runs here (it deletes the option above).
// ...

// 3. Only now tell the banner whether this site asked for its data to be deleted.
wpab_banner_uninstall( WP_UNINSTALL_PLUGIN, $delete );
```

Why this order: the plugin's own cleanup deletes the option that holds the flag, so reading it after cleanup always gives `false` and the banner's "closed" flag would never be removed.

The flag is only deleted when no other WPAnchorBay plugin is still installed. If an earlier plugin asked for deletion, the last one to go deletes it.

## How it behaves

- **Who sees it:** administrators only (`manage_options`).
- **When:** 25 Nov 2026 00:00:01 UTC to 5 Dec 2026 23:59:59 UTC (25 Nov 6:00:01 AM to 6 Dec 5:59:59 AM Bangladesh time). Outside this window the banner is not shown, and it also hides itself after the end time if a page was cached.
- **Where:**
  - the Plugins screen;
  - the active plugins' own admin pages (any screen whose id contains the plugin key, for example `campaignbay`);
  - the plugin's tab under WooCommerce > Settings.
  - The main Dashboard is off by default (WordPress.org Guideline 11).
- **Closing it:** the X hides it and saves the choice in user meta `wpab_banner_dismissed`, so it never comes back for that user, on any browser or device.

## Settings you may need

All of them are in `wpab-banner-loader.php` or are filters you can add in the plugin.

| What | How |
|---|---|
| Change the dates | Edit `WPAB_BANNER_STARTS` and `WPAB_BANNER_ENDS` (Unix time, UTC). |
| Run a new campaign later | Change `WPAB_BANNER_CAMPAIGN` (for example `cybermonday26`) and replace `wpab-banner.html`. Users who closed the old one will see the new one. |
| A WooCommerce settings tab slug is not the plugin key | `add_filter( 'wpab_banner_wc_tabs', fn( $tabs ) => $tabs + array( 'campaignbay' => 'campaignbay-settings' ) );` |
| Hide it for Pro users | `add_filter( 'wpab_banner_show', fn( $show ) => $show && ! my_plugin_has_pro() );` |
| Also show on the Dashboard | `add_filter( 'wpab_banner_show_on_dashboard', '__return_true' );` |
| Show on a plugin screen whose id does not contain the plugin key | `wpab_banner_show_on_screen( bool $show, WP_Screen $screen, string[] $slugs )`, see below. |

### `wpab_banner_show_on_screen`

By default the banner appears on a screen only when the screen id contains an active plugin key. A plugin whose admin screen id does not contain its brand key can return `true` for its own screen ids:

```php
add_filter(
	'wpab_banner_show_on_screen',
	function ( $show, $screen, $slugs ) {
		$own_screens = array( 'toplevel_page_my-plugin-dashboard', 'my-menu_page_my-plugin-reports' );
		return $show || in_array( $screen->id, $own_screens, true );
	},
	10,
	3
);
```

## Important

All plugins must ship the **same version** of these three files for a campaign, because whichever plugin loads first is the one that prints the banner.
