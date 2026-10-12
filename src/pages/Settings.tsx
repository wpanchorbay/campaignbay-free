import React, { useState, useRef, useEffect } from "react";
import { Switch } from "../components/common/Switch";
import { Input } from "../components/common/Input";
import CustomSelect from "../components/common/Select";
import { Checkbox } from "../components/common/Checkbox";
import Button from "../components/common/Button";
import { ConfirmationModal } from "../components/common/ConfirmationModal";
import {
  CampaignBaySettingsType,
  DiscountTableOptionsType,
} from "../components/settings/types";
import Select from "../components/common/Select";
import { Placeholders } from "../components/campaign/CampaignSettings";
import { useToast } from "../store/toast/use-toast";
import apiFetch from "@wordpress/api-fetch";
import { __ } from "@wordpress/i18n";
import LogViewerModal from "../components/settings/LogViewerModal";
import Skeleton from "../components/common/Skeleton";
import Page from "../components/common/Page";
import Header from "../components/common/Header";
import HeaderContainer from "../components/common/HeaderContainer";
import QuantityTableEditModal from "../components/settings/QuantityTableEditModal";
import { useCbStoreActions } from "../store/cbStore";

type TabType = "global" | "product" | "cart" | "advanced";

// Helper Components moved outside to prevent re-renders losing focus
const SettingsCard = ({
  children,
  className = "",
}: {
  children: React.ReactNode;
  className?: string;
}) => (
  <div
    className={`campaignbay-bg-white campaignbay-rounded-lg campaignbay-p-6 ${className}`}
  >
    {children}
  </div>
);

const SectionHeader = ({ title }: { title: string }) => (
  <div className="campaignbay-pb-4 campaignbay-mb-6">
    <h3 className="campaignbay-text-[15px] campaignbay-font-[700] campaignbay-leading-[24px] campaignbay-text-[#1e1e1e]">
      {title}
    </h3>
  </div>
);

const TabHeader = React.forwardRef<
  HTMLButtonElement,
  {
    id: TabType;
    label: string;
    activeTab: TabType;
    onTabChange: (id: TabType) => void;
  }
>(({ id, label, activeTab, onTabChange }, ref) => (
  <button
    ref={ref}
    onClick={() => onTabChange(id)}
    className={`
      campaignbay-px-4 campaignbay-py-4 campaignbay-text-sm campaignbay-font-bold campaignbay-outline-none
      campaignbay-transition-colors campaignbay-duration-200
      ${
        activeTab === id
          ? "campaignbay-text-primary"
          : "campaignbay-text-[#1e1e1e] hover:campaignbay-text-primary"
      }
    `}
  >
    {label}
  </button>
));
TabHeader.displayName = "TabHeader";

interface ContentProps {
  settings: CampaignBaySettingsType;
  updateSetting: (key: keyof CampaignBaySettingsType, value: any) => void;
}

