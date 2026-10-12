import { FC } from "react";
import { CartTotalRuleCondition, ConditionInterface, RuleErrors } from "./type";
import UsersRule from "./UsersRule";
import { Trash2 } from "lucide-react";
import { UserRuleCondition, UserRolesCondition, UsersCondition } from "./type";
import UserRoleRule from "./UserRoleRule";
import UserRolesRule from "./UserRolesRule";
import { closeSmall, Icon } from "@wordpress/icons";
import Button from "../common/Button";
import CartTotalRule from "./CartTotalRule";
import UserStatusRule from "./UserStatusRule";
import { UserStatusCondition } from "./type";

interface RuleProps {
  rule: ConditionInterface;
  onChange: (rule: ConditionInterface) => void;
  onDelete: () => void;
  errors: RuleErrors;
}

const Rule: FC<RuleProps> = ({ rule, onChange, onDelete, errors }) => {
  return (
    <div
      className={`campaignbay-flex campaignbay-items-center campaignbay-gap-2 campaignbay-justify-between campaignbay-w-full campaignbay-bg-[#F0F0F0] campaignbay-rounded-[4px] campaignbay-p-[10px]`}
    >
      {(() => {
        switch (rule.type) {
          case "user_role":
            return (
              <UserRoleRule
                errors={errors}
                condition={rule.condition as UserRuleCondition}
                onChange={(condition) => onChange({ ...rule, condition })}
              />
            );
          case "cart_total":
            return (
              <CartTotalRule
                errors={errors}
                condition={rule.condition as CartTotalRuleCondition}
                onChange={(condition) => onChange({ ...rule, condition })}
              />
            );
          case "user_status":
            return (
              <UserStatusRule
                errors={errors}
                condition={rule.condition as UserStatusCondition}
                onChange={(condition) => onChange({ ...rule, condition })}
              />
            );
          case "user_roles":
            return (
              <UserRolesRule
                errors={errors}
                condition={rule.condition as UserRolesCondition}
                onChange={(condition) => onChange({ ...rule, condition })}
              />
            );
          case "users":
            return (
              <UsersRule
                errors={errors}
                condition={rule.condition as UsersCondition}
                onChange={(condition) => onChange({ ...rule, condition })}
              />
            );
        }
      })()}
      <div className="campaignbay-self-start campaignbay-flex campaignbay-items-start campaignbay-justify-start campaignbay-gap-2">
        <Button
          onClick={onDelete}
          variant="outline"
          color="danger"
          className="!campaignbay-p-[7px] !campaignbay-rounded-[4px] !campaignbay-border !campaignbay-border-[#cc1818] !campaignbay-text-[#cc1818] campaignbay-bg-[#ffeeee] hover:campaignbay-bg-[#ffbbbb]"
        >
          <Icon icon={closeSmall} fill="currentColor" />
        </Button>
      </div>
    </div>
  );
};

export default Rule;
