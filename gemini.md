# CampaignBay Core Plugin

## Overview
CampaignBay is a WooCommerce plugin designed to provide automated discount campaigns and flash sales. It acts as the core foundational engine, supporting four main campaign types out-of-the-box:
1. **Scheduled Discounts:** Time-based flat percentage or fixed discounts.
2. **Quantity Based:** Tiered pricing rewarding bulk purchases.
3. **Early Bird:** Scarcity-based offers limited to the first N customers.
4. **BOGO Standard:** Classic "Buy X Get X" free or discounted.

## Architecture
The plugin follows a modular MVC-like approach:
- **`app/Api/`**: REST API endpoints for the React-based admin interface (e.g., Campaigns, Settings, Analytics).
- **`app/Core/`**: Core classes for plugin lifecycle, the Campaign ORM-like model, Filter for targeting products, and Logger.
- **`app/Data/`**: Database migrations handling two custom tables: `campaignbay_campaigns` and `campaignbay_logs`.
- **`app/Engine/`**: The heart of the plugin containing `ProductDiscount`, `CartDiscount`, `CampaignManager`, and `PricingEngine`. The `PricingEngine` integrates tightly with WooCommerce hooks like `woocommerce_get_price_html`, `woocommerce_before_calculate_totals`, and uses virtual coupons to apply discounts seamlessly without creating actual WC coupons.
- **`app/Helper/`**: Utilities for evaluating conditions and WooCommerce-specific logic.
- **`src/`**: A React + TypeScript SPA for the admin UI, communicating with the `app/Api/` endpoints.

## Targeting & Conditions
- **Targeting Types:** Entire Store, Specific Products, Specific Categories, Product Tags.
- **Exclusion Logic:** `is_exclude` flag inverts targeting; `exclude_sale_items` prevents double discounting.
- **Conditions:** Free version supports restricting by `user_role`, `user_roles`, and specific `users`.

## Key Hooks
- `woocommerce_before_calculate_totals`: Where cart level rules (like Quantity and BOGO) are evaluated.
- `woocommerce_get_price_html`: Where product-level discounts are visually represented.
- `woocommerce_before_add_to_cart_form`: Where promotional discount messages are displayed using placeholders (e.g. `{percentage_off}`).

## Extensibility
The core plugin is built to be extended, primarily serving as the base for `CampaignBayPro`. It exposes various filters like `campaignbay_filter_match` and `campaignbay_pass_product_level_condition` for addons to hook into the discounting flow.
