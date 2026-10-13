import { CampaignSettingsType, CampaignType } from "./types";

export const getSettings = (type: CampaignType, settings: CampaignSettingsType) => {
    const result: CampaignSettingsType = getTypeSettings(type, settings);
    // Optional quantity limits are shared by every campaign type; keep them when present.
    if (settings?.quantity_limits) {
        result.quantity_limits = settings.quantity_limits;
    }
    return result;
}

const getTypeSettings = (type: CampaignType, settings: CampaignSettingsType) => {
    let defaultSettings: CampaignSettingsType = {}
    switch (type) {
        case "earlybird":
            defaultSettings = {
                display_as_regular_price: false,
                message_format: "",
                show_product_message: true,
            };
            if (settings?.display_as_regular_price !== undefined)
                defaultSettings["display_as_regular_price"] = settings.display_as_regular_price;
            defaultSettings["message_format"] = settings?.message_format || "";
            if (settings?.show_product_message !== undefined)
                defaultSettings["show_product_message"] = settings.show_product_message;
            return defaultSettings;

        case "scheduled":
            defaultSettings = {
                display_as_regular_price: false,
                message_format: "",
                show_product_message: true,
            };
            if (settings?.display_as_regular_price !== undefined)
                defaultSettings["display_as_regular_price"] = settings.display_as_regular_price;
            defaultSettings["message_format"] = settings?.message_format || "";
            if (settings?.show_product_message !== undefined)
                defaultSettings["show_product_message"] = settings.show_product_message;
            return defaultSettings;

        case "quantity":
            defaultSettings = {
                enable_quantity_table: false,
                apply_as: "line_total",
                cart_quantity_message_location: "line_item_name",
                cart_quantity_message_format: "",
            };
            if (settings?.enable_quantity_table !== undefined)
                defaultSettings["enable_quantity_table"] = settings.enable_quantity_table;
            defaultSettings["apply_as"] = settings?.apply_as || "line_total";
            defaultSettings["cart_quantity_message_location"] = settings?.cart_quantity_message_location || "line_item_name";
            defaultSettings["cart_quantity_message_format"] = settings?.cart_quantity_message_format || "";
            return defaultSettings;


        case "bogo":
            defaultSettings = {
                auto_add_free_product: true,// disabled
                show_bogo_message: true,
                bogo_banner_message_format: "",
                cart_bogo_message_format: "",
                bogo_cart_message_location: "line_item_name",// dont show added here no need seperate field
            };
            if (settings?.auto_add_free_product !== undefined)
                defaultSettings["auto_add_free_product"] = settings.auto_add_free_product;
            if (settings?.show_bogo_message !== undefined)
                defaultSettings["show_bogo_message"] = settings.show_bogo_message;
            defaultSettings["bogo_banner_message_format"] = settings?.bogo_banner_message_format || "";
            defaultSettings["cart_bogo_message_format"] = settings?.cart_bogo_message_format || "";
            defaultSettings["bogo_cart_message_location"] = settings?.bogo_cart_message_location || "line_item_name";
            return defaultSettings;
    }
    return defaultSettings;
}



