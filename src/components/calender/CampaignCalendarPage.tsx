import React, { useEffect, useMemo, useState } from "react";
import { CampaignType } from "../../utils/types";
import { areDatesSameDay, useCalendar } from "./useCalender";
import apiFetch from "@wordpress/api-fetch";
import "../../utils/apiFetch";
import { useCbStore, useCbStoreActions } from "../../store/cbStore";
import {
  date as wpDate,
  getSettings as getDateSettings,
  getDate,
} from "@wordpress/date";
import Page from "../common/Page";
import HeaderContainer from "../common/HeaderContainer";
import Header from "../common/Header";
import { Toggler } from "../common/Toggler";
import { formatDate } from "../../utils/Dates";
import { getCampaignTypeText } from "../../pages/Dashboard";
import { useNavigate, useSearchParams } from "react-router-dom";
import { CalendarHeader } from "./Calendar";

export interface CalendarDay {
  date: Date;
  dayOfMonth: number;
  isCurrentMonth: boolean;
  isToday: boolean;
  hasEvent: boolean;
  isSelected: boolean;
}

const CAMPAIGN_TYPES: CampaignType[] = [
  "bogo",
  "scheduled",
  "quantity",
  "earlybird",
  "bogo_pro",
  "product_in_cart",
];

export interface Campaign {
  id: number;
  name: string;
  startDate: Date;
  startDateUnix: number;
  endDate?: Date | null; // Optional end date for ongoing campaigns
  endDateUnix?: number | null;
  type: CampaignType;
}

interface ApiCampaign {
  id: string; // The sample shows string "1", "2"
  name: string;
  type: string; // API might return string that matches CampaignType
  startDate: number | string; // timestamp in seconds (or string)
  endDate: number | string | null; // or null
}

const getCampaignColor = (type: CampaignType): string => {
  switch (type) {
    case "bogo":
      return "campaignbay-bg-indigo-500";
    case "scheduled":
      return "campaignbay-bg-teal-600";
    case "quantity":
      return "campaignbay-bg-orange-500";
    case "earlybird":
      return "campaignbay-bg-blue-600";
    case "bogo_pro":
      return "campaignbay-bg-green-500";
    case "product_in_cart":
      return "campaignbay-bg-red-500";
    default:
      return "campaignbay-bg-gray-500";
  }
};

const getCampaignBorderColor = (type: CampaignType): string => {
  switch (type) {
    case "bogo":
      return "campaignbay-border-indigo-500";
    case "scheduled":
      return "campaignbay-border-teal-600";
    case "quantity":
      return "campaignbay-border-orange-500";
    case "earlybird":
      return "campaignbay-border-blue-600";
    case "bogo_pro":
      return "campaignbay-border-green-500";
    case "product_in_cart":
      return "campaignbay-border-red-500";
    default:
      return "campaignbay-border-gray-500";
  }
};

const getCampaignLabel = (type: CampaignType): string => {
  switch (type) {
    case "bogo":
      return "BOGO";
    case "scheduled":
      return "Scheduled";
    case "quantity":
      return "Quantity";
    case "earlybird":
      return "Early Bird";
    case "bogo_pro":
      return "BOGO Pro";
    case "product_in_cart":
      return "Product In Cart";
    default:
      return type;
  }
};

