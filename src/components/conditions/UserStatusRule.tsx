import { FC } from "react";
import Select from "../common/Select";
import { __ } from "@wordpress/i18n";
import { renderErrorMessage } from "../campaign/Campaign";
import { Label } from "../campaign/CampaignTiers";
import { RuleErrors, UserStatusCondition } from "./type";

interface UserStatusRuleProps {
  condition: UserStatusCondition;
  onChange: (condition: UserStatusCondition) => void;
  errors: RuleErrors;
}

const UserStatusRule: FC<UserStatusRuleProps> = ({
  condition,
  onChange,
  errors,
}) => {
  return (
    <div className="campaignbay-flex campaignbay-items-start campaignbay-gap-2">
      <Label className="campaignbay-text-[12px] campaignbay-leading-[16px] campaignbay-font-[400] campaignbay-text-[#757575] campaignbay-text-nowrap">
        {__("Apply this campaign only to", "campaignbay")}
      </Label>
      <div>
        <Select
          isError={!!errors?.status}
          differentDropdownWidth={true}
          className="!campaignbay-w-max"
          placeholder={__("Select", "campaignbay")}
          value={condition?.status || ""}
          onChange={(value: string | number) =>
            onChange({
              ...condition,
              status: value as UserStatusCondition["status"],
            })
          }
          options={[
            { label: __("Guests (not logged in)", "campaignbay"), value: "guest" },
            {
              label: __("Logged-in customers", "campaignbay"),
              value: "logged_in",
            },
          ]}
        />
        {renderErrorMessage(errors?.status?.message || "")}
      </div>
    </div>
  );
};

export default UserStatusRule;