const GlobalContent: React.FC<ContentProps> = ({ settings, updateSetting }) => {
  const [isClearingLogs, setIsClearingLogs] = useState(false);
  const [isLogModalOpen, setIsLogModalOpen] = useState(false);
  const { addToast } = useToast();
  const handleClearLogs = async (): Promise<boolean> => {
    setIsClearingLogs(true);
    let wasSuccessful = false;
    try {
      await apiFetch({ path: "/campaignbay/v1/logs", method: "DELETE" });
      addToast(__("Log files cleared successfully.", "campaignbay"), "success");
      wasSuccessful = true;
    } catch (error: any) {
      const errorMessage =
        error.message || __("An unknown error occurred.", "campaignbay");
      addToast(
        `${__("Error clearing logs:", "campaignbay")} ${errorMessage}`,
        "error",
      );
      console.error("Clear logs error:", error);
      wasSuccessful = false;
    }
    setIsClearingLogs(false);
    return wasSuccessful;
  };

  return (
    <div className="campaignbay-space-y-[12px]">
      <SettingsCard>
        <SectionHeader title="Global Options" />

        <div className="campaignbay-space-y-6">
          <div>
            <div className="campaignbay-flex campaignbay-items-center campaignbay-justify-start campaignbay-gap-[8px]">
              <label className="!campaignbay-text-[13px] !campaignbay-font-[700] !campaignbay-leading-[20px] !campaignbay-text-[#1e1e1e] !campaignbay-uppercase">
                Enable Discount Add-on
              </label>
              <Switch
                size="small"
                checked={settings.global_enableAddon}
                onChange={(v) => updateSetting("global_enableAddon", v)}
              />
            </div>
            <p className="campaignbay-text-[12px] campaignbay-font-[400] campaignbay-leading-[20px] campaignbay-text-gray-600">
              Turn off to temporarily disable all discount campaigns.
            </p>
          </div>
          <div className="campaignbay-grid campaignbay-grid-cols-1 lg:campaignbay-grid-cols-2 campaignbay-gap-8">
            <Select
              label="Bulk Table Position"
              classNames={{
                label:
                  "!campaignbay-text-[13px] !campaignbay-font-[700] !campaignbay-leading-[20px] !campaignbay-text-[#1e1e1e] !campaignbay-uppercase",
              }}
              value={settings.position_to_show_bulk_table}
              onChange={(v) => updateSetting("position_to_show_bulk_table", v)}
              options={[
                {
                  label: __("Below Cart", "campaignbay"),
                  value: "woocommerce_after_add_to_cart_form",
                },
                {
                  label: __("Above Cart", "campaignbay"),
                  value: "woocommerce_before_add_to_cart_form",
                },
                {
                  label: __("Below Meta", "campaignbay"),
                  value: "woocommerce_product_meta_end",
                },
                {
                  label: __("Above Meta", "campaignbay"),
                  value: "woocommerce_product_meta_start",
                },
              ]}
            />
            <Select
              label="Discount Bar Position"
              classNames={{
                label:
                  "!campaignbay-text-[13px] !campaignbay-font-[700] !campaignbay-leading-[20px] !campaignbay-text-[#1e1e1e] !campaignbay-uppercase",
              }}
              value={settings.position_to_show_discount_bar}
              onChange={(v) =>
                updateSetting("position_to_show_discount_bar", v)
              }
              options={[
                {
                  label: __("Below Cart", "campaignbay"),
                  value: "woocommerce_after_add_to_cart_form",
                },
                {
                  label: __("Above Cart", "campaignbay"),
                  value: "woocommerce_before_add_to_cart_form",
                },
                {
                  label: __("Below Meta", "campaignbay"),
                  value: "woocommerce_product_meta_end",
                },
                {
                  label: __("Above Meta", "campaignbay"),
                  value: "woocommerce_product_meta_start",
                },
              ]}
            />
          </div>
          <div className="campaignbay-max-w-md">
            <Select
              label="Calculate Discount From"
              classNames={{
                label:
                  "!campaignbay-text-[13px] !campaignbay-font-[700] !campaignbay-leading-[20px] !campaignbay-text-[#1e1e1e] !campaignbay-uppercase",
              }}
              value={settings.global_calculate_discount_from}
              onChange={(v) =>
                updateSetting("global_calculate_discount_from", v)
              }
              options={[
                { label: "Sale Price", value: "sale_price" },
                { label: "Regular Price", value: "regular_price" },
              ]}
            />
            <p className="campaignbay-text-xs campaignbay-text-gray-500 campaignbay-mt-2">
              Select the price to calculate discounts from
            </p>
          </div>
        </div>
      </SettingsCard>

      <SettingsCard>
        <SectionHeader title="Debugging & Logging" />

        <div className="campaignbay-space-y-4">
          <Checkbox
            label="Enable Debug Mode"
            checked={settings.debug_enableMode}
            onChange={(v) => updateSetting("debug_enableMode", v)}
            classNames={{
              label:
                "!campaignbay-text-[13px] !campaignbay-font-[700] !campaignbay-leading-[20px] !campaignbay-text-[#1e1e1e] !campaignbay-uppercase",
            }}
          />
          <p className="campaignbay-text-xs campaignbay-text-gray-500 campaignbay-pl-8">
            Show detailed error messages and logging for troubleshooting.
          </p>

          <div className="campaignbay-flex campaignbay-gap-4 campaignbay-pl-8">
            <Button
              variant="outline"
              size="small"
              className="campaignbay-flex campaignbay-gap-2"
              onClick={() => setIsLogModalOpen(true)}
            >
              <svg
                className="campaignbay-w-4 campaignbay-h-4"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                />
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                />
              </svg>
              View Logs
            </Button>
            <Button
              variant="outline"
              color="danger"
              size="small"
              disabled={isClearingLogs}
              className="campaignbay-flex campaignbay-gap-2"
              onClick={handleClearLogs}
            >
              <svg
                className="campaignbay-w-4 campaignbay-h-4"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                />
              </svg>
              Clear Log Files
            </Button>
          </div>
        </div>
      </SettingsCard>

      <LogViewerModal
        isLogModalOpen={isLogModalOpen}
        setIsLogModalOpen={setIsLogModalOpen}
        handleClearLogs={handleClearLogs}
        isClearingLogs={isClearingLogs}
      />
    </div>
  );
};

