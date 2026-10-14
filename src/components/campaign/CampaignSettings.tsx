import { __ } from "@wordpress/i18n";
import Required from "./Required";
import { FC, Dispatch, SetStateAction } from "react";
import {
  CampaignSettingsErrorsType,
  CampaignSettingsType,
  CampaignType,
} from "../../utils/types";
import { Checkbox } from "../common/Checkbox";
import { Helper, renderError } from "./Campaign";
import Select from "../common/Select";
import CopyToClipboard from "../common/CopyToClipboard";
import { Input } from "../common/Input";
import { NumberInput } from "../common/NumberInput";

interface CampaignSettingsProps {
  settings: CampaignSettingsType;
  setSettings: Dispatch<SetStateAction<CampaignSettingsType>>;
  errors?: CampaignSettingsErrorsType;
  type: CampaignType;
}

const CampaignSettings: FC<CampaignSettingsProps> = ({
  settings,
  setSettings,
  errors,
  type,
}) => {
  const handleApplyAsChange = (value: string | number) => {
    if (value !== "coupon" && value !== "line_total" && value !== "fee") return;
    setSettings((prev: CampaignSettingsType) => ({
      ...settings,
      apply_as: value,
    }));
  };

  const handleCartMessageLocationChange = (value: string | number) => {
    if (
      value !== "line_item_name" &&
      value !== "notice" &&
      value !== "dont_show"
    )
      return;
    setSettings((prev: CampaignSettingsType) => ({
      ...prev,
      bogo_cart_message_location: value,
      cart_quantity_message_location: value,
    }));
  };

  return (
    <>
      {type === "earlybird" || type === "scheduled" ? (
        <>
          <div className="campaignbay-flex campaignbay-items-center campaignbay-gap-2">
            <Checkbox
              checked={
                settings?.display_as_regular_price === undefined
                  ? false
                  : settings?.display_as_regular_price
              }
              label={
                <>
                  {__("Display as Regular Price", "campaignbay")}{" "}
                  <Helper
                    content={__(
                      "Price will be shown as regular price, not as strike through price",
                      "campaignbay",
                    )}
                    docLink="https://docs.wpanchorbay.com/campaigns/scheduled-discounts.html#step-7-display-configurations"
                  />
                </>
              }
              onChange={(checked) =>
                setSettings((prev: CampaignSettingsType) => ({
                  ...prev,
                  display_as_regular_price: checked,
                }))
              }
            />
            {renderError(errors?.display_as_regular_price)}
          </div>
          {settings?.display_as_regular_price !== true ? (
            <>
              <div className="campaignbay-flex campaignbay-items-center campaignbay-gap-2">
                <Checkbox
                  checked={
                    settings?.show_product_message === undefined
                      ? true
                      : settings?.show_product_message
                  }
                  label={__(
                    "Show Product Page Promotional Message",
                    "campaignbay",
                  )}
                  onChange={(checked) =>
                    setSettings((prev: CampaignSettingsType) => ({
                      ...prev,
                      show_product_message: checked,
                    }))
                  }
                />
                {renderError(errors?.show_product_message)}
              </div>
              {settings?.show_product_message === true ||
              settings?.show_product_message === undefined ? (
                <div className="campaignbay-flex campaignbay-flex-col campaignbay-items-start campaignbay-gap-[4px]">
                  <Label>{__("Discount Message Format", "campaignbay")}</Label>
                  <Input
                    type="text"
                    id="message-format"
                    aria-label={__("Discount Message Format", "campaignbay")}
                    placeholder={__(
                      "Leave it blank for default message.",
                      "campaignbay",
                    )}
                    error={errors?.message_format?.message || ""}
                    value={settings?.message_format}
                    onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
                      setSettings((prev: CampaignSettingsType) => ({
                        ...prev,
                        message_format: e.target.value,
                      }))
                    }
                    disabled={settings?.display_as_regular_price}
                  />
                  <HelpText>
                    {__(
                      "Product Page Discount Message Format. Leave Blank for default message.",
                      "campaignbay",
                    )}
                    <Placeholders options={["percentage_off", "amount_off"]} />
                  </HelpText>
                </div>
              ) : null}
            </>
          ) : null}
        </>
      ) : null}

      {type === "quantity" ? (
        <>
          <div className="campaignbay-flex campaignbay-flex-col campaignbay-items-start campaignbay-gap-[4px]">
            <Label>
              {__("Apply Discount As", "campaignbay")} <Required />
            </Label>
            <Select
              id="apply_as"
              isError={!!errors?.apply_as}
              value={settings?.apply_as || "line_total"}
              onChange={handleApplyAsChange}
              options={[
                {
                  value: "line_total",
                  label: __("Strike through in line total", "campaignbay"),
                },
                { value: "coupon", label: __("Coupon", "campaignbay") },
                { value: "fee", label: __("Fee", "campaignbay") },
              ]}
            />
            {renderError(errors?.apply_as)}
          </div>

          {/* fixed discount basis */}
          <div className="campaignbay-flex campaignbay-flex-col campaignbay-items-start campaignbay-gap-[4px]">
            <Label>{__("Fixed discount applies", "campaignbay")}</Label>
            <Select
              id="fixed_discount_basis"
              value={settings?.fixed_discount_basis || "per_unit"}
              onChange={(value) => {
                if (value !== "per_unit" && value !== "per_line") return;
                setSettings((prev: CampaignSettingsType) => ({
                  ...prev,
                  fixed_discount_basis: value,
                }));
              }}
              options={[
                { value: "per_unit", label: __("Per item (default)", "campaignbay") },
                { value: "per_line", label: __("Once to the whole line", "campaignbay") },
              ]}
            />
            <HelpText>
              {__(
                "Only affects tiers with a fixed amount. Per item takes the amount off every unit; once to the whole line takes it off the line total a single time.",
                "campaignbay",
              )}
            </HelpText>
            {renderError(errors?.fixed_discount_basis)}
          </div>

          {/* cart page message location */}
          <div className="campaignbay-flex campaignbay-flex-col campaignbay-items-start campaignbay-gap-[4px]">
            <Label>
              {__("Cart Page Next Discount Message Location", "campaignbay")}{" "}
              <Required />
            </Label>
            <Select
              id="cart-message-location"
              isError={!!errors?.cart_quantity_message_location}
              value={
                settings?.cart_quantity_message_location || "line_item_name"
              }
              aria-label={__(
                "Cart Page Next Discount Message Location",
                "campaignbay",
              )}
              onChange={handleCartMessageLocationChange}
              options={[
                {
                  value: "line_item_name",
                  labelNode: (
                    <span className="campaignbay-flex campaignbay-items-center campaignbay-gap-2">
                      {__("Line Item Name", "campaignbay")}
                      <span className="campaignbay-text-xs campaignbay-opacity-50 !campaignbay-font-normal">
                        May not work in block theme.
                      </span>
                    </span>
                  ),
                  label: __("Line Item Name", "campaignbay"),
                },
                {
                  value: "notice",
                  label: __("Notice", "campaignbay"),
                  variant: "coming_soon",
                },
                {
                  value: "dont_show",
                  label: __("Don't Show", "campaignbay"),
                },
              ]}
            />
            <HelpText>
              {__(
                "Choose where the next quantity discount message will be displayed on the cart page.",
                "campaignbay",
              )}
            </HelpText>
            {renderError(errors?.cart_quantity_message_location)}
          </div>

          {/* quantity message format */}
          {settings?.cart_quantity_message_location !== "dont_show" ? (
            <div className="campaignbay-flex campaignbay-flex-col campaignbay-items-start campaignbay-gap-[4px]">
              <Label>{__("Next Discount Message Format", "campaignbay")}</Label>
              <Input
                type="text"
                id="message-format"
                placeholder={__(
                  "Leave it blank for default message.",
                  "campaignbay",
                )}
                aria-label={__(
                  "Cart Page Next Discount Message Format",
                  "campaignbay",
                )}
                error={errors?.cart_quantity_message_format?.message || ""}
                value={settings?.cart_quantity_message_format}
                onChange={(e) =>
                  setSettings((prev) => ({
                    ...prev,
                    cart_quantity_message_format: e.target.value,
                  }))
                }
              />
              <HelpText>
                {__(
                  "This message will be displayed on the cart item name. Leave blank for the default message.",
                  "campaignbay",
                )}
                <Placeholders
                  options={[
                    "remaining_quantity_for_next_offer",
                    "percentage_off",
                    "amount_off",
                  ]}
                />
              </HelpText>
            </div>
          ) : null}
        </>
      ) : null}

      {type === "bogo" ? (
        <>
          {/* cart page message format when a bogo applied */}
          <div className="campaignbay-flex campaignbay-flex-col campaignbay-items-start campaignbay-gap-[4px]">
            <Label>
              {__("Cart Page Discount Message Format", "campaignbay")}
            </Label>
            <Input
              type="text"
              id="cart-bogo-message-format"
              placeholder={__(
                "Leave it blank for default message.",
                "campaignbay",
              )}
              aria-label={__(
                "Cart Page Discount Message Format",
                "campaignbay",
              )}
              error={errors?.cart_bogo_message_format?.message || ""}
              value={settings?.cart_bogo_message_format}
              onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
                setSettings((prev: CampaignSettingsType) => ({
                  ...prev,
                  cart_bogo_message_format: e.target.value,
                }))
              }
            />
            <HelpText>
              {__(
                "This message will be displayed on the cart page. Leave blank for the default message.",
                "campaignbay",
              )}
              <Placeholders options={["title", "buy_product_name"]} />
            </HelpText>
          </div>

          {/* cart page message location */}
          <div className="campaignbay-flex campaignbay-flex-col campaignbay-items-start campaignbay-gap-[4px]">
            <Label>
              {__("Cart Page Message Location", "campaignbay")} <Required />
            </Label>
            <Select
              id="bogo-cart-message-location"
              isError={!!errors?.bogo_cart_message_location}
              value={settings?.bogo_cart_message_location || "line_item_name"}
              aria-label={__("Cart Page Message Location", "campaignbay")}
              onChange={handleCartMessageLocationChange}
              options={[
                {
                  value: "line_item_name",
                  labelNode: (
                    <span className="campaignbay-flex campaignbay-items-center campaignbay-gap-2">
                      {__("Line Item Name", "campaignbay")}
                      <span className="campaignbay-text-xs campaignbay-opacity-50 !campaignbay-font-normal">
                        May not work in block theme.
                      </span>
                    </span>
                  ),
                  label: __("Line Item Name", "campaignbay"),
                },
                {
                  value: "notice",
                  label: __("Notice", "campaignbay"),
                  variant: "coming_soon",
                },
                {
                  value: "dont_show",
                  label: __("Don't Show", "campaignbay"),
                },
              ]}
            />
            <HelpText>
              {__(
                "Choose where the BOGO discount message will be displayed on the cart page.",
                "campaignbay",
              )}
            </HelpText>
            {renderError(errors?.bogo_cart_message_location)}
          </div>

          <div className="campaignbay-flex campaignbay-items-center campaignbay-gap-2">
            <Checkbox
              checked={
                settings?.show_bogo_message === undefined
                  ? true
                  : settings?.show_bogo_message
              }
              label={__(
                "Show BOGO Promotional Message at product page",
                "campaignbay",
              )}
              onChange={(checked) =>
                setSettings((prev: CampaignSettingsType) => ({
                  ...settings,
                  show_bogo_message: checked,
                }))
              }
            />
            {renderError(errors?.show_bogo_message)}
          </div>

          {/* product page message format */}
          {settings?.show_bogo_message === true ||
          settings?.show_bogo_message === undefined ? (
            <div className="campaignbay-flex campaignbay-flex-col campaignbay-items-start campaignbay-gap-[4px]">
              <Label>
                {__("Product Page Discount Message Format", "campaignbay")}
              </Label>
              <Input
                type="text"
                placeholder={__(
                  "Leave it blank for default message.",
                  "campaignbay",
                )}
                id="bogo-banner-message-format"
                aria-label={__(
                  "Product Page Discount Message Format",
                  "campaignbay",
                )}
                error={errors?.bogo_banner_message_format?.message || ""}
                value={settings?.bogo_banner_message_format}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
                  setSettings((prev: CampaignSettingsType) => ({
                    ...prev,
                    bogo_banner_message_format: e.target.value,
                  }))
                }
              />
              <HelpText>
                {__(
                  "This message will be displayed on the product page. Leave blank for the default message.",
                  "campaignbay",
                )}
                <Placeholders options={["buy_quantity", "get_quantity"]} />
              </HelpText>
            </div>
          ) : null}
        </>
      ) : null}

      {/* storefront display (all campaign types) */}
      <div className="campaignbay-flex campaignbay-flex-col campaignbay-items-start campaignbay-gap-[8px]">
        <Label>{__("Storefront display", "campaignbay")}</Label>
        <Checkbox
          checked={settings?.show_countdown ?? true}
          label={__("Show countdown timer", "campaignbay")}
          onChange={(checked) =>
            setSettings((prev: CampaignSettingsType) => ({
              ...prev,
              show_countdown: checked,
            }))
          }
        />
        <HelpText>
          {__(
            "Shows a countdown to the campaign end date. Only appears when the campaign has an end date.",
            "campaignbay",
          )}
        </HelpText>
        <Checkbox
          checked={!!settings?.promote_enabled}
          label={__("Promote this campaign", "campaignbay")}
          onChange={(checked) =>
            setSettings((prev: CampaignSettingsType) => ({
              ...prev,
              promote_enabled: checked,
            }))
          }
        />
        {settings?.promote_enabled ? (
          <>
            <textarea
              id="promote_text"
              rows={3}
              className="campaignbay-w-full campaignbay-text-[13px]"
              placeholder={__("Leave blank for the default promotion text.", "campaignbay")}
              aria-label={__("Promotion text", "campaignbay")}
              value={settings?.promote_text ?? ""}
              onChange={(e) =>
                setSettings((prev: CampaignSettingsType) => ({
                  ...prev,
                  promote_text: e.target.value,
                }))
              }
            />
            {renderError(errors?.promote_text)}
            <HelpText>
              {__(
                "Shown on the storefront to advertise this campaign.",
                "campaignbay",
              )}
              <Placeholders
                options={["campaign_title", "end_date", "discount"]}
              />
            </HelpText>
          </>
        ) : null}
      </div>

      {/* optional quantity limits (all campaign types) */}
      <div className="campaignbay-flex campaignbay-flex-col campaignbay-items-start campaignbay-gap-[8px]">
        <Checkbox
          checked={!!settings?.quantity_limits?.enabled}
          label={
            <>
              {__("Limit purchase quantity", "campaignbay")}{" "}
              <Helper
                content={__(
                  "Set a minimum, maximum and step quantity for the products this campaign targets while it is active. Not enforced for campaigns that use cart total or cart item quantity conditions.",
                  "campaignbay",
                )}
              />
            </>
          }
          onChange={(checked) =>
            setSettings((prev: CampaignSettingsType) => ({
              ...prev,
              quantity_limits: checked
                ? {
                    min: null,
                    max: null,
                    step: null,
                    ...prev?.quantity_limits,
                    enabled: true,
                  }
                : { enabled: false, min: null, max: null, step: null },
            }))
          }
        />
        {settings?.quantity_limits?.enabled ? (
          <div className="campaignbay-flex campaignbay-flex-wrap campaignbay-gap-[12px]">
            {(["min", "max", "step"] as const).map((field) => (
              <div
                key={field}
                className="campaignbay-flex campaignbay-flex-col campaignbay-items-start campaignbay-gap-[4px]"
              >
                <Label>
                  {field === "min"
                    ? __("Minimum quantity", "campaignbay")
                    : field === "max"
                      ? __("Maximum quantity", "campaignbay")
                      : __("Quantity step", "campaignbay")}
                </Label>
                <NumberInput
                  min={1}
                  value={settings?.quantity_limits?.[field] ?? null}
                  placeholder={__("No limit", "campaignbay")}
                  onChange={(value) =>
                    setSettings((prev: CampaignSettingsType) => ({
                      ...prev,
                      quantity_limits: {
                        enabled: true,
                        min: null,
                        max: null,
                        step: null,
                        ...prev?.quantity_limits,
                        [field]: value === null ? null : Math.max(1, Math.floor(value)),
                      },
                    }))
                  }
                />
              </div>
            ))}
          </div>
        ) : null}
        {renderError(errors?.quantity_limits)}
        {settings?.quantity_limits?.enabled ? (
          <HelpText>
            {__(
              "Leave a field empty for no limit. Quantities must be a multiple of the step.",
              "campaignbay",
            )}
          </HelpText>
        ) : null}
      </div>
    </>
  );
};

