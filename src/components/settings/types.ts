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
  product_priorityMethod: "apply_highest" | "apply_lowest" | "apply_first" | "apply_recent";
  product_enableQuantityTable: boolean;
  discount_table_options: DiscountTableOptionsType;

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