interface ProductContentProps extends ContentProps {
  isSaving: boolean;
  handleSave: () => void;
}

const ProductContent: React.FC<ProductContentProps> = ({
  settings,
  updateSetting,
  isSaving,
  handleSave,
}) => {
  const [isModalOpen, setIsModalOpen] = useState<boolean>(false);
  return (
    <div className="campaignbay-space-y-6">
      <SettingsCard>
        <SectionHeader title="Product Page Display" />

        <div className="campaignbay-space-y-6">
          <div className="campaignbay-grid campaignbay-grid-cols-1 lg:campaignbay-grid-cols-2 campaignbay-gap-8">
            <div>
              <label className="campaignbay-block campaignbay-mb-2 !campaignbay-text-[13px] !campaignbay-font-[700] !campaignbay-leading-[20px] !campaignbay-text-[#1e1e1e] !campaignbay-uppercase">
                Product Page Percentage Schedule or Early Bird Discount Message
                Format
              </label>
              <Input
                value={settings.product_message_format_percentage}
                onChange={(e) =>
                  updateSetting(
                    "product_message_format_percentage",
                    e.target.value,
                  )
                }
              />
              <Placeholders
                options={["percentage_off"]}
                classNames={{ root: "campaignbay-mt-2" }}
              />
            </div>
            <div>
              <label className="campaignbay-block campaignbay-mb-2 !campaignbay-text-[13px] !campaignbay-font-[700] !campaignbay-leading-[20px] !campaignbay-text-[#1e1e1e] !campaignbay-uppercase">
                Product Page Fixed Schedule or Early Bird Discount Message
                Format
              </label>
              <Input
                value={settings.product_message_format_fixed}
                onChange={(e) =>
                  updateSetting("product_message_format_fixed", e.target.value)
                }
              />
              <Placeholders
                options={["amount_off"]}
                classNames={{ root: "campaignbay-mt-2" }}
              />
            </div>
          </div>

          <div className="campaignbay-grid campaignbay-grid-cols-1 lg:campaignbay-grid-cols-2 campaignbay-gap-8">
            <div>
              <label className="campaignbay-block campaignbay-mb-2 !campaignbay-text-[13px] !campaignbay-font-[700] !campaignbay-leading-[20px] !campaignbay-text-[#1e1e1e] !campaignbay-uppercase">
                Product Page BOGO Discount Message Format
              </label>
              <Input
                value={settings.bogo_banner_message_format}
                onChange={(e) =>
                  updateSetting("bogo_banner_message_format", e.target.value)
                }
              />
              <Placeholders
                options={["buy_quantity", "get_quantity"]}
                classNames={{ root: "campaignbay-mt-2" }}
              />
            </div>

            <div className="campaignbay-space-y-2">
              <Checkbox
                label="Enable Quantity Discounts Table on Product Page"
                checked={settings.product_enableQuantityTable}
                onChange={(v) =>
                  updateSetting("product_enableQuantityTable", v)
                }
                classNames={{
                  label:
                    "!campaignbay-text-[13px] !campaignbay-font-[700] !campaignbay-leading-[20px] !campaignbay-text-[#1e1e1e] !campaignbay-uppercase",
                }}
              />
              <span className="campaignbay-text-xs campaignbay-text-gray-500 campaignbay-pl-8">
                Show a table outlining tiered quantity based discounts
              </span>
              <div className="campaignbay-pl-8">
                <Button
                  variant="outline"
                  size="medium"
                  className="campaignbay-flex campaignbay-gap-2"
                  onClick={() => setIsModalOpen(true)}
                >
                  <svg
                    className="campaignbay-w-4 campaignbay-h-4"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                    />
                  </svg>
                  Customize Table
                </Button>
              </div>
            </div>
          </div>
        </div>
      </SettingsCard>

      <SettingsCard>
        <SectionHeader title="Quantity Limit Messages" />
        <div className="campaignbay-space-y-6">
          <div className="campaignbay-flex campaignbay-items-center campaignbay-justify-start campaignbay-gap-[8px]">
            <label className="!campaignbay-text-[13px] !campaignbay-font-[700] !campaignbay-leading-[20px] !campaignbay-text-[#1e1e1e] !campaignbay-uppercase">
              Show Limits on Product Page
            </label>
            <Switch
              size="small"
              checked={settings.quantity_limit_show_product_message}
              onChange={(v) => updateSetting("quantity_limit_show_product_message", v)}
            />
          </div>
          <div className="campaignbay-grid campaignbay-grid-cols-1 lg:campaignbay-grid-cols-2 campaignbay-gap-8">
            <div>
              <label className="campaignbay-block campaignbay-mb-2 !campaignbay-text-[13px] !campaignbay-font-[700] !campaignbay-leading-[20px] !campaignbay-text-[#1e1e1e] !campaignbay-uppercase">
                Product Page Quantity Limits Message Format
              </label>
              <Input
                value={settings.quantity_limit_product_message_format}
                onChange={(e) => updateSetting("quantity_limit_product_message_format", e.target.value)}
              />
              <Placeholders
                options={["limits", "min_quantity", "max_quantity", "step"]}
                classNames={{ root: "campaignbay-mt-2" }}
              />
            </div>
            <div>
              <label className="campaignbay-block campaignbay-mb-2 !campaignbay-text-[13px] !campaignbay-font-[700] !campaignbay-leading-[20px] !campaignbay-text-[#1e1e1e] !campaignbay-uppercase">
                Cart / Add to Cart: Minimum Quantity Message
              </label>
              <Input
                value={settings.quantity_limit_message_min}
                onChange={(e) => updateSetting("quantity_limit_message_min", e.target.value)}
              />
              <Placeholders
                options={["product_name", "min_quantity"]}
                classNames={{ root: "campaignbay-mt-2" }}
              />
            </div>
            <div>
              <label className="campaignbay-block campaignbay-mb-2 !campaignbay-text-[13px] !campaignbay-font-[700] !campaignbay-leading-[20px] !campaignbay-text-[#1e1e1e] !campaignbay-uppercase">
                Cart / Add to Cart: Maximum Quantity Message
              </label>
              <Input
                value={settings.quantity_limit_message_max}
                onChange={(e) => updateSetting("quantity_limit_message_max", e.target.value)}
              />
              <Placeholders
                options={["product_name", "max_quantity"]}
                classNames={{ root: "campaignbay-mt-2" }}
              />
            </div>
            <div>
              <label className="campaignbay-block campaignbay-mb-2 !campaignbay-text-[13px] !campaignbay-font-[700] !campaignbay-leading-[20px] !campaignbay-text-[#1e1e1e] !campaignbay-uppercase">
                Cart / Add to Cart: Step Message
              </label>
              <Input
                value={settings.quantity_limit_message_step}
                onChange={(e) => updateSetting("quantity_limit_message_step", e.target.value)}
              />
              <Placeholders
                options={["product_name", "step"]}
                classNames={{ root: "campaignbay-mt-2" }}
              />
            </div>
          </div>
        </div>
      </SettingsCard>

      <SettingsCard>
        <SectionHeader title="Product Exclusion & Prioritization" />

        <div className="campaignbay-max-w-md">
          <CustomSelect
            label="Product Page Discount Message Format"
            classNames={{
              label:
                "!campaignbay-text-[13px] !campaignbay-font-[700] !campaignbay-leading-[20px] !campaignbay-text-[#1e1e1e] !campaignbay-uppercase",
            }}
            value={settings.product_priorityMethod}
            onChange={(v) => updateSetting("product_priorityMethod", v)}
            options={[
              { label: "Apply Highest Discount", value: "apply_highest" },
              { label: "Apply Lowest Discount", value: "apply_lowest" },
              { label: "Apply First Match", value: "apply_first" },
              {
                label: "Most Recently Started Wins",
                value: "apply_recent",
              },
            ]}
          />
          <p className="campaignbay-text-xs campaignbay-text-gray-500 campaignbay-mt-2">
            Defines how multiple product-level discounts are applied.
          </p>
        </div>
      </SettingsCard>
      <QuantityTableEditModal
        isModalOpen={isModalOpen}
        setIsModalOpen={setIsModalOpen}
        options={settings.discount_table_options}
        setOptions={(value: DiscountTableOptionsType) => {
          updateSetting("discount_table_options", value);
        }}
        isSaving={isSaving}
        updateSettings={handleSave}
      />
    </div>
  );
};

