import { useState } from "@wordpress/element";
import apiFetch from "@wordpress/api-fetch";
import { __ } from "@wordpress/i18n";
import { useToast } from "../store/toast/use-toast";
import { FC, useEffect } from "react";
import { useNavigate, useParams, useSearchParams } from "react-router-dom";
import {
  CampaignErrorsType,
  Campaign as CampaignInterfaceBase,
  CampaignType,
} from "../utils/types";
import { currentDateTime } from "../utils/Dates";
import Page from "../components/common/Page";
import Campaign from "../components/campaign/Campaign";
import getBool from "../utils/getBool";
import CampaignLoading from "../components/campaign/CampaignLoading";
// @ts-ignore
interface CampaignInterface extends CampaignInterfaceBase {
  type: CampaignType | null;
}

const CampaignsEdit: FC = () => {
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [campaign, setCampaign] = useState<CampaignInterface>({
    id: 0,
    title: "",
    status: "active",
    type: null,
    discount_type: "percentage",
    discount_value: "",
    target_type: "entire_store",
    target_ids: [],
    is_exclude: false,
    exclude_sale_items: false,
    usage_limit: null,
    schedule_enabled: false,
    start_datetime: "",
    end_datetime: "",
    tiers: [
      {
        id: 0,
        buy_quantity: 1,
        get_quantity: 1,
      },
    ],
    settings: {},
    conditions: {
      match_type: "all",
      rules: [],
    },
  });
  const navigate = useNavigate();
  const { addToast } = useToast();

  const [isDeleting, setIsDeleting] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);

  const { id } = useParams();

  const [errors, setErrors] = useState<CampaignErrorsType>({});
  const currentDate = currentDateTime();

  // Load template data if templateId is in URL
  useEffect(() => {
    fetchCampaign();
  }, [id]);

  const handleSaveCampaign = async () => {
    const campaignData = {
      ...campaign,
      discount_value: Number(campaign.discount_value),
      // An empty max on the last quantity tier means "no upper bound" and must reach the
      // API as null, not "" (the backend rejects an empty string as an invalid integer).
      tiers: campaign.tiers?.map((tier) =>
        "max" in tier && tier.max === "" ? { ...tier, max: null } : tier,
      ),
    };

    try {
      setIsSaving(true);
      const response = await apiFetch({
        path: "/campaignbay/v1/campaigns/" + id,
        method: "POST",
        data: campaignData,
      });
      setIsSaving(false);
      addToast(__("Campaign saved successfully", "campaignbay"), "success");
      navigate("/campaigns");
    } catch (error: any) {
      setIsSaving(false);
      if (
        error?.code === "rest_invalid_param" ||
        error?.code === "rest_validation_error"
      ) {
        addToast(__("Validation Error. Try again.", "campaignbay"), "error");
        setErrors(error?.data?.details);
      } else
        addToast(
          __("Something went wrong, Please reload the page.", "campaignbay"),
          "error",
        );
    }
  };

  const handleDeleteCampaign = async () => {
    try {
      setIsDeleting(true);
      const response = await apiFetch({
        path: "/campaignbay/v1/campaigns/" + id,
        method: "DELETE",
      });
      setIsDeleteModalOpen(false);
      addToast(__("Campaign deleted successfully", "campaignbay"), "success");
      navigate("/campaigns");
    } catch (error) {
      addToast(
        __("Something went wrong, Please reload the page.", "campaignbay"),
        "error",
      );
    } finally {
      setIsDeleting(false);
    }
  };

  const fetchCampaign = async () => {
    try {
      const response: any = await apiFetch({
        path: `/campaignbay/v1/campaigns/${id}?_timestamp=${Date.now()}`,
      });
      setCampaign({
        ...response,
        schedule_enabled: getBool(response.schedule_enabled),
        exclude_sale_items: getBool(response.exclude_sale_items),
        is_exclude: getBool(response.is_exclude),
        conditions: {
          match_type: "any",
          ...response.conditions,
        },
      });
      setIsLoading(false);
    } catch (error) {
      console.error("Error fetching campaign:", error);
      addToast(
        __("Something went wrong, Please reload the page.", "campaignbay"),
        "error",
      );
      setIsLoading(false);
    }
  };

  return (
    <>
      <Page>
        {isLoading ? (
          <CampaignLoading />
        ) : (
          <Campaign
            campaign={campaign}
            setCampaign={setCampaign}
            errors={errors}
            buttonText="Update Campaign"
            handleSave={handleSaveCampaign}
            isLoading={isLoading}
            isSaving={isSaving}
            showDeleteButton={true}
            onDelete={handleDeleteCampaign}
          />
        )}
      </Page>
    </>
  );
};

export default CampaignsEdit;