export default CampaignSettings;

interface PlaceholderProps {
  text: string;
  classNames?: {
    root?: string;
    copy?: string;
  };
}

export const Placeholder: FC<PlaceholderProps> = ({ text, classNames }) => {
  return (
    <span
      className={
        classNames?.root ||
        "campaignbay-bg-gray-200 campaignbay-my-[2px] campaignbay-mx-[4px] campaignbay-p-[2px] campaignbay-px-[4px] campaignbay-rounded-md campaignbay-inline-flex campaignbay-items-center campaignbay-justify-center campaignbay-gap-[4px]"
      }
    >
      <span
        className={
          classNames?.copy || "campaignbay-mr-[2px] campaignbay-text-gray-600"
        }
      >{`{${text}}`}</span>
      <CopyToClipboard text={`{${text}}`} />
    </span>
  );
};

interface PlaceholdersProps {
  options: string[];
  classNames?: {
    root?: string;
    placeholder?: string;
  };
}

export const Placeholders: FC<PlaceholdersProps> = ({
  options,
  classNames,
}) => {
  return (
    <span
      className={classNames?.root || "campaignbay-inline campaignbay-text-wrap"}
    >
      {" "}
      Use placeholder like
      {options.map((option, index) => {
        return (
          <Placeholder
            key={index}
            text={option}
            classNames={{ copy: classNames?.placeholder }}
          />
        );
      })}
      .
    </span>
  );
};

const Label = ({ children }: { children: React.ReactNode }) => {
  return (
    <label className="campaignbay-text-[11px] campaignbay-font-[400] campaignbay-text-[#1e1e1e] campaignbay-leading-[16px] campaignbay-uppercase">
      {children}
    </label>
  );
};

const HelpText = ({ children }: { children: React.ReactNode }) => {
  return (
    <span className="campaignbay-text-[13px] campaignbay-font-[400] campaignbay-text-[#1e1e1e] campaignbay-leading-[16px]">
      {children}
    </span>
  );
};