const CartContent: React.FC<ContentProps> = ({ settings, updateSetting }) => (
  <div className="campaignbay-space-y-6">
    <SettingsCard>
      <SectionHeader title="Cart Page Display" />

      <div className="campaignbay-space-y-6">
        <div className="campaignbay-grid campaignbay-grid-cols-1 lg:campaignbay-grid-cols-2 campaignbay-gap-8">
          <div>
            <label className="campaignbay-block campaignbay-mb-2 !campaignbay-text-[13px] !campaignbay-font-[700] !campaignbay-leading-[20px] !campaignbay-text-[#1e1e1e] !campaignbay-uppercase">
              Cart Page Quantity Discount Message Format (Percentage)
            </label>
            <Input
              value={settings.cart_quantity_message_format_percentage}
              onChange={(e) =>
                updateSetting(
                  "cart_quantity_message_format_percentage",
                  e.target.value,
                )
              }
            />
            <Placeholders
              options={["remaining_quantity_for_next_offer", "percentage_off"]}
              classNames={{ root: "campaignbay-mt-2" }}
            />
          </div>
          <div>
            <label className="campaignbay-block campaignbay-mb-2 !campaignbay-text-[13px] !campaignbay-font-[700] !campaignbay-leading-[20px] !campaignbay-text-[#1e1e1e] !campaignbay-uppercase">
              Cart Page Quantity Discount Message Format (Fixed)
            </label>
            <Input
              value={settings.cart_quantity_message_format_fixed}
              onChange={(e) =>
                updateSetting(
                  "cart_quantity_message_format_fixed",
                  e.target.value,
                )
              }
            />
            <Placeholders
              options={["remaining_quantity_for_next_offer", "amount_off"]}
              classNames={{ root: "campaignbay-mt-2" }}
            />
          </div>
        </div>

        <div className="campaignbay-grid campaignbay-grid-cols-1 lg:campaignbay-grid-cols-2 campaignbay-gap-8">
          <div>
            <label className="campaignbay-block campaignbay-mb-2 !campaignbay-text-[13px] !campaignbay-font-[700] !campaignbay-leading-[20px] !campaignbay-text-[#1e1e1e] !campaignbay-uppercase">
              Cart Page BOGO Discount Message Format
            </label>
            <Input
              value={settings.cart_bogo_message_format}
              onChange={(e) =>
                updateSetting("cart_bogo_message_format", e.target.value)
              }
            />
            <Placeholders
              options={["title", "buy_product_name"]}
              classNames={{ root: "campaignbay-mt-2" }}
            />
          </div>
        </div>
      </div>
    </SettingsCard>

    <SettingsCard>
      <SectionHeader title="Cart Discount Options" />
      <div className="campaignbay-grid campaignbay-grid-cols-1 lg:campaignbay-grid-cols-2 campaignbay-gap-8">
        <div className="campaignbay-space-y-2">
          <Checkbox
            label="Allow Stacking with WooCommerce Coupons"
            checked={settings.cart_allowWcCouponStacking}
            onChange={(v) => updateSetting("cart_allowWcCouponStacking", v)}
            classNames={{
              label:
                "!campaignbay-text-[13px] !campaignbay-font-[700] !campaignbay-leading-[20px] !campaignbay-text-[#1e1e1e] !campaignbay-uppercase",
            }}
          />
          <p className="campaignbay-text-xs campaignbay-text-gray-500 campaignbay-pl-8">
            If checked, your campaign discounts can be combined with standard
            WooCommerce coupons.
          </p>
        </div>
        <div className="campaignbay-space-y-2">
          <Checkbox
            label="Allow Stacking with Other Discount Campaigns"
            checked={settings.cart_allowCampaignStacking}
            onChange={(v) => updateSetting("cart_allowCampaignStacking", v)}
            classNames={{
              label:
                "!campaignbay-text-[13px] !campaignbay-font-[700] !campaignbay-leading-[20px] !campaignbay-text-[#1e1e1e] !campaignbay-uppercase",
            }}
          />
          <p className="campaignbay-text-xs campaignbay-text-gray-500 campaignbay-pl-8">
            If checked, multiple active discount campaigns can apply to the same
            cart.
          </p>
        </div>
      </div>
    </SettingsCard>
  </div>
);

