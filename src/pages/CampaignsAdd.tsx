import { useState } from "@wordpress/element";
import apiFetch from "@wordpress/api-fetch";
import { __ } from "@wordpress/i18n";
import { useToast } from "../store/toast/use-toast";
import { FC, useEffect } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import {
  Campaign as CampaignInterfaceBase,
  CampaignType,
} from "../utils/types";
import { useGuide, useGuideStep } from "../store/GuideContext";
import { TOUR_STEPS } from "../utils/tourSteps";
import { getTemplate } from "../utils/templates";
import { currentDateTime } from "../utils/Dates";
import Page from "../components/common/Page";
import Loader from "../components/common/Loader";
import Campaign from "../components/campaign/Campaign";
import CampaignLoading from "../components/campaign/CampaignLoading";
// @ts-ignore
interface CampaignInterface extends CampaignInterfaceBase {
  type: CampaignType | null;
}

const CampaignsAdd: FC = () => {
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
  const [searchParams] = useSearchParams();

  const [errors, setErrors] = useState<any>({});
  const currentDate = currentDateTime();

  // Load template data if templateId is in URL
  useEffect(() => {
    const templateId = searchParams.get("templateId");

    if (templateId && templateId !== "blank") {
      const template = getTemplate(templateId);

      if (template) {
        if (templateId === "flash_sale_20") {
          const endDateTime = currentDate.setDate(currentDate.getDate() + 7);
          setCampaign({
            ...campaign,
            ...template.campaign_data,
            schedule_enabled: true,
            // @ts-ignore
            end_datetime: endDateTime,
          });
        } else {
          setCampaign({
            ...campaign,
            ...template.campaign_data,
          });
        }
      }
    }
    setIsLoading(false);
  }, [searchParams]);
  const handleSaveCampaign = async () => {
    setIsSaving(true);
    setTourStep(0);
    const campaignData = {
      ...campaign,
      discount_value: Number(campaign.discount_value) || 0,
      // An empty max on the last quantity tier means "no upper bound" and must reach the
      // API as null, not "" (the backend rejects an empty string as an invalid integer).
      tiers: campaign.tiers?.map((tier) =>
        "max" in tier && tier.max === "" ? { ...tier, max: null } : tier,
      ),
    };
    try {
      const response = await apiFetch({
        path: "/campaignbay/v1/campaigns",
        method: "POST",
        data: campaignData,
      });
      addToast(__("Campaign saved successfully", "campaignbay"), "success");
      navigate(`/campaigns`);
    } catch (error: any) {
      if (
        error?.code === "rest_invalid_param" ||
        error?.code === "rest_validation_error"
      ) {
        addToast(__("Validation Error. Try again.", "campaignbay"), "error");
        setErrors(error?.data?.details || {});
      } else
        addToast(
          __("Something went wrong, Please reload the page.", "campaignbay"),
          "error",
        );
    }
    setIsSaving(false);
  };

  //=================================================================================
  //============================     Guide    =======================================
  //=================================================================================
  const { tourStep, setConfig, setTourStep } = useGuide();
  const TYPE_TO_STEP_MAP: Record<string, number> = {
    bogo: TOUR_STEPS.BOGO_BUY,
    quantity: TOUR_STEPS.QTY_RANGE,
    scheduled: TOUR_STEPS.SCHED_TYPE,
    earlybird: TOUR_STEPS.EB_QUANTITY,
  };
  const TYPE_TO_PREV_STEP_MAP: Record<string, number> = {
    bogo: TOUR_STEPS.BOGO_GET,
    quantity: TOUR_STEPS.QTY_TOGGLE,
    scheduled: TOUR_STEPS.SCHED_VALUE,
    earlybird: TOUR_STEPS.EB_TOGGLE,
  };

  useEffect(() => {
    if (!tourStep) return;
    const nextStepId =
      TYPE_TO_STEP_MAP[campaign?.type || "bogo"] || TOUR_STEPS.BOGO_BUY;
    const prevStepId =
      TYPE_TO_PREV_STEP_MAP[campaign?.type || "bogo"] || TOUR_STEPS.BOGO_BUY;

    if (campaign.target_type === "entire_store") {
      setConfig((prevConfig) => ({
        ...prevConfig,
        [TOUR_STEPS.TARGET_TYPE]: {
          ...prevConfig[TOUR_STEPS.TARGET_TYPE],
          onNext: ({ setStep }) => {
            setStep(nextStepId);
          },
        },
        [TOUR_STEPS.BOGO_BUY]: {
          ...prevConfig[TOUR_STEPS.BOGO_BUY],
          onPrev: ({ setStep }) => {
            setStep(TOUR_STEPS.TARGET_TYPE);
          },
        },
        [TOUR_STEPS.SCHED_TYPE]: {
          ...prevConfig[TOUR_STEPS.SCHED_TYPE],
          onPrev: ({ setStep }) => {
            setStep(TOUR_STEPS.TARGET_TYPE);
          },
        },
        [TOUR_STEPS.QTY_RANGE]: {
          ...prevConfig[TOUR_STEPS.QTY_RANGE],
          onPrev: ({ setStep }) => {
            setStep(TOUR_STEPS.TARGET_TYPE);
          },
        },
        [TOUR_STEPS.EB_QUANTITY]: {
          ...prevConfig[TOUR_STEPS.EB_QUANTITY],
          onPrev: ({ setStep }) => {
            setStep(TOUR_STEPS.TARGET_TYPE);
          },
        },
        [TOUR_STEPS.USAGE_TOGGLE]: {
          ...prevConfig[TOUR_STEPS.USAGE_TOGGLE],
          onPrev: ({ setStep }) => {
            setStep(prevStepId);
          },
        },
      }));
    } else {
      setConfig((prevConfig) => ({
        ...prevConfig,
        [TOUR_STEPS.TARGET_TYPE]: {
          ...prevConfig[TOUR_STEPS.TARGET_TYPE],
          onNext: ({ setStep }) => {
            setStep(TOUR_STEPS.TARGET_IDS);
          },
        },
        [TOUR_STEPS.TARGET_IDS]: {
          ...prevConfig[TOUR_STEPS.TARGET_IDS],
          onNext: ({ setStep }) => {
            setStep(nextStepId);
          },
        },
        [TOUR_STEPS.BOGO_BUY]: {
          ...prevConfig[TOUR_STEPS.BOGO_BUY],
          onPrev: ({ setStep }) => {
            setStep(TOUR_STEPS.TARGET_IDS);
          },
        },
        [TOUR_STEPS.SCHED_TYPE]: {
          ...prevConfig[TOUR_STEPS.SCHED_TYPE],
          onPrev: ({ setStep }) => {
            setStep(TOUR_STEPS.TARGET_IDS);
          },
        },
        [TOUR_STEPS.QTY_RANGE]: {
          ...prevConfig[TOUR_STEPS.QTY_RANGE],
          onPrev: ({ setStep }) => {
            setStep(TOUR_STEPS.TARGET_IDS);
          },
        },
        [TOUR_STEPS.EB_QUANTITY]: {
          ...prevConfig[TOUR_STEPS.EB_QUANTITY],
          onPrev: ({ setStep }) => {
            setStep(TOUR_STEPS.TARGET_IDS);
          },
        },
        [TOUR_STEPS.USAGE_TOGGLE]: {
          ...prevConfig[TOUR_STEPS.USAGE_TOGGLE],
          onPrev: ({ setStep }) => {
            setStep(TOUR_STEPS.TARGET_IDS);
          },
        },
      }));
    }
  }, [campaign.target_type, campaign.type, setConfig]);

  useEffect(() => {
    if (!tourStep) return;
    setConfig((prevConfig) => ({
      ...prevConfig,
      [TOUR_STEPS.SCHED_TOGGLE]: {
        ...prevConfig[TOUR_STEPS.SCHED_TOGGLE],
        onNext: ({ setStep }) => {
          setStep(
            campaign.schedule_enabled
              ? TOUR_STEPS.START_TIME
              : TOUR_STEPS.SAVE_BTN,
          );
        },
      },
      [TOUR_STEPS.SAVE_BTN]: {
        ...prevConfig[TOUR_STEPS.SAVE_BTN],
        onPrev: ({ setStep }) => {
          setStep(
            campaign.schedule_enabled
              ? TOUR_STEPS.END_TIME
              : TOUR_STEPS.SCHED_TOGGLE,
          );
        },
      },
    }));
  }, [campaign.schedule_enabled, setConfig]);

  //=================================================================================
  //============================     Guide    =======================================
  //=================================================================================

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
            buttonText="Save Campaign"
            handleSave={handleSaveCampaign}
            isLoading={isLoading}
            isSaving={isSaving}
          />
        )}
      </Page>
    </>
  );
};

export default CampaignsAdd;
