# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

CampaignBay is the **free** WooCommerce discount/flash-sale plugin (WordPress.org slug `campaignbay`). It requires WooCommerce active to run (checked in `campaignbay.php` via `is_plugin_active( 'woocommerce/woocommerce.php' )`) and provides four campaign types out of the box: Scheduled discounts, Quantity (tiered) discounts, Early Bird discounts, and BOGO (Buy One Get One). A paid **Pro add-on**, `campaignbaypro`, lives in a sibling directory (typically `../campaignbaypro`) and cannot run standalone — it hooks into this plugin's classes and filters rather than duplicating logic. When changing shared behavior (the discount engine, `Condition`, settings schema, cart pipeline), check whether `../campaignbaypro` has an equivalent class or hook consumer that also needs updating.

## PHP autoloading

PHP autoloading is a hand-rolled `spl_autoload_register` in `campaignbay.php` (no Composer/PSR-4 autoloader, no `composer.json`): classes under `WpabCampaignBay\*` map to `app/<Namespace path>.php`, and classes under `WPAB\DeactivationFeedback\*` map to the vendored `vendor/deactivation-feedback/src/`. `vendor/` is committed (not gitignored/installed) since there's no Composer step.

## Architecture

**Bootstrap** (`campaignbay.php`): defines `CAMPAIGNBAY_*` constants, registers the autoloader, and on `plugins_loaded` instantiates `WpabCampaignBay\Core\Plugin::get_instance()->run()` — but only if WooCommerce is active; otherwise it just shows an admin notice. Activation/deactivation hooks call `Core\Activator`/`Core\Deactivator`. A vendored `DeactivationFeedback` widget (in `vendor/deactivation-feedback/`) posts optional feedback to `dfs.wpanchorbay.com` when the plugin is deactivated, and a review-reminder notice is scheduled from the same activation timestamp.

**Hook registration pattern**: almost every service class extends `Core\Base`, an abstract singleton (`get_instance()` keyed by `static::class`) that lets a subclass declare `add_action()`/`add_filter()` calls in its constructor into a local `$hooks` array instead of calling WordPress's `add_action`/`add_filter` directly. `Core\Plugin::define_core_hooks()` instantiates each service (`DiscountManager`, `CampaignManager`, `PricingEngine`, `OrderManager`, `Scheduler`, `CalenderManager`), pulls their `get_hooks()`, and registers everything through `Helper\Loader` (a simple actions/filters collector with a `run()` that calls the real WP hook functions). New backend services should follow this `Base`-subclass + `define_hooks()` pattern rather than hooking WordPress directly in a constructor.

**Data model**: two custom tables created by `Data\DbManager` on activation:
- `{prefix}campaignbay_campaigns` — one row per campaign: `type` (`scheduled|quantity|earlybird|bogo`; Pro adds more), `discount_type`/`discount_value` for simple campaigns, JSON columns `tiers` (per-tier rules for quantity/earlybird/bogo), `conditions` (targeting rule tree), `settings` (per-campaign display/behavior options), plus `target_type`/`target_ids`, scheduling fields, and `usage_count`/`usage_limit`.
- `{prefix}campaignbay_logs` — analytics/activity log rows keyed by `campaign_id`/`order_id`, used by the Dashboard and Activity Log REST endpoints.

`Core\Campaign` is the model/gateway for that table (`create()`, `update()`, `delete()`, `set_status()`, `increment_usage_count()`/`decrement_usage_count()`, JSON-decoding accessors). Validation is layered: `Core\Validator` runs declarative rule strings (e.g. `'required|in:...'`, `'required_if:...'`) against top-level fields, tier arrays, `settings`, and `conditions` separately, each with its own filterable rule set (`campaignbay_get_campaign_validation_rules`, `campaignbay_get_tiers_validation_rules`, `campaignbay_get_settings_validation_rules`, `campaignbay_get_conditions_validation_rules`) — this is one of the main Pro extension points for adding new campaign types or condition types.