const AdvancedContent: React.FC<ContentProps> = ({
  settings,
  updateSetting,
}) => (
  <div className="campaignbay-space-y-6">
    <SettingsCard>
      <SectionHeader title="Advanced Settings" />

      <div className="campaignbay-space-y-2">
        <Checkbox
          label="Delete All Data on Uninstall"
          checked={settings.advanced_deleteAllOnUninstall}
          onChange={(v) => updateSetting("advanced_deleteAllOnUninstall", v)}
          classNames={{
            label:
              "!campaignbay-text-[13px] !campaignbay-font-[700] !campaignbay-leading-[20px] !campaignbay-text-[#1e1e1e] !campaignbay-uppercase",
          }}
        />
        <p className="campaignbay-text-xs campaignbay-text-gray-500 campaignbay-pl-8">
          Delete all data when the plugin is uninstalled
        </p>
      </div>
    </SettingsCard>
  </div>
);

const Settings = () => {
  const [activeTab, setActiveTab] = useState<TabType>("global");
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const { addToast } = useToast();
  const { updateStore } = useCbStoreActions();

  const defaultSettings: CampaignBaySettingsType = {
    global_enableAddon: true,
    global_calculate_discount_from: "regular_price",
    position_to_show_bulk_table: "below_cart",
    position_to_show_discount_bar: "below_cart",
    perf_enableCaching: true,
    debug_enableMode: true,

    product_message_format_percentage: "You save {percentage_off}%",
    product_message_format_fixed: "You save {amount_off} per item",
    bogo_banner_message_format:
      "Buy {buy_quantity} and get {get_quantity} free!",
    show_discount_table: true,
    product_priorityMethod: "apply_highest",
    quantity_limit_show_product_message: true,
    quantity_limit_product_message_format: "Purchase limits: {limits}",
    quantity_limit_message_min:
      'The minimum quantity for "{product_name}" is {min_quantity}.',
    quantity_limit_message_max:
      'The maximum quantity for "{product_name}" is {max_quantity}.',
    quantity_limit_message_step:
      '"{product_name}" must be purchased in multiples of {step}.',
    product_enableQuantityTable: true,
    discount_table_options: {
      show_header: true,
      title: { show: true, label: "Quantity" },
      range: { show: true, label: "Range" },
      discount: { show: true, label: "Discount", content: "value" },
    },

    cart_allowWcCouponStacking: false,
    cart_allowCampaignStacking: false,
    cart_quantity_message_format_percentage:
      "Add {remaining_quantity_for_next_offer} more and get {percentage_off}% off",
    cart_quantity_message_format_fixed:
      "Add {remaining_quantity_for_next_offer} more and get {amount_off} off per item!",
    cart_bogo_message_format: "{title} discount applied.",
    cart_bogo_cart_message_format: "Buy {buy_quantity} get {get_quantity} free",

    advanced_deleteAllOnUninstall: false,
    advanced_customCss: "",
    advanced_customJs: "",
  };

  // Initial State matching CampaignBaySettingsType
  const [settings, setSettings] =
    useState<CampaignBaySettingsType>(defaultSettings);
  // Track saved state to determine if dirty
  const [savedSettings, setSavedSettings] =
    useState<CampaignBaySettingsType>(defaultSettings);

  useEffect(() => {
    fetchSettings();
  }, []);

  const fetchSettings = async () => {
    try {
      const response: CampaignBaySettingsType = await apiFetch({
        path: "/campaignbay/v1/settings?_timestamp=" + Date.now(),
      });
      setSettings(response);
      setSavedSettings(response);
      setIsLoading(false);
    } catch (error) {
      console.error("Error fetching settings:", error);
      setError("Failed to load settings");
      addToast(
        __("Something went wrong . Please refresh the page.", "campaignbay"),
        "error",
      );
    }
  };
  // Modal State
  const [isConfirmModalOpen, setIsConfirmModalOpen] = useState(false);
  const [pendingTab, setPendingTab] = useState<TabType | null>(null);

  // Active Tab Border Animation State
  const [indicatorStyle, setIndicatorStyle] = useState({ left: 0, width: 0 });
  const tabsRef = useRef<{ [key in TabType]: HTMLButtonElement | null }>({
    global: null,
    product: null,
    cart: null,
    advanced: null,
  });

  const isDirty = JSON.stringify(settings) !== JSON.stringify(savedSettings);

  const updateSetting = (key: keyof CampaignBaySettingsType, value: any) => {
    setSettings((prev) => ({ ...prev, [key]: value }));
  };

  const handleTabChange = (newTab: TabType) => {
    if (activeTab === newTab) return;

    if (isDirty) {
      setPendingTab(newTab);
      setIsConfirmModalOpen(true);
    } else {
      setActiveTab(newTab);
    }
  };

  const confirmTabSwitch = () => {
    // Discard unsaved changes by reverting to saved settings
    setSettings(savedSettings);

    if (pendingTab) {
      setActiveTab(pendingTab);
    }
    setIsConfirmModalOpen(false);
    setPendingTab(null);
  };

  const cancelTabSwitch = () => {
    setIsConfirmModalOpen(false);
    setPendingTab(null);
  };

  const handleSave = async () => {
    setSavedSettings(settings);
    setIsSaving(true);
    // In a real application, you would make an API call here.
    try {
      const response: CampaignBaySettingsType = await apiFetch({
        path: "/campaignbay/v1/settings?_timestamp=" + Date.now(),
        method: "POST",
        data: settings,
      });
      setSettings(response);
      setSavedSettings(response);
      setIsSaving(false);
      updateStore("campaignbay_settings", response);
      addToast(__("Settings saved successfully", "campaignbay"), "success");
    } catch (error) {
      console.error("Error fetching settings:", error);
      setError("Failed to load settings");
      addToast(
        __("Something went wrong . Please refresh the page.", "campaignbay"),
        "error",
      );
      setIsSaving(false);
    }
  };

  useEffect(() => {
    const activeElement = tabsRef.current[activeTab];
    if (activeElement) {
      setIndicatorStyle({
        left: activeElement.offsetLeft,
        width: activeElement.offsetWidth,
      });
    }
  }, [activeTab]);

  return (
    <Page>
      <HeaderContainer className="campaignbay-py-[12px]">
        <Header> Settings </Header>
        <div className="campaignbay-flex campaignbay-gap-2 campaignbay-justify-end campaignbay-items-center">
          <Button
            onClick={handleSave}
            disabled={!isDirty || isLoading || isSaving}
          >
            <svg
              className="campaignbay-w-4 campaignbay-h-4 campaignbay-mr-2"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M5 13l4 4L19 7"
              />
            </svg>
            Save Changes
          </Button>
        </div>
      </HeaderContainer>

      {/* Confirmation Modal */}
      <ConfirmationModal
        isOpen={isConfirmModalOpen}
        title="Unsaved Changes"
        message="You have unsaved changes. Are you sure you want to switch tabs without saving? Your changes will be discarded."
        confirmLabel="Yes, Switch"
        cancelLabel="No, Stay"
        onConfirm={confirmTabSwitch}
        onCancel={cancelTabSwitch}
      />

      {isLoading ? (
        <div className="campaignbay-py-8 campaignbay-px-4 campaignbay-flex campaignbay-flex-col campaignbay-gap-4">
          <div className="campaignbay-flex campaignbay-justify-start campaignbay-items-center campaignbay-bg-white campaignbay-gap-4 campaignbay-p-4 campaignbay-rounded-[8px]">
            <Skeleton className="campaignbay-w-[100px] campaignbay-h-[20px]" />
            <Skeleton className="campaignbay-w-[100px] campaignbay-h-[20px]" />
            <Skeleton className="campaignbay-w-[100px] campaignbay-h-[20px]" />
            <Skeleton className="campaignbay-w-[100px] campaignbay-h-[20px]" />
          </div>
          <div className="campaignbay-flex campaignbay-flex-col campaignbay-justify-start campaignbay-items-center campaignbay-bg-white campaignbay-gap-4 campaignbay-p-4 campaignbay-rounded-[8px]">
            <div className="campaignbay-flex campaignbay-justify-start campaignbay-items-start campaignbay-gap-4  campaignbay-w-full">
              <div className="campaignbay-flex campaignbay-flex-col campaignbay-w-full campaignbay-justify-start campaignbay-items-start campaignbay-gap-2">
                <Skeleton className="campaignbay-w-[75%] campaignbay-h-[20px]" />
                <Skeleton className="campaignbay-w-[50%] campaignbay-h-[15px]" />
              </div>
              <Skeleton className="campaignbay-w-[50px] campaignbay-h-[20px]" />
            </div>
            <div className="campaignbay-flex campaignbay-justify-start campaignbay-items-start campaignbay-gap-4  campaignbay-w-full">
              <div className="campaignbay-flex campaignbay-flex-col campaignbay-w-full campaignbay-justify-start campaignbay-items-start campaignbay-gap-2">
                <Skeleton className="campaignbay-w-[75%] campaignbay-h-[20px]" />
                <Skeleton className="campaignbay-w-[50%] campaignbay-h-[15px]" />
              </div>
              <Skeleton className="campaignbay-w-[50px] campaignbay-h-[20px]" />
            </div>
            <div className="campaignbay-flex campaignbay-justify-start campaignbay-items-start campaignbay-gap-4  campaignbay-w-full">
              <div className="campaignbay-flex campaignbay-flex-col campaignbay-w-full campaignbay-justify-start campaignbay-items-start campaignbay-gap-2">
                <Skeleton className="campaignbay-w-[75%] campaignbay-h-[20px]" />
                <Skeleton className="campaignbay-w-[50%] campaignbay-h-[15px]" />
              </div>
              <Skeleton className="campaignbay-w-[50px] campaignbay-h-[20px]" />
            </div>
          </div>
          <div className="campaignbay-flex campaignbay-flex-col campaignbay-justify-start campaignbay-items-center campaignbay-bg-white campaignbay-gap-4 campaignbay-p-4 campaignbay-rounded-[8px]">
            <div className="campaignbay-flex campaignbay-justify-start campaignbay-items-start campaignbay-gap-4  campaignbay-w-full">
              <div className="campaignbay-flex campaignbay-flex-col campaignbay-w-full campaignbay-justify-start campaignbay-items-start campaignbay-gap-2">
                <Skeleton className="campaignbay-w-[75%] campaignbay-h-[20px]" />
                <Skeleton className="campaignbay-w-[50%] campaignbay-h-[15px]" />
              </div>
              <Skeleton className="campaignbay-w-[50px] campaignbay-h-[20px]" />
            </div>
            <div className="campaignbay-flex campaignbay-justify-start campaignbay-items-start campaignbay-gap-4  campaignbay-w-full">
              <div className="campaignbay-flex campaignbay-flex-col campaignbay-w-full campaignbay-justify-start campaignbay-items-start campaignbay-gap-2">
                <Skeleton className="campaignbay-w-[75%] campaignbay-h-[20px]" />
                <Skeleton className="campaignbay-w-[50%] campaignbay-h-[15px]" />
              </div>
              <Skeleton className="campaignbay-w-[50px] campaignbay-h-[20px]" />
            </div>
            <div className="campaignbay-flex campaignbay-justify-start campaignbay-items-start campaignbay-gap-4  campaignbay-w-full">
              <div className="campaignbay-flex campaignbay-flex-col campaignbay-w-full campaignbay-justify-start campaignbay-items-start campaignbay-gap-2">
                <Skeleton className="campaignbay-w-[75%] campaignbay-h-[20px]" />
                <Skeleton className="campaignbay-w-[50%] campaignbay-h-[15px]" />
              </div>
              <Skeleton className="campaignbay-w-[50px] campaignbay-h-[20px]" />
            </div>
          </div>
        </div>
      ) : (
        <div className="campaignbay-space-y-[12px]">
          {/* Tab Headers Card */}
          <SettingsCard className="!campaignbay-p-0 !campaignbay-px-4 !campaignbay-py-0">
            <div className="campaignbay-flex campaignbay-space-x-4 campaignbay-relative ">
              <TabHeader
                ref={(el) => {
                  tabsRef.current["global"] = el;
                }}
                id="global"
                label="Global Settings"
                activeTab={activeTab}
                onTabChange={handleTabChange}
              />
              <TabHeader
                ref={(el) => {
                  tabsRef.current["product"] = el;
                }}
                id="product"
                label="Product Settings"
                activeTab={activeTab}
                onTabChange={handleTabChange}
              />
              <TabHeader
                ref={(el) => {
                  tabsRef.current["cart"] = el;
                }}
                id="cart"
                label="Cart Settings"
                activeTab={activeTab}
                onTabChange={handleTabChange}
              />
              <TabHeader
                ref={(el) => {
                  tabsRef.current["advanced"] = el;
                }}
                id="advanced"
                label="Advanced Settings"
                activeTab={activeTab}
                onTabChange={handleTabChange}
              />

              {/* Sliding Indicator */}
              <div
                className="campaignbay-absolute !campaignbay-m-0 campaignbay--bottom-[1px] campaignbay-h-0.5 campaignbay-bg-primary campaignbay-transition-all campaignbay-duration-300 campaignbay-ease-in-out"
                style={{
                  left: indicatorStyle.left,
                  width: indicatorStyle.width,
                  opacity: indicatorStyle.width ? 1 : 0,
                  bottom: 0,
                }}
              />
            </div>
          </SettingsCard>

          {/* Content Area with Transition */}
          <div key={activeTab} className="animate-fade-in campaignbay-w-full">
            {activeTab === "global" && (
              <GlobalContent
                settings={settings}
                updateSetting={updateSetting}
              />
            )}
            {activeTab === "product" && (
              <ProductContent
                settings={settings}
                updateSetting={updateSetting}
                isSaving={isSaving}
                handleSave={handleSave}
              />
            )}
            {activeTab === "cart" && (
              <CartContent settings={settings} updateSetting={updateSetting} />
            )}
            {activeTab === "advanced" && (
              <AdvancedContent
                settings={settings}
                updateSetting={updateSetting}
              />
            )}
          </div>

          {/* Save Bar (Detached) */}
          <div className="campaignbay-flex campaignbay-justify-end campaignbay-mt-8">
            <Button
              onClick={handleSave}
              disabled={!isDirty || isLoading || isSaving}
            >
              <svg
                className="campaignbay-w-4 campaignbay-h-4 campaignbay-mr-2"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M5 13l4 4L19 7"
                />
              </svg>
              Save Changes
            </Button>
          </div>
        </div>
      )}
    </Page>
  );
};

export default Settings;
