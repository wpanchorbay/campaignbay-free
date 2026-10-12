import { ValidationError } from "../../utils/types";

export type ConditionRuleType = "user_role" | "user_roles" | "users" | "cart_total" | "user_status";
export type UserRuleCondition = {
  option: string;
  is_included: boolean;
};
export type CartTotalRuleConditionOperator = "<=" | ">=" | "<" | ">" | "==";
export type CartTotalRuleCondition = {
  operator: CartTotalRuleConditionOperator;
  value: number;
};
export type UserStatusCondition = {
  status: "guest" | "logged_in";
};
export type UserRolesCondition = {
  options: string[];
  is_included: boolean;
};
export type UsersCondition = {
  options: number[];
  is_included: boolean;
};

export type AllConditionsType =
  | UserRuleCondition
  | UserRolesCondition
  | UsersCondition
  | CartTotalRuleCondition
  | UserStatusCondition;

export type ConditionInterface = {
  type: ConditionRuleType;
  condition: AllConditionsType;
};

export type ConditionsInterface = {
  match_type: "all" | "any";
  rules: ConditionInterface[];
};

export type RuleErrors = Record<string | number, ValidationError> | null;