const CampaignCalendarPage: React.FC = () => {
  const [searchParams] = useSearchParams();

  const navigate = useNavigate();
  const [selectedDate, setSelectedDate] = useState(new Date());
  const [layout, setLayout] = useState<"month" | "week" | "year">("month");
  const [visibleTypes, setVisibleTypes] =
    useState<CampaignType[]>(CAMPAIGN_TYPES);
  const [campaigns, setCampaigns] = useState<Campaign[]>([]);
  const [loading, setLoading] = useState(true);
  // Initialize with local time, will update to server time
  const { serverDate, serverDateLoaded } = useCbStoreActions();

  useEffect(() => {
    if (!serverDateLoaded) {
      return;
    }
    fetchCampaignsFromApi();
  }, [serverDateLoaded]);

  const fetchCampaignsFromApi = async () => {
    try {
      // const currentDate = new Date();
      const dif = 0;
      // const dif = currentDate.getTime() - serverDate.getTime();
      // console.log(currentDate);
      // console.log(serverDate);
      // console.log(dif);
      setLoading(true);
      const response: ApiCampaign[] = await apiFetch({
        path: "/campaignbay/v1/calendar/campaigns",
      });
      const data: Campaign[] = response.map((item) => ({
        id: typeof item.id === "string" ? parseInt(item.id, 10) : item.id,
        name: item.name,
        type: item.type as CampaignType, // Ensure API returns valid CampaignType string
        // @ts-ignore
        startDate: getDate(item.startDate * 1000 - dif), // Convert seconds to ms
        startDateUnix: item.startDate as number,
        // @ts-ignore
        endDate: item.endDate
          ? // @ts-ignore
            getDate(item.endDate * 1000 - dif)
          : null,
        endDateUnix: item.endDate as number,
      }));
      setLoading(false);
      setCampaigns(data);
    } catch (error) {
      console.error("Failed to fetch campaigns:", error);
      setLoading(false);
    }
  };

  // Use the existing hook for campaignbay-grid logic
  const {
    view, // We will manually control the header view prop based on layout
    setView,
    currentMonthName,
    currentYear,
    daysOfWeek,
    calendarGrid,
    yearViewStart,
    goToNextMonth,
    goToPrevMonth,
    goToNextYear,
    goToPrevYear,
    selectMonthAndYear,
  } = useCalendar({
    selectedDate,
    onSelectDate: setSelectedDate,
    today: serverDate,
  });

  useEffect(() => {
    const month = searchParams.get("month");
    const year = searchParams.get("year");
    if (month && year) {
      selectMonthAndYear(parseInt(month), parseInt(year));
    }
  }, [searchParams]);

  const filteredCampaigns = useMemo(() => {
    return campaigns.filter((c) => visibleTypes.includes(c.type));
  }, [campaigns, visibleTypes]);

  const toggleType = (type: CampaignType) => {
    setVisibleTypes((prev) => {
      if (prev.includes(type)) {
        return prev.filter((t) => t !== type);
      } else {
        return [...prev, type];
      }
    });
  };

  // Split campaignbay-grid into weeks (rows)
  const weeks = useMemo(() => {
    const chunks: CalendarDay[][] = [];
    for (let i = 0; i < calendarGrid.length; i += 7) {
      chunks.push(calendarGrid.slice(i, i + 7));
    }
    return chunks;
  }, [calendarGrid]);

  const headerId = React.useId();

  const handleTitleClick = () => {
    if (layout === "month") setLayout("year");
    else if (layout === "year") setLayout("month");
  };

  // Custom Navigation Logic
  const handlePrev = () => {
    if (layout === "week") {
      const newDate = new Date(selectedDate);
      newDate.setDate(newDate.getDate() - 7);
      setSelectedDate(newDate);
    } else if (layout === "year") {
      goToPrevYear();
    } else {
      goToPrevMonth();
    }
  };

  const handleNext = () => {
    if (layout === "week") {
      const newDate = new Date(selectedDate);
      newDate.setDate(newDate.getDate() + 7);
      setSelectedDate(newDate);
    } else if (layout === "year") {
      goToNextYear();
    } else {
      goToNextMonth();
    }
  };

  const handleGoToToday = () => {
    setSelectedDate(serverDate);
    // Logic for layout switch if needed
    if (layout === "year") {
      // setLayout('month');
    }
  };

  // --- RENDERERS ---

  const handleCampaignClick = (campaign: Campaign) => {
    // console.log("Campaign clicked:", campaign);
    navigate(`/campaigns/${campaign.id}`);
  };

  const renderWeekRow = (
    week: CalendarDay[],
    weekIndex: number,
    isWeekView: boolean,
  ) => {
    const weekStart = week[0].date;
    const weekEnd = week[6].date;

    // 1. Find campaigns that intersect with this week
    const activeCampaigns = filteredCampaigns
      .filter((c) => {
        const startsBeforeOrInWeek = c.startDate <= weekEnd;
        const endsAfterOrInWeek = !c.endDate || c.endDate >= weekStart;
        return startsBeforeOrInWeek && endsAfterOrInWeek;
      })
      .sort((a, b) => {
        const startDiff = a.startDate.getTime() - b.startDate.getTime();
        if (startDiff !== 0) return startDiff;
        if (!a.endDate) return -1;
        if (!b.endDate) return 1;
        return b.endDate.getTime() - a.endDate.getTime();
      });

    // 2. Assign "lanes"
    const slots: number[] = [];
    const campaignPositions = activeCampaigns.map((campaign) => {
      const isContinuingFromPrevWeek = campaign.startDate < weekStart;
      let startIdx = 0;
      if (!isContinuingFromPrevWeek) {
        const diffTime = Math.abs(
          campaign.startDate.getTime() - weekStart.getTime(),
        );
        startIdx = Math.floor(diffTime / (1000 * 60 * 60 * 24));
      }

      const isContinuingToNextWeek =
        !campaign.endDate || campaign.endDate > weekEnd;
      let endIdx = 6;
      if (!isContinuingToNextWeek && campaign.endDate) {
        const diffTime = Math.abs(
          campaign.endDate.getTime() - weekStart.getTime(),
        );
        endIdx = Math.floor(diffTime / (1000 * 60 * 60 * 24));
      }

      startIdx = Math.max(0, Math.min(6, startIdx));
      endIdx = Math.max(0, Math.min(6, endIdx));

      const span = endIdx - startIdx + 1;

      let slotIndex = 0;
      while (slots[slotIndex] >= startIdx) {
        slotIndex++;
      }
      slots[slotIndex] = endIdx;

      return {
        campaign,
        startIdx,
        span,
        slotIndex,
        isContinuingFromPrevWeek,
        isContinuingToNextWeek,
      };
    });

    const maxSlots = Math.max(0, ...slots.map((_, i) => i + 1));
    const rowHeightPx = 50 + maxSlots * 28;
    // In Week view, we want a minimum height that feels substantial
    const minHeight = isWeekView ? 300 : 100;

    return (
      <div
        key={weekIndex}
        className={`campaignbay-relative campaignbay-border-b campaignbay-border-gray-200 campaignbay-transition-all ${
          isWeekView ? "campaignbay-bg-white" : "hover:campaignbay-bg-gray-50"
        } last:campaignbay-border-b-0`}
        style={{ height: `${Math.max(minHeight, rowHeightPx)}px` }}
      >
        {/* Grid Background */}
        <div className="campaignbay-absolute campaignbay-inset-0 campaignbay-grid campaignbay-grid-cols-7 campaignbay-h-full campaignbay-pointer-events-none">
          {week.map((day, i) => (
            <div
              key={i}
              className={`campaignbay-border-r campaignbay-border-gray-200 campaignbay-p-2 last:campaignbay-border-r-0 ${
                !day.isCurrentMonth && !isWeekView
                  ? "campaignbay-bg-gray-50/50"
                  : ""
              }`}
            >
              <span
                className={`campaignbay-text-sm campaignbay-font-semibold ${
                  areDatesSameDay(day.date, serverDate)
                    ? "campaignbay-bg-blue-600 campaignbay-text-white campaignbay-w-7 campaignbay-h-7 campaignbay-flex campaignbay-items-center campaignbay-justify-center campaignbay-rounded-full campaignbay-shadow-sm"
                    : day.isCurrentMonth || isWeekView
                    ? "campaignbay-text-gray-700"
                    : "campaignbay-text-gray-400"
                }`}
              >
                {day.dayOfMonth}
              </span>
            </div>
          ))}
        </div>

        {/* Campaign Layer */}
        <div className="campaignbay-absolute campaignbay-inset-0 campaignbay-pt-10 campaignbay-px-0.5">
          {campaignPositions.map((pos, i) => {
            const roundedLeft = pos.isContinuingFromPrevWeek
              ? "campaignbay-rounded-l-none campaignbay-border-l-0"
              : "campaignbay-rounded-l-md";
            const roundedRight = pos.isContinuingToNextWeek
              ? "campaignbay-rounded-r-none campaignbay-border-r-0"
              : "campaignbay-rounded-r-md";
            const infiniteIndicator =
              !pos.campaign.endDate && pos.isContinuingToNextWeek
                ? 'campaignbay-after:content-[""] after:campaignbay-absolute after:campaignbay-right-0 after:campaignbay-top-0 after:campaignbay-bottom-0 after:campaignbay-w-4 after:campaignbay-bg-gradient-to-r campaignbay-after:from-transparent campaignbay-after:to-black/20'
                : "";
            const colorClass = getCampaignColor(pos.campaign.type);

            return (
              <div
                onClick={() => handleCampaignClick(pos.campaign)}
                key={`${pos.campaign.id}-${weekIndex}`}
                className={`campaignbay-absolute campaignbay-h-6 campaignbay-px-2 campaignbay-flex campaignbay-items-center campaignbay-shadow-sm campaignbay-text-xs campaignbay-font-bold campaignbay-text-white campaignbay-whitespace-nowrap campaignbay-overflow-hidden campaignbay-transition-all hover:campaignbay-brightness-110 hover:campaignbay-scale-[1.01] hover:campaignbay-z-10 campaignbay-cursor-pointer ${colorClass} ${roundedLeft} ${roundedRight} ${infiniteIndicator}`}
                style={{
                  left: `${(pos.startIdx / 7) * 100}%`,
                  width: `calc(${(pos.span / 7) * 100}% - 4px)`,
                  top: `${40 + pos.slotIndex * 28}px`,
                  marginLeft: "2px",
                }}
                title={`${pos.campaign.name} (${formatDate(
                  pos.campaign.startDateUnix,
                )} - ${
                  pos.campaign.endDateUnix
                    ? formatDate(pos.campaign.endDateUnix)
                    : ""
                })`}
              >
                <span className="campaignbay-truncate campaignbay-w-full campaignbay-drop-shadow-sm">
                  {pos.campaign.name}
                </span>
              </div>
            );
          })}
        </div>
      </div>
    );
  };

  const renderMonthView = () => (
    <div className="campaignbay-border campaignbay-border-gray-200 campaignbay-rounded-xl campaignbay-shadow-inner campaignbay-bg-gray-50">
      <div className="campaignbay-grid campaignbay-grid-cols-7 campaignbay-bg-white campaignbay-border-b campaignbay-border-gray-200 campaignbay-sticky campaignbay-top-0 campaignbay-z-20 campaignbay-rounded-t-xl">
        {daysOfWeek.map((day) => (
          <div
            key={day}
            className="campaignbay-py-3 campaignbay-text-center campaignbay-text-xs campaignbay-font-bold campaignbay-text-gray-500 campaignbay-uppercase campaignbay-tracking-widest"
          >
            {day}
          </div>
        ))}
      </div>
      <div className="campaignbay-bg-white campaignbay-rounded-b-xl campaignbay-overflow-hidden">
        {weeks.map((week, idx) => renderWeekRow(week, idx, false))}
      </div>
    </div>
  );

  const renderWeekView = () => {
    // Find the week that contains the selected date
    const targetWeek =
      weeks.find((week) =>
        week.some((day) => areDatesSameDay(day.date, selectedDate)),
      ) || weeks[0]; // Fallback

    return (
      <div className="campaignbay-border campaignbay-border-gray-200 campaignbay-rounded-xl campaignbay-shadow-inner campaignbay-bg-white campaignbay-overflow-hidden">
        <div className="campaignbay-grid campaignbay-grid-cols-7 campaignbay-bg-white campaignbay-border-b campaignbay-border-gray-200 campaignbay-sticky campaignbay-top-0 campaignbay-z-20">
          {daysOfWeek.map((day) => (
            <div
              key={day}
              className="campaignbay-py-3 campaignbay-text-center campaignbay-text-xs campaignbay-font-bold campaignbay-text-gray-500 campaignbay-uppercase campaignbay-tracking-widest"
            >
              {day}
            </div>
          ))}
        </div>
        {renderWeekRow(targetWeek, 0, true)}
      </div>
    );
  };

  const renderYearView = () => {
    // Generate 12 months data
    const months = Array.from({ length: 12 }, (_, i) => i);

    return (
      <div className="campaignbay-grid campaignbay-grid-cols-1 md:campaignbay-grid-cols-2 lg:campaignbay-grid-cols-3 campaignbay-gap-6">
        {months.map((monthIndex) => {
          const date = new Date(currentYear, monthIndex, 1);
          const monthName = date.toLocaleDateString("en-US", { month: "long" });
          const startDay = date.getDay();
          const daysInMonth = new Date(
            currentYear,
            monthIndex + 1,
            0,
          ).getDate();
          const daysArray = [
            ...Array(startDay).fill(null),
            ...Array.from(
              { length: daysInMonth },
              (_, i) => new Date(currentYear, monthIndex, i + 1),
            ),
          ];

          return (
            <div
              key={monthIndex}
              className="campaignbay-bg-white campaignbay-p-4 campaignbay-rounded-xl campaignbay-border campaignbay-border-gray-200 campaignbay-shadow-sm hover:campaignbay-shadow-md campaignbay-transition-shadow"
            >
              <div className="campaignbay-text-sm campaignbay-font-bold campaignbay-text-gray-900 campaignbay-mb-2 campaignbay-text-center">
                {monthName}
              </div>
              <div className="campaignbay-grid campaignbay-grid-cols-7 campaignbay-gap-1">
                {["S", "M", "T", "W", "T", "F", "S"].map((d, i) => (
                  <div
                    key={i}
                    className="campaignbay-text-[10px] campaignbay-text-center campaignbay-text-gray-400 campaignbay-font-medium"
                  >
                    {d}
                  </div>
                ))}
                {daysArray.map((dayDate, i) => {
                  if (!dayDate) return <div key={`empty-${i}`} />;

                  // Check for active campaigns on this day
                  const dayCampaigns = filteredCampaigns.filter((c) => {
                    const start = c.startDate;
                    const end = c.endDate || new Date(9999, 11, 31);
                    // Strip time
                    const d = new Date(dayDate);
                    d.setHours(0, 0, 0, 0);
                    const s = new Date(start);
                    s.setHours(0, 0, 0, 0);
                    const e = new Date(end);
                    e.setHours(0, 0, 0, 0);
                    return d >= s && d <= e;
                  });

                  const hasActivity = dayCampaigns.length > 0;
                  const isSelected = areDatesSameDay(dayDate, selectedDate);
                  const isToday = areDatesSameDay(dayDate, serverDate);

                  return (
                    <div
                      key={dayDate.getDate()}
                      onClick={() => {
                        setSelectedDate(dayDate);
                        setLayout("week");
                      }}
                      className={`
                                            campaignbay-h-8 campaignbay-flex campaignbay-flex-col campaignbay-items-center campaignbay-justify-center campaignbay-rounded-lg campaignbay-text-xs campaignbay-cursor-pointer campaignbay-transition-colors
                                            ${
                                              isSelected
                                                ? "campaignbay-bg-blue-100 campaignbay-text-blue-700 campaignbay-font-bold campaignbay-ring-1 campaignbay-ring-blue-500"
                                                : "hover:campaignbay-bg-gray-50 campaignbay-text-gray-700"
                                            }
                                            ${
                                              isToday && !isSelected
                                                ? "campaignbay-text-blue-600 campaignbay-font-bold campaignbay-bg-blue-50"
                                                : ""
                                            }
                                        `}
                    >
                      <span>{dayDate.getDate()}</span>
                      {hasActivity && (
                        <div className="campaignbay-flex -campaignbay--space-x-1 campaignbay-mt-0.5">
                          {dayCampaigns.slice(0, 3).map((c, ci) => (
                            <div
                              key={c.id}
                              className={`campaignbay-w-1.5 campaignbay-h-1.5 campaignbay-rounded-full ${getCampaignColor(
                                c.type,
                              )} campaignbay-border campaignbay-border-white`}
                            ></div>
                          ))}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
          );
        })}
      </div>
    );
  };

  return (
    <Page>
      <HeaderContainer className="campaignbay-py-[12px]">
        <Header> Campaigns Calendar </Header>
        <div className="campaignbay-flex campaignbay-gap-2 campaignbay-justify-end campaignbay-items-center">
          <Toggler
            size="small"
            value={layout}
            onChange={setLayout}
            options={[
              {
                label: "Week",
                value: "week",
              },
              {
                label: "Month",
                value: "month",
              },
              {
                label: "Year",
                value: "year",
              },
            ]}
          />
        </div>
      </HeaderContainer>
      <div className="campaignbay-w-full campaignbay-bg-white campaignbay-shadow-xl campaignbay-rounded-2xl campaignbay-py-[24px] campaignbay-px-[48px] campaignbay-flex campaignbay-flex-col campaignbay-gap-[16px]">
        <CalendarHeader
          view={layout === "year" ? "month" : "date"} // 'month' in header means "Show Year" (e.g. 2024)
          monthName={currentMonthName}
          year={currentYear}
          yearViewStart={yearViewStart}
          onPrev={handlePrev}
          onNext={handleNext}
          onGoToToday={handleGoToToday}
          onTitleClick={handleTitleClick}
          headerId={headerId}
        />

        {layout === "month" && renderMonthView()}
        {layout === "week" && renderWeekView()}
        {layout === "year" && renderYearView()}

        <div className="campaignbay-flex campaignbay-flex-wrap campaignbay-gap-6 campaignbay-text-sm">
          {CAMPAIGN_TYPES.map((type) => {
            const isSelected = visibleTypes.includes(type);
            const colorClass = getCampaignColor(type);
            const borderClass = getCampaignBorderColor(type);

            return (
              <label
                key={type}
                className="campaignbay-flex campaignbay-items-center campaignbay-space-x-2 campaignbay-cursor-pointer campaignbay-select-none group"
              >
                <input
                  type="checkbox"
                  className="campaignbay-sr-only"
                  checked={isSelected}
                  onChange={() => toggleType(type)}
                />
                <div
                  className={`campaignbay-w-5 campaignbay-h-5 campaignbay-rounded-full campaignbay-border-2 campaignbay-flex campaignbay-items-center campaignbay-justify-center campaignbay-transition-all campaignbay-duration-200 ${borderClass} ${
                    isSelected ? colorClass : "campaignbay-bg-transparent"
                  }`}
                >
                  <svg
                    className={`campaignbay-w-3 campaignbay-h-3 campaignbay-text-white campaignbay-transform campaignbay-transition-all campaignbay-duration-200 ${
                      isSelected
                        ? "campaignbay-opacity-100 campaignbay-scale-100"
                        : "campaignbay-opacity-0 campaignbay-scale-75"
                    }`}
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    strokeWidth="3"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      d="M4.5 12.75l6 6 9-13.5"
                    />
                  </svg>
                </div>
                <span
                  className={`campaignbay-capitalize campaignbay-transition-colors ${
                    isSelected
                      ? "campaignbay-text-gray-700 campaignbay-font-medium"
                      : "campaignbay-text-gray-400"
                  }`}
                >
                  {getCampaignTypeText(type)}
                </span>
              </label>
            );
          })}
        </div>
      </div>
    </Page>
  );
};

export default CampaignCalendarPage;
