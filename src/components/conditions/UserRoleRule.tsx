import { RuleErrors, UserRuleCondition } from "./type";
import { FC } from "react";
import { useCbStore } from "../../store/cbStore";
import Select from "../common/Select";
import { __ } from "@wordpress/i18n";
import { renderErrorMessage } from "../campaign/Campaign";
import { Label } from "../campaign/CampaignTiers";

interface UserRoleRuleProps {
  condition: UserRuleCondition;
  onChange: (condition: UserRuleCondition) => void;
  errors: RuleErrors;
}

const UserRoleRule: FC<UserRoleRuleProps> = ({
  condition,
  onChange,
  errors,
}) => {
  const { wpSettings } = useCbStore();
  const userRoles = wpSettings?.user_roles?.map((role) => ({
    label: role.name,
    value: role.value,
  }));
  return (
    <div className="campaignbay-flex campaignbay-items-start campaignbay-gap-2">
      <Label className="campaignbay-text-[12px] campaignbay-leading-[16px] campaignbay-font-[400] campaignbay-text-[#757575] campaignbay-text-nowrap">
        {__("Apply this campaign to only users with", "campaignbay")}
      </Label>
      <div>
        <Select
          isError={!!errors?.option}
          differentDropdownWidth={true}
          className="!campaignbay-w-max"
          placeholder={__("Select User Role", "campaignbay")}
          value={condition?.option || ""}
          onChange={(value: string | number) =>
            onChange({ ...condition, option: value as string })
          }
          options={userRoles}
        />
        {renderErrorMessage(errors?.option?.message || "")}
      </div>
      <Label className="campaignbay-text-[12px] campaignbay-leading-[16px] campaignbay-font-[400] campaignbay-text-[#757575]">
        {__("role.", "campaignbay")}
      </Label>
    </div>
  );
};

export default UserRoleRule;
