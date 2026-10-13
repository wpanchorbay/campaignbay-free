export interface DiscountTableOptionsType {
  show_header: boolean;
  title: {
    show: boolean;
    label: string;
  };
  range: {
    show: boolean;
    label: string;
  };
  discount: {
    show: boolean;
    label: string;
    content: "price" | "value";
  };
}

export interface CampaignBaySettingsType {
  global_enableAddon: boolean;
  global_calculate_discount_from: "regular_price" | "sale_price";
  position_to_show_bulk_table: string;
  position_to_show_discount_bar: string;
  perf_enableCaching: boolean;
  debug_enableMode: boolean;

  product_message_format_percentage: string;
  product_message_format_fixed: string;
  bogo_banner_message_format: string;
  show_discount_table: boolean;
  quantity_limit_show_product_message: boolean;
  quantity_limit_product_message_format: string;
  quantity_limit_message_min: string;
  quantity_limit_message_max: string;
  quantity_limit_message_step: string;
  product_priorityMethod: "apply_highest" | "apply_lowest" | "apply_first" | "apply_recent";
  product_enableQuantityTable: boolean;
  discount_table_options: DiscountTableOptionsType;
  discount_table_layout: "table" | "cards";
  discount_table_show_tier_label: boolean;
  discount_card_title_format: string;
  discount_card_price_format: string;
  discount_card_discount_format: string;

  countdown_show_product: boolean;
  countdown_show_loop: boolean;
  countdown_show_cart: boolean;
  countdown_format_product: string;
  countdown_format_loop: string;
  countdown_format_cart: string;
  countdown_format_shortcode: string;
  countdown_expired_message: string;

  badge_enabled: boolean;
  badge_text: string;
  badge_bg_color: string;
  badge_text_color: string;

  category_offer_show: boolean;
  category_offer_show_shop: boolean;
  category_offer_format: string;
  promote_show_product: boolean;
  promote_show_category: boolean;
  promote_format: string;

  cart_allowWcCouponStacking: boolean;
  cart_allowCampaignStacking: boolean;
  cart_quantity_message_format_percentage: string;
  cart_quantity_message_format_fixed: string;
  cart_bogo_message_format: string;
  cart_bogo_cart_message_format: string;
  advanced_deleteAllOnUninstall: boolean;
  advanced_customCss: string;
  advanced_customJs: string;
  _locale?: string;
}