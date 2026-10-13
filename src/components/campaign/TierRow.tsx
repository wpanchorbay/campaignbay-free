// FILE: TierRow.tsx
import React, { useState, FC } from "react";
import { __ } from "@wordpress/i18n";
import { useCbStore } from "../../store/cbStore";
import { QuantityTier, QuantityTierError } from "../../utils/types";
import { useGuideStep } from "../../store/GuideContext";
import { TOUR_STEPS } from "../../utils/tourSteps";
import { Toggler } from "../common/Toggler";
import { NumberInput } from "../common/NumberInput";
import { Label } from "./CampaignTiers";
import { Input } from "../common/Input";

const LABEL_SUGGESTIONS = [
  __("Best Value", "campaignbay"),
  __("Most Popular", "campaignbay"),
  __("Bulk Deal", "campaignbay"),
];

interface TierRowProps {
  tierData: QuantityTier;
  onUpdate: (updatedTier: QuantityTier) => void;
  onRemove: (id: number | string) => void;
  onAdd: (setError: React.Dispatch<React.SetStateAction<string>>) => void;
  isLast: boolean;
  isFirst: boolean;
  errors?: QuantityTierError;
}

const TierRow: FC<TierRowProps> = ({
  tierData,
  onUpdate,
  onRemove,
  onAdd,
  isLast,
  isFirst,
  errors,
}) => {
  const [error, setError] = useState<string>("");
  const { woocommerce_currency_symbol } = useCbStore();

  //=================================================================================
  //============================     Guide    =======================================
  //=================================================================================
  const qtyRangeInputRef = useGuideStep<HTMLInputElement>(TOUR_STEPS.QTY_RANGE);
  const qtyValueInputRef = useGuideStep<HTMLInputElement>(TOUR_STEPS.QTY_VALUE);
  const qtyToggleInputRef = useGuideStep<HTMLInputElement>(
    TOUR_STEPS.QTY_TOGGLE,
  );
  const qtyAddBtnInputRef = useGuideStep<HTMLButtonElement>(
    TOUR_STEPS.QTY_ADD_BTN,
  );
  //=================================================================================
  //============================     Guide    =======================================
  //=================================================================================

  const handleChange = (name: "max" | "value", rawValue: number | null) => {
    const value = rawValue === null ? "" : rawValue;
    const updatedTier = { ...tierData, [name]: value };

    if (name === "max" && value !== "" && value < tierData.min) {
      setError(
        __("Max quantity must be greater than min quantity.", "campaignbay"),
      );
    } else {
      setError("");
    }

    onUpdate(updatedTier);
  };

  const handleTypeToggle = (newType: string) => {
    if (newType !== "percentage" && newType !== "currency") return;
    onUpdate({ ...tierData, type: newType });
  };
  return (
    <div
      className={`campaignbay-rounded-[8px] campaignbay-p-[10px] ${
        error || errors
          ? "campaignbay-border-red-100 campaignbay-bg-red-50"
          : "campaignbay-border-[#dddddd] campaignbay-bg-[#f0f0f0]"
      }`}
    >
      <div className="campaignbay-flex campaignbay-flex-wrap campaignbay-gap-4 campaignbay-items-start">
        {/* First row: Buy from X to Y */}
        <div className="campaignbay-flex campaignbay-items-start campaignbay-gap-2 campaignbay-flex-nowrap">
          <Label className="campaignbay-text-nowrap">
            {__("Buy from", "campaignbay")}
          </Label>
          <NumberInput
            value={tierData.min}
            onChange={() => {}}
            disabled
            classNames={{
              root: "campaignbay-min-w-min campaignbay-w-min",
            }}
            error={errors?.min?.message}
          />
          <Label>{__("to", "campaignbay")}</Label>
          <NumberInput
            value={tierData.max === "" ? undefined : tierData.max}
            onChange={(val) => handleChange("max", val)}
            placeholder="e.g., 5"
            error={errors?.max?.message}
            classNames={{
              root: "campaignbay-min-w-min campaignbay-w-min",
            }}

          />
        </div>

        {/* Second row: items, get X % or $ */}
        <div className="campaignbay-flex campaignbay-items-start campaignbay-gap-2 campaignbay-flex-nowrap">
          <Label className="campaignbay-text-nowrap">
            {__("Items, Get", "campaignbay")}
          </Label>
          <NumberInput
            value={tierData.value === "" ? undefined : tierData.value}
            onChange={(val) => handleChange("value", val)}
            placeholder="e.g., 10"
            min={0}
            error={errors?.value?.message}
            classNames={{
              root: "campaignbay-min-w-min campaignbay-w-min",
            }}
          />
          <Toggler
            options={[
              { label: "%", value: "percentage" },
              { label: woocommerce_currency_symbol || "$", value: "currency" },
            ]}
            value={tierData.type || "percentage"}
            onChange={handleTypeToggle}
          />

          <Label className="campaignbay-text-nowrap">
            {tierData.type === "percentage" ? "Off." : "Off per Piece."}
          </Label>
        </div>
      </div>

      {/* Optional storefront label */}
      <div className="campaignbay-flex campaignbay-flex-wrap campaignbay-items-center campaignbay-gap-2">
        <Label className="campaignbay-text-nowrap">
          {__("Label (optional)", "campaignbay")}
        </Label>
        <Input
          type="text"
          size="small"
          maxLength={40}
          aria-label={__("Tier label", "campaignbay")}
          placeholder={__("e.g., Best Value", "campaignbay")}
          value={tierData.label ?? ""}
          error={errors?.label?.message}
          classNames={{ root: "!campaignbay-w-[200px]" }}
          onChange={(e) =>
            onUpdate({ ...tierData, label: e.target.value || null })
          }
        />
        {LABEL_SUGGESTIONS.map((suggestion) => (
          <button
            key={suggestion}
            type="button"
            onClick={() => onUpdate({ ...tierData, label: suggestion })}
            className="campaignbay-text-[12px] campaignbay-bg-gray-200 hover:campaignbay-bg-gray-300 campaignbay-text-gray-700 campaignbay-rounded-md campaignbay-px-[6px] campaignbay-py-[2px] campaignbay-cursor-pointer campaignbay-border-none"
          >
            {suggestion}
          </button>
        ))}
      </div>

      {/* Actions: Add / Remove tier */}
      {(!isFirst || isLast) && (
        <div
          className={`campaignbay-flex campaignbay-gap-3 campaignbay-mt-[10px] campaignbay-pt-1.5 campaignbay-border-t ${
            error
              ? "campaignbay-border-red-200"
              : "campaignbay-border-[#dddddd]"
          }`}
        >
          {!isFirst && (
            <button
              type="button"
              onClick={() => onRemove(tierData.id)}
              className="campaignbay-text-[13px] campaignbay-text-red-600 hover:campaignbay-text-red-800 campaignbay-font-[500] campaignbay-transition-colors campaignbay-cursor-pointer campaignbay-bg-transparent campaignbay-border-none"
            >
              {__("– Remove this tier", "campaignbay")}
            </button>
          )}
          {isLast && (
            <button
              ref={isFirst ? qtyAddBtnInputRef : null}
              type="button"
              onClick={() => onAdd(setError)}
              className="campaignbay-text-[13px] campaignbay-text-[#3858e9] hover:campaignbay-text-[#2a45b8] campaignbay-font-[500] campaignbay-transition-colors campaignbay-cursor-pointer campaignbay-bg-transparent campaignbay-border-none"
            >
              {__("+ Add another tier", "campaignbay")}
            </button>
          )}
        </div>
      )}

      {/* Error message */}
      {error && (
        <span
          className={`campaignbay-mt-1 campaignbay-text-xs campaignbay-text-red-500 `}
        >
          {error}
        </span>
      )}
    </div>
  );
};

export default TierRow;
