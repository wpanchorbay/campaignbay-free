import { ConditionsInterface } from "../components/conditions/type";
import { CampaignBaySettingsType } from "../components/settings/types";

export interface WhiteLabel {
    plugin_name: string;
    short_name: string;
    menu_label: string;
    custom_icon: string; // URL
    menu_icon: string; // URL
    author_name: string;
    author_uri: string; // URL
    support_uri: string; // URL
    docs_uri: string; // URL
    position: number;
}

export interface WpSettings {
    dateFormat: string;
    timeFormat: string;
    user_roles: {
        name: string;
        value: string;
    }[];
}

export interface CbStore {
    version: string; 
    root_id: string;
    nonce: string;
    store: string;
    rest_url: string;
    white_label: WhiteLabel;
    woocommerce_currency_symbol: string;
    onboarding: {
        first_campaign: boolean;
    };
    wpSettings: WpSettings;
    campaignbay_settings: CampaignBaySettingsType;
}

export interface DiscountTableOptionsType {
    show_header: boolean;
    title:{
        show: boolean;
        label: string;
    };
    range:{
        show: boolean;
        label: string;
    };
    discount:{
        show: boolean;
        label: string;
        content: "price" | "value";
    };
}



// ====================================================================================
// ==================================== Campaign Settings =============================
// ====================================================================================

export type ApplyAsType = "line_total" | "coupon" | "fee";
export type BogoMessageLocationType = "line_item_name" | "notice" | "dont_show";

export type CampaignStatusType =
    | "active"
    | "inactive"
    | "scheduled"
    | "expired";
export type CampaignType = "bogo" | "scheduled" | "quantity" | "earlybird" | "bogo_pro" | "product_in_cart";
export type DiscountType = "percentage" | "fixed" | "currency";
export type TargetType = "entire_store" | "category" | "product" | "tag";

// ==================================== Tier start ========================================
export interface QuantityTier {
    id: number;
    min: number;
    max?: number | "" | null;
    value?: number | "";
    type?: "percentage" | "currency";
}
export type ValidationError = Partial<
    Record<string | number, { message: string }>
>;
export type QuantityTierError = Partial<
    Record<"min" | "max" | "value" | "type", { message: string }>
>;

export interface EBTier {
    id: number | string;
    quantity: number | "";
    value: number | "";
    type: "percentage" | "currency";
    total: number;
}
export type EBTierError = Partial<
    Record<"quantity" | "value", { message: string }>
>;

export interface BogoTier {
    id: number;
    buy_quantity: number | "";
    get_quantity: number | "";
}
export type BogoTierError = Partial<
    Record<"buy_quantity" | "get_quantity", { message: string }>
>;

export type Tier = QuantityTier | EBTier | BogoTier;

// ==================== tier end ====================================

// ==================== campaign start ====================================
export interface Campaign {
    id: number;
    title: string;
    status: CampaignStatusType;
    type: CampaignType;

    discount_type: DiscountType;
    discount_value: number | null | "";

    tiers: Tier[];

    target_type: TargetType;
    target_ids: number[];
    is_exclude: boolean;
    exclude_sale_items: boolean;

    schedule_enabled: boolean;
    start_datetime: string | Date | null;
    end_datetime: string | Date | null;

    usage_count?: number;
    usage_limit: number | null;

    date_created?: string | Date | Number;
    date_modified?: string | Date | Number;
    created_by?: number;
    updated_by?: number;

    start_datetime_unix?: number | null;
    end_datetime_unix?: number | null;
    date_modified_unix?: number | null;
    date_created_unix?: number | null;

    conditions: ConditionsInterface;

    settings: CampaignSettingsType;
}

export interface CampaignSettingsType {
  // scheduled & earlybird
  display_as_regular_price?: boolean;
  message_format?: string;
  show_product_message?: boolean;

  // quantity
  enable_quantity_table?: boolean;
  apply_as?: ApplyAsType;
  cart_quantity_message_location?: BogoMessageLocationType;
  cart_quantity_message_format?: string;

  // bogo
  auto_add_free_product?: boolean;
  show_bogo_message?: boolean;
  bogo_banner_message_format?: string;
  cart_bogo_message_format?: string;
  bogo_cart_message_location?: BogoMessageLocationType;// dont show added here , no need boolean
}
export type CampaignSettingsErrorsType = Partial<
  Record<keyof CampaignSettingsType, { message: string }>
>;
export type CampaignErrorsType = {
  title?: { message: string };
  status?: { message: string };
  type?: { message: string };
  discount_type?: { message: string };
  discount_value?: { message: string };
  target_type?: { message: string };
  target_ids?: { message: string };
  isExclude?: { message: string };
  exclude_sale_items?: { message: string };
  usage_limit?: { message: string };
  schedule_enabled?: { message: string };
  start_datetime?: { message: string };
  end_datetime?: { message: string };
  tiers?: QuantityTierError[] | EBTierError[] | BogoTierError[];
  settings?: CampaignSettingsErrorsType;
};