**Discount engine** (`app/Engine/`): split into product-level and cart-level pipelines.
- `DiscountManager` hooks `campaignbay_get_product` and delegates to `Engine\ProductDiscount::create($product)->apply_discounts()`, which loops active campaigns (`CampaignManager::get_active_campaigns()`), applies `scheduled`/`earlybird` pricing natively, and for any other campaign `type` fires `campaignbay_product_campaign_discount_calculation` so Pro/third-party code can compute its own price and compete for "best price" (governed by the `product_priorityMethod` setting: `apply_highest`/`apply_lowest`/`apply_first`). Variable products are handled by rolling up each variation's already-computed `campaignbay` product meta. The final discount metadata is stored as product meta (`campaignbay`) and is filterable via `campaignbay_product_discount_meta`.
- `Engine\CartDiscount::calculate_discounts()` (called from `woocommerce_before_calculate_totals`) is the cart-level worker: it reads each cart item's `campaignbay` meta (via `get_cart_discount()`, itself filterable at `campaignbay_get_cart_discount`), applies quantity-tier pricing (as line-total price change, a virtual coupon, or a negative fee depending on the campaign's `apply_as` setting), and adds BOGO free items as **separate, clearly-flagged cart line items** (`is_campaignbay_free_product`) rather than mutating quantities — a deliberate architecture change (see `README.md`/changelog) so multiple stacked BOGO campaigns each show their own line and their own custom message. Discounts that aren't a direct price change go through `Helper\Helper::add_data()`/`CartDiscount::add_fee()` and are ultimately applied via fake/virtual coupon codes or `$cart->add_fee()`.
- `Engine\PricingEngine` and `Engine\OrderManager` wire this into the actual WooCommerce hooks (price display, cart totals, order line items) and campaign usage counting on order status changes.
- `Helper\Filter::match()` is the targeting engine: sale-item exclusion, then `Helper\Condition::check_product_level_conditions()` (free version supports `user_role`, `user_roles`, `users` condition types), then `entire_store`/`product`/`category` target-type matching with `is_exclude` inversion — filterable via `campaignbay_filter_match` / `campaignbay_pass_product_level_condition`.

**Free/Pro coupling**: the discount pipeline is deliberately riddled with `do_action`/`apply_filters` calls documented with `@hook` tags specifically so Pro can extend without forking — e.g. `campaignbay_product_campaign_discount_calculation`, `campaignbay_product_discount_before_appling`/`_after_appling`, `campaignbay_before_cart_discount_calculation(_loop)`, `campaignbay_before/after_cart_single_discount_calculation`, `campaignbay_get_cart_discount`, `campaignbay_discount_breakdown`, `campaignbay_filter_match`, `campaignbay_pass_product_level_condition`, and `campaignbay_options_properties` (the settings schema itself is filterable so Pro can add its own settings fields into the same options object). `Core\Common` is the shared settings accessor (`get_settings()`/`update_settings()`, single option `campaignbay` merged over `default_settings`) that both the free plugin and Pro read from.

**REST API** (`app/Api/`, namespace `campaignbay/v1`): each controller extends `Api\ApiController` (itself a `WP_REST_Controller`), which centralizes the `manage_campaignbay` capability check + `X-WP-Nonce` verification used by every route's `permission_callback`. Controllers: `CampaignsController` (CRUD + `/duplicate`, `/bulk`, `/export`, `/import`), `SettingsController`, `DashboardController`, `LogsController`, `ActivityLogController`, `CalenderController`, `ResourceController`. Settings are also registered as a single WP option (`register_setting('campaignbay_settings_group', 'campaignbay', ...)`) with a `show_in_rest` schema built by `Admin::get_settings_schema()` — that schema is the filterable `campaignbay_options_properties` mentioned above, and `Admin::sanitize_settings_object()` is the corresponding sanitizer.

**Admin React app** (`src/`): a React + TypeScript SPA (HashRouter, routes `/`, `/campaigns`, `/campaigns/add`, `/campaigns/:id`, `/Calendar`, `/settings`) mounted by `src/index.tsx` into `#campaignbay` (a div rendered by `Admin::add_setting_root_div()`), bootstrapped from `window.campaignbay_Localize` (built server-side by `Admin::enqueue_resources()`, itself filterable via `campaignbay_admin_localize` — carries the REST nonce, `rest_url`, white-label info, WP date/time formats, editable user roles, and the full settings object). State is provided via `CbStoreProvider` (`src/store/cbStore.tsx`); `src/components/` is organized by feature (`campaign/`, `calender/`, `conditions/`, `dashboard/`, `importExport/`, `Onboarding/`, `settings/`, plus `common/`/`ui/` primitives); talks to the backend exclusively through `app/Api/` via `src/utils/apiFetch.ts`.

**Dual JS build targets**: `webpack.config.js` builds two bundles from the same `src/index.tsx` entry — a modern build (`admin.js`, React/ReactDOM externalized to WP-enqueued globals, used when `get_bloginfo('version') >= 6.8.0`) and a self-contained legacy Babel build (`admin-legacy.js`, used on older WordPress) selected via `BUILD_TARGET=legacy`; `Admin::enqueue_resources()` picks the right one at runtime and both are filterable (`campaignbay_admin_script`/`campaignbay_admin_legacy_script`/`campaignbay_admin_css`).

**Capability**: a custom `manage_campaignbay` capability (added to Administrator and Shop Manager roles on activation, in `Core\Activator`) gates both the admin menu and every REST endpoint — there is no reliance on `manage_options`/`manage_woocommerce`.

## What Pro adds (per readme.txt)

BOGO Advanced (cross-category buy/get), Product-in-Cart discounts, and advanced targeting conditions (cart total, order history, specific products in cart) beyond the free version's user-role/user conditions — sold as `campaignbaypro`, gated by a license key checked against `global_enableAddon` in settings.

The release checklist (version bump, changelog, build & zip) lives in the `release` skill — invoke it for "make a zip", "update the version", "is it ready to release", etc.

