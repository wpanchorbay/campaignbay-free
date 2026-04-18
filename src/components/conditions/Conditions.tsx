import { __ } from "@wordpress/i18n";
import { Dispatch, FC, SetStateAction, useMemo } from "react";
import {
  AllConditionsType,
  ConditionInterface,
  ConditionRuleType,
  ConditionsInterface,
} from "./type";
import Rule from "./Rule";
import Select from "../common/Select";
import { CampaignErrorsType, CampaignType } from "../../utils/types";
import { Toggler } from "../common/Toggler";

interface ConditionsProps {
  conditions: ConditionsInterface;
  setConditions:
    | Dispatch<SetStateAction<ConditionsInterface>>
    | ((conditions: ConditionsInterface) => void);
  errors: CampaignErrorsType;
  type: CampaignType;
}

const Conditions: FC<ConditionsProps> = ({
  conditions,
  setConditions,
  errors,
  type,
}) => {
  const handleAddNewCondition = (type: ConditionRuleType) => {
    const newCondition: ConditionInterface = {
      type: type,
      condition: { ...defaultConditions[type] },
    };
    setConditions({
      ...conditions,
      rules: [...(conditions?.rules || []), newCondition],
    });
  };

  const ifAny = useMemo(
    () => (type: ConditionRuleType) => {
      return conditions?.rules?.some(
        (rule: ConditionInterface) => rule.type === type
      );
    },
    [conditions?.rules]
  );

  const getOptions = () => {
    const disabledUserRole = ifAny("user_role") || ifAny("user_roles");
    return [
      {
        label: __("User Role", "campaignbay"),
        labelNode: disabledUserRole ? (
          <span className="campaignbay-text-pink-500">
            {__("User Role", "campaignbay")}
            <span className="campaignbay-text-gray-500 campaignbay-ml-2">
              -- Already in Use
            </span>
          </span>
        ) : (
          __("User Role", "campaignbay")
        ),
        value: "user_role",
        disabled: disabledUserRole,
      },
      {
        label: __("Cart Total", "campaignbay"),
        value: "cart_total",
      },
      {
        label: __("User Roles", "campaignbay"),
        value: "user_roles",
        variant: "buy_pro",
      },
      {
        label: __("Users", "campaignbay"),
        value: "users",
        variant: "buy_pro",
      },
    ];
  };

  return (
    <>
      <div className="campaignbay-flex campaignbay-gap-[15px] campaignbay-items-start campaignbay-flex-col">
        <div className="campaignbay-flex campaignbay-gap-0 campaignbay-items-start campaignbay-flex-col">
          <div className="campaignbay-inline-flex campaignbay-gap-2 campaignbay-items-center campaignbay-fix-toggle-group ">
            <Select
              placeholder={__("Add New Condition", "campaignbay")}
              className="!campaignbay-w-max"
              classNames={{
                container: "campaignbay-w-max",
              }}
              differentDropdownWidth
              hideIcon
              value={""}
              onChange={(value) =>
                handleAddNewCondition(value as ConditionRuleType)
              }
              // @ts-ignore
              options={getOptions()}
              
            />

            <Toggler
              classNames={{
                root: "campaignbay-w-full",
                button:
                  "campaignbay-text-[11px] campaignbay-leading-[16px] campaignbay-text-[#1e1e1e] campaignbay-py-[7px]",
              }}
              value={conditions.match_type}
              onChange={(value) =>
                setConditions({
                  ...conditions,
                  match_type: value,
                })
              }
              options={[
                {
                  label: __("Match Any", "campaignbay"),
                  value: "any",
                },
                {
                  label: __("Match All", "campaignbay"),
                  value: "all",
                },
              ]}
            />
          </div>
          <span className="campaignbay-text-[12px] campaignbay-leading-[16px] campaignbay-font-[700] campaignbay-text-[#757575] campaignbay-py-[12px]">
            {conditions.match_type === "any"
              ? __(
                  "Note: If any of the conditions are met, the discount will be applied.",
                  "campaignbay"
                )
              : __(
                  "Note: If all of the conditions are met, the discount will be applied.",
                  "campaignbay"
                )}
          </span>
        </div>
        <div className="campaignbay-flex campaignbay-flex-col campaignbay-gap-2 campaignbay-w-full">
          {conditions?.rules?.length > 0 ? (
            conditions?.rules?.map(
              (rule: ConditionInterface, index: number) => (
                <Rule
                  key={index}
                  // @ts-ignore
                  errors={errors?.rules?.[index] || null}
                  rule={rule}
                  onChange={(updatedRule) => {
                    const newRules = [...conditions.rules];
                    newRules[index] = updatedRule;
                    setConditions({
                      ...conditions,
                      rules: newRules,
                    });
                  }}
                  onDelete={() => {
                    const newRules = [...conditions.rules];
                    newRules.splice(index, 1);
                    setConditions({
                      ...conditions,
                      rules: newRules,
                    });
                  }}
                />
              )
            )
          ) : (
            <span className="campaignbay-flex campaignbay-items-center campaignbay-justify-center campaignbay-w-full campaignbay-bg-gray-100 campaignbay-py-2 campaignbay-rounded-[4px]">
              <span className="campaignbay-text-red-500 campaignbay-text-center campaignbay-w-full">
                {__("No conditions added", "campaignbay")}
              </span>
            </span>
          )}
        </div>
      </div>
    </>
  );
};

export default Conditions;

const defaultConditions: Record<string, AllConditionsType> = {
  user_role: {
    option: "",
    is_included: true,
  },
  user_roles: {
    options: [],
    is_included: true,
  },
  users: {
    options: [],
    is_included: true,
  },
};
