import { FC } from "react";
import Select from "../common/Select";
import { __ } from "@wordpress/i18n";
import { renderErrorMessage } from "../campaign/Campaign";
import { Label } from "../campaign/CampaignTiers";
import {
  CartItemsRuleCondition,
  CartTotalRuleConditionOperator,
  RuleErrors,
} from "./type";
import { NumberInput } from "../common/NumberInput";

interface CartItemsRuleProps {
  condition: CartItemsRuleCondition;
  onChange: (condition: CartItemsRuleCondition) => void;
  errors: RuleErrors;
}

const CartItemsRule: FC<CartItemsRuleProps> = ({
  condition,
  onChange,
  errors,
}) => {
  return (
    <div className="campaignbay-flex campaignbay-items-start campaignbay-gap-2">
      <Label className="campaignbay-text-[12px] campaignbay-leading-[16px] campaignbay-font-[400] campaignbay-text-[#757575] campaignbay-text-nowrap">
        {__("Apply this campaign if the total quantity of items in the cart is ", "campaignbay")}
      </Label>
      <div>
        <Select
          isError={!!errors?.operator}
          differentDropdownWidth={true}
          className="!campaignbay-w-max"
          placeholder={__("Select Operator", "campaignbay")}
          value={condition?.operator || ""}
          onChange={(value: string | number) =>
            onChange({
              ...condition,
              operator: value as CartTotalRuleConditionOperator,
            })
          }
          options={[
            {
              label: __("Greater than or equal to >= ", "campaignbay"),
              value: ">=",
            },
            {
              label: __("Less than or equal to <= ", "campaignbay"),
              value: "<=",
            },
            { label: __("Greater than > ", "campaignbay"), value: ">" },
            { label: __("Less than < ", "campaignbay"), value: "<" },
            { label: __("Equal to == ", "campaignbay"), value: "==" },
          ]}
        />
        {renderErrorMessage((errors?.operator as { message?: string } | undefined)?.message || "")}
      </div>
      <div>
        <NumberInput
          value={condition?.value}
          // @ts-ignore
          onChange={(value: number) => onChange({ ...condition, value: value })}
          placeholder={__("Enter quantity", "campaignbay")}
          isError={!!errors?.value}
        />
        {renderErrorMessage((errors?.value as { message?: string } | undefined)?.message || "")}
      </div>
    </div>
  );
};

export default CartItemsRule;
