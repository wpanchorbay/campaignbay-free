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

That's all for showing it. The loader prints the banner through `admin_notices`, except on WooCommerce > Settings, where WooCommerce Admin hides everything printed on `admin_notices`; there it prints on `all_admin_notices` (after WooCommerce's hidden wrapper) so it shows at the top of the content area. The banner clears the floated Screen Options / Help tabs, so on the Plugins screen it takes the full row below them.

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
- **When:** 12 Nov 2026 08:00:00 UTC to 20 Dec 2026 23:59:59 UTC (12 Nov 2:00:00 PM to 21 Dec 5:59:59 AM Bangladesh time). Outside this window the banner is not shown, and it also hides itself after the end time if a page was cached.
- **Where:**
  - the Plugins screen;
  - the active plugins' own admin pages (any screen whose id contains the plugin key, for example `campaignbay`);
  - the plugin's tab under WooCommerce > Settings (`tab=<plugin key>`), or its section under the Products tab (`tab=products&section=<plugin key>`, as OptionBay uses). The Products tab on its own has no banner.
  - The main Dashboard is off by default (WordPress.org Guideline 11).
- **Closing it:** the X hides it and saves the close time in user meta `wpab_banner_dismissed`, so it stays closed for that user on every browser and device until the next comeback date in `WPAB_BANNER_RESHOW`. Each user is separate.
- **Comeback dates:** once one of these times passes, the banner shows again for every user who closed it before then:

  | Date (UTC) | Bangladesh | New York | Why |
  |---|---|---|---|
  | Thu 19 Nov 2026, 05:00 | 11:00 AM | 12:00 AM | One week into the sale |
  | Tue 24 Nov 2026, 05:00 | 11:00 AM | 12:00 AM | Thanksgiving week |
  | Thu 26 Nov 2026, 17:00 | 11:00 PM | 12:00 PM | 12 hours before Black Friday (27 Nov) |
  | Sun 29 Nov 2026, 17:00 | 11:00 PM | 12:00 PM | 12 hours before Cyber Monday (30 Nov) |
  | Mon 7 Dec 2026, 05:00 | 11:00 AM | 12:00 AM | Start of the second week of December |
  | Mon 14 Dec 2026, 05:00 | 11:00 AM | 12:00 AM | Last full week of the sale |
  | Fri 18 Dec 2026, 05:00 | 11:00 AM | 12:00 AM | The last three days of the sale |

## Settings you may need

All of them are in `wpab-banner-loader.php` or are filters you can add in the plugin.

| What | How |
|---|---|
| Change the dates | Edit `WPAB_BANNER_STARTS` and `WPAB_BANNER_ENDS` (Unix time, UTC). |
| Change the comeback dates | Edit `WPAB_BANNER_RESHOW` (Unix times, UTC). A user who closed the banner sees it again once a listed time passes. |
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

## For AI agents and automation

Read this before touching these files or wiring the banner into a plugin.

**Rules**

1. **The four files are one unit and must be byte-identical in every plugin.** Never edit a copy inside a plugin. Change the canonical copy, then copy it into every plugin and compare with `sha256sum`. Whichever plugin loads first prints the banner, so an out-of-date copy anywhere shows the old banner or the old dates.
2. **Load it at file scope, guarded by `is_admin()`**, in the plugin's main file. Do not hook it, and do not put it inside a class or a function. The dismiss handler (`wp_ajax_wpab_dismiss_banner`) has to register on admin-ajax requests, and a late hook would miss them.
3. **Uninstall order matters.** Read the plugin's own "delete data on uninstall" flag before its cleanup runs, then call `wpab_banner_uninstall( WP_UNINSTALL_PLUGIN, $delete )` last. See section 3.
4. **Packaging and linting.** Make sure the plugin's release script copies `includes/` into the zip, and exclude `*/includes/wpab-banner/*` from phpcs so a formatter never rewrites the shared files.
5. **Do not change dates or placement per plugin.** Dates live in `WPAB_BANNER_STARTS`, `WPAB_BANNER_ENDS` and `WPAB_BANNER_RESHOW`. To make the banner appear on a plugin screen that the default rules miss, use the `wpab_banner_show_on_screen` filter from the plugin's own code, not an edit to the loader.
6. **Test dates without editing shipped files.** Use a temporary mu-plugin that defines `WPAB_BANNER_STARTS` and `WPAB_BANNER_ENDS` before the plugins load (see `dev/wpab-banner-test.php` in the repo), and delete it afterwards. The loader defines both constants unconditionally, so a test override that loads after it prints a "constant already defined" warning.
7. **Do not add CSS or JS for the banner outside `wpab-banner.html`.** It is self-contained on purpose (no external requests, fonts built in).

**Check that an integration is done**

```bash
# the four files match the canonical copy
sha256sum includes/wpab-banner/*

# the loader is required at file scope, guarded by is_admin()
grep -n "wpab-banner-loader.php" *.php

# uninstall reads the flag first and calls wpab_banner_uninstall() last
grep -n "wpab_banner_uninstall\|wpab-banner-uninstall" uninstall.php

# the dates are the campaign dates
grep -n "WPAB_BANNER_STARTS'\|WPAB_BANNER_ENDS'" includes/wpab-banner/wpab-banner-loader.php

# syntax
php -l includes/wpab-banner/wpab-banner-loader.php
```

Then check in a browser as an administrator, inside the date window: the banner shows once on the Plugins screen and on the plugin's own pages, shows on its WooCommerce settings tab if it has one, is absent from the Dashboard, and stays gone after you close it and reload (until the next comeback date). Do not run a plugin's real `uninstall.php` on a working site to test it; call `wpab_banner_uninstall()` directly instead.

## Important

All plugins must ship the **same version** of these files for a campaign, because whichever plugin loads first is the one that prints the banner.
