import { Outlet } from "react-router-dom";
import { FC, useEffect, useState } from "react";
import Navbar from "./Navbar";
import { useCbStore } from "../../store/cbStore";
import FirstCampaign from "../Onboarding/FirstCampaign";
import { __ } from "@wordpress/i18n";
import { GuideProvider } from "../../store/GuideContext";
import { WandSparkles } from "lucide-react";

const AppLayout: FC = () => {
  const { onboarding } = useCbStore();
  const [showOnboarding, setShowOnboarding] = useState<boolean>(false);
  useEffect(() => {
    if (!onboarding?.first_campaign) {
      setShowOnboarding(true);
    }
  }, [onboarding]);
  return (
    <div className="wpab-cb-container radius-large">
      <GuideProvider>
        <Navbar />
        <Notifications />
        <FirstCampaign isOpen={showOnboarding} setIsOpen={setShowOnboarding} />
        <Outlet />
        {/* <TourGuard />
        <Guide /> */}
        <button
          className="campaignbay-fixed campaignbay-bottom-[20px] campaignbay-right-[20px] campaignbay-text-white campaignbay-rounded-full campaignbay-p-[12px] campaignbay-shadow-xl campaignbay-transition-all campaignbay-duration-300 hover:campaignbay-scale-110 hover:campaignbay-shadow-[#EE00FF]/30 campaignbay-z-[100]"
          style={{ background: "linear-gradient(to bottom, #EE00FF, #3300B3)" }}
          onClick={() => setShowOnboarding(true)}
        >
          <WandSparkles size={24} />
        </button>
      </GuideProvider>
    </div>
  );
};

export default AppLayout;

const Notifications = () => {
  const { campaignbay_settings } = useCbStore();
  const [showPromoBanner, setShowPromoBanner] = useState<boolean>(false);

  const showNotification =
    !campaignbay_settings?.global_enableAddon || showPromoBanner;

  if (showNotification) {
    return (
      <div className="campaignbay-w-full campaignbay-flex campaignbay-flex-col campaignbay-items-end campaignbay-justify-start campaignbay-p-[12px] campaignbay-gap-[12px]">
        {showPromoBanner ? (
          <div
            className="campaignbay-p-[12px] campaignbay-rounded-[8px] campaignbay-flex campaignbay-items-center campaignbay-justify-center campaignbay-gap-[16px] campaignbay-w-full campaignbay-relative campaignbay-overflow-hidden"
            style={{
              background:
                "linear-gradient(90deg, #00FF90 0%, #00FFCC 50%, #00FF90 100%)",
            }}
          >
            <p className="campaignbay-text-[#07090C] campaignbay-font-semibold campaignbay-text-[15px]">
              {__(
                "⚡ Limited Time Offer: Get Flat 50% off on CampaignBayPro!",
                "campaignbay",
              )}
            </p>
            <a
              href="https://wpanchorbay.com/campaignbay/#pricing"
              target="_blank"
              rel="noopener noreferrer"
              className="campaignbay-bg-gray-900 campaignbay-text-white campaignbay-px-[16px] campaignbay-py-[6px] campaignbay-rounded-[6px] campaignbay-text-[13px] campaignbay-font-medium campaignbay-transition-all campaignbay-duration-200 hover:campaignbay-bg-gray-800 hover:campaignbay-shadow-lg campaignbay-no-underline"
            >
              {__("Upgrade Pro", "campaignbay")}
            </a>
            <button
              onClick={() => setShowPromoBanner(false)}
              className="campaignbay-absolute campaignbay-right-[12px] campaignbay-top-1/2 campaignbay--translate-y-1/2 campaignbay-text-gray-700 hover:campaignbay-text-gray-900 campaignbay-transition-colors campaignbay-bg-transparent campaignbay-border-none campaignbay-cursor-pointer campaignbay-p-[4px]"
              aria-label="Close"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
            </button>
          </div>
        ) : null}

        {campaignbay_settings?.global_enableAddon ? null : (
          <div className="campaignbay-bg-red-50/50 campaignbay-border-red-200 campaignbay-border-[1px] campaignbay-p-[12px] campaignbay-rounded-[8px] campaignbay-flex campaignbay-items-center campaignbay-justify-start campaignbay-w-full campaignbay-backdrop-blur-[8px]">
            <p className="campaignbay-text-red-500">
              {__("CampaignBay is disabled. Enable it from ", "campaignbay")}
              <a
                href="#/settings"
                className="campaignbay-text-blue-800 campaignbay-underline campaignbay-underline-offset-4"
              >
                {__("Settings", "campaignbay")}
              </a>
              {__(" to start using CampaignBay.", "campaignbay")}
            </p>
          </div>
        )}
      </div>
    );
  }
  return null;
};