## Known bugs

### Dashboard "1 Year" range returns 400 and hangs the screen

The dashboard's date-range dropdown sends a `period` value the REST endpoint rejects, so
selecting **1 Year** leaves the whole dashboard stuck on its loading skeleton — not an
error state, just a permanent spinner.

- `src/pages/Dashboard.tsx:95` declares `type ChartPeriod = "7days" | "30days" | "1year"`.
- `src/pages/Dashboard.tsx:614` renders the option as `{ value: "1year", label: "1 Year" }`.
- `app/Api/DashboardController.php:601` declares `'enum' => array( '7days', '30days', 'year', 'custom' )`.
- `app/Api/DashboardController.php:499` handles `case 'year':` in the range switch. There is
  no `1year` case anywhere in PHP.

So `GET /wp-json/campaignbay/v1/dashboard?period=1year` returns:

```
400 {"code":"rest_invalid_param","message":"Invalid parameter(s): period",
     "data":{"params":{"period":"period is not one of 7days, 30days, year, and custom."}}}
```

Reproduced in the browser on WP 7.0 / WooCommerce 11.0. The 7-day and 30-day presets both
work; only the third option is broken.

**Recommended fix:** change the two `"1year"` occurrences in `Dashboard.tsx` to `"year"`.
`year` is already the canonical server-side value — it is what the switch handles and what
the enum advertises — so correcting the client keeps one spelling instead of teaching the
API a second one. Rebuild both JS bundles afterwards (`admin.js` and `admin-legacy.js`) or
the legacy path keeps sending the old value.

Related, not a bug: there is no 90-day preset at all — the choices are 7 days, 30 days and
1 Year. Anything in between requires the custom range picker.

## Behaviour worth knowing before you touch it

### `usage_count` is spent, not decorative

`usage_count` is not a display counter — two separate mechanisms consume it, and writing a
plausible-looking number into it can silently disable a campaign while the admin still shows
it as active:

- `Helper\Helper::earlybird_current_tier()` (`app/Helper/Helper.php:744`) walks the tiers
  subtracting each tier's `quantity` from `usage_count`, and **returns `null` once they are
  exhausted** — a null tier means no discount is applied. Note `quantity` here is *how many
  uses the tier covers*, not a cart quantity, so a tier written as `'quantity' => 1` gives its
  discount to exactly one order and then the campaign is dead for good.
- `Core\Campaign::increment_usage_count()` (`app/Core/Campaign.php:1188`) flips `status` to
  expired the moment `usage_count + 1 >= usage_limit`.

Anything that seeds, imports or migrates campaigns must keep `usage_count` below both the
summed tier `quantity` and `usage_limit`. `CampaignsController.php:712` deliberately allows
importing a previous usage count, so this is reachable through the public import path too.

### Dashboard activity timestamps render "now" and creep forward on every reload

Any Recent Activity entry older than 10 days shows the **current** time instead of when the
event happened, and the displayed time advances every time the page is reloaded.

`src/utils/Dates.ts`:

- `timeDiff()` (line 70) is careful about its input — line 80 reads
  `getDate(typeof dateTimeString === "number" ? dateTimeString * 1000 : dateTimeString)`.
- But when the event is more than 10 days old, line 88 hands the **raw** value to
  `formatDateTime(dateTimeString)`.
- `formatDateTime()` (line 17) is not careful: line 32 is
  `const dateTime: Date = getDate(dateTimeString * 1000);` — unconditional, no type check.

The activity feed supplies `l.timestamp` from `{prefix}campaignbay_logs`, which is a MySQL
DATETIME **string**. `"2026-08-08 06:00:23" * 1000` is `NaN`, `getDate(NaN)` falls back to
the current time, so the row renders as right now — and re-renders as a new "right now" on
every load.

Entries newer than 10 days are unaffected because they take the `humanTimeDiff()` branch,
which uses the correctly-parsed `dateTime` from line 80. That is why a feed can show
`3 days ago` / `7 days ago` correctly next to three rows all claiming the current minute.

`Campaigns.tsx:992` also calls `timeDiff()`, but passes `campaign.date_modified_unix` — a
number — so it never hits this path. The bug is specific to string inputs, i.e. the log
table.

**Recommended fix:** give `formatDateTime()` (and `formatDate()`, which has the identical
line 56) the same type check `timeDiff()` already uses, rather than fixing it at the call
site — both are exported and either can be called with a string by future code. Simplest
correct form:

```ts
const dateTime: Date = getDate(
  typeof dateTimeString === "number" ? dateTimeString * 1000 : dateTimeString
);
```

Alternatively have `timeDiff()` pass its already-parsed `dateTime` object down, but that
leaves the underlying trap in place for other callers.

Reproduced on WP 7.0 / WooCommerce 11.0 by seeding activity rows with backdated
`timestamp` values and reloading the dashboard three times ~30s apart: the three
older-than-10-days rows read 7:40 pm, then 7:41 pm, then 7:43 pm, while the DB values
never changed.
