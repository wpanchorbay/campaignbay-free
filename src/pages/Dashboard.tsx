//todo make $ sign dynamic
import { FC, ReactNode, useEffect, useState } from "react";
// @ts-ignore
import chart_placeholder from "./../../assets/img/top_p_c.svg";
// @ts-ignore
import table_placeholder from "./../../assets/img/top_p_t.svg";
import { __, _n, sprintf } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";
import { addQueryArgs } from "@wordpress/url";
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Title,
  Tooltip,
  Legend,
  ArcElement,
  ChartOptions,
} from "chart.js";
import { Line, Doughnut, Bar } from "react-chartjs-2";
import { useNavigate } from "react-router-dom";
import { useGuide } from "../store/GuideContext";
import Skeleton from "../components/common/Skeleton";
import HeaderContainer from "../components/common/HeaderContainer";
import Header from "../components/common/Header";
import { formatDate, timeDiff } from "../utils/Dates";
import Page from "../components/common/Page";
import Select from "../components/common/Select";
import { Input } from "../components/common/Input";
import { Toggler } from "../components/common/Toggler";
import ActivityLogModal from "../components/dashboard/ActivityLogModal";
import { useCbStore, useCbStoreActions } from "../store/cbStore";
import { CampaignType } from "../utils/types";
import Button from "../components/common/Button";
import { Campaign } from "../components/calender/CampaignCalendarPage";
import Calendar from "../components/calender/Calendar";

interface KpiValue {
  value: number;
  change?: number;
}
interface Kpis {
  active_campaigns: KpiValue;
  total_discount_value: KpiValue;
  discounted_orders: KpiValue;
  sales_from_campaigns: KpiValue;
}
interface DiscountTrend {
  date: string;
  total_discount_value: string;
  total_base: string;
  total_sales: string;
}
interface TopCampaign {
  campaign_id: number;
  name: string;
  value: string;
}
interface MostImpactfulType {
  type: string;
  total_sales: string;
}
interface Charts {
  discount_trends: DiscountTrend[];
  top_campaigns: TopCampaign[];
  most_impactful_types: MostImpactfulType[];
}
interface LiveCampaign {
  id: number;
  title: string;
  type: CampaignType;
  end_date?: string;
  start_date?: string;
}
interface RecentActivity {
  timestamp: string;
  campaign_id: number;
  campaign_title: string;
  action: string;
  user: string;
}
interface DashboardData {
  kpis: Kpis;
  charts: Charts;
  live_and_upcoming: {
    active: LiveCampaign[];
    scheduled: LiveCampaign[];
  };
  recent_activity: RecentActivity[];
  campaignsCalendar: Campaign[];
}

type ChartPeriod = "7days" | "30days" | "90days" | "year" | "custom";
type ChartDisplayType = "bar" | "line";

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  Title,
  Tooltip,
  Legend,
  ArcElement,
);

const Dashboard: FC = () => {
  const [dashboardData, setDashboardData] = useState<DashboardData | null>(
    null,
  );
  const [loading, setLoading] = useState<boolean>(true);
  const [selectedPeriod, setSelectedPeriod] = useState<ChartPeriod>("7days");
  const [customStart, setCustomStart] = useState<string>("");
  const [customEnd, setCustomEnd] = useState<string>("");
  const [chartType, setChartType] = useState<ChartDisplayType>("bar");
  const [isActivityModalOpen, setIsActivityModalOpen] =
    useState<boolean>(false);
  const navigate = useNavigate();
  const { setIsModalOpen } = useGuide();
  const { woocommerce_currency_symbol } = useCbStore();

  // states for calendar
  const [selectedDate, setSelectedDate] = useState<Date | null>(null);
  const { serverDate, serverDateLoaded } = useCbStoreActions();

  useEffect(() => {
    const fetchData = async () => {
      if (selectedPeriod === "custom" && (!customStart || !customEnd)) {
        return;
      }
      try {
        setLoading(true);
        const params: Record<string, string | number> = {
          period: selectedPeriod,
          _timestamp: Date.now(),
        };
        if (selectedPeriod === "custom") {
          params.start_date = customStart;
          params.end_date = customEnd;
        }
        const response: DashboardData = await apiFetch({
          path: addQueryArgs("/campaignbay/v1/dashboard", params),
        });
        setDashboardData(response);
      } catch (error) {
      } finally {
        setLoading(false);
      }
    };
    fetchData();
  }, [selectedPeriod, customStart, customEnd]);

  const formatCurrency = (value: number): string => {
    return new Intl.NumberFormat("en-US", {
      style: "currency",
      currency: "USD",
    }).format(value);
  };

  // Chart data preparation
  const getDiscountTrendsData = () => {
    if (!dashboardData?.charts?.discount_trends) {
      return {
        labels: [],
        datasets: [],
      };
    }

    const data = dashboardData.charts.discount_trends;
    return {
      labels: data.map((item) => {
        const date = new Date(item.date);
        return date.toLocaleDateString("en-US", {
          month: "short",
          day: "numeric",
        });
      }),
      datasets: [
        {
          label: __(
            `Discount Value (${woocommerce_currency_symbol})`,
            "campaignbay",
          ),
          data: data.map((item) => parseFloat(item.total_discount_value)),
          borderColor: "#183ad6",
          backgroundColor: "rgba(24, 58, 214, 0.1)",
          borderWidth: 3,
          fill: true,
          tension: 0.4,
          pointBackgroundColor: "#183ad6",
          pointBorderColor: "#ffffff",
          pointBorderWidth: 2,
          pointRadius: 6,
          pointHoverRadius: 8,
        },
        // {
        //   label: __("Orginal Order Value ($)", "campaignbay"),
        //   data: data.map((item) => parseFloat(item.total_base)),
        //   borderColor: "#ffc107",
        //   backgroundColor: "rgba(255, 193, 7, 0.1)",
        //   borderWidth: 2,
        //   fill: false,
        //   tension: 0.4,
        //   pointBackgroundColor: "#ffc107",
        //   pointBorderColor: "#ffffff",
        //   pointBorderWidth: 2,
        //   pointRadius: 6,
        //   pointHoverRadius: 8,
        // },
        {
          label: __(
            `Discounted Order Value (${woocommerce_currency_symbol})`,
            "campaignbay",
          ),
          data: data.map((item) => parseFloat(item.total_sales)),
          borderColor: "#28a745",
          backgroundColor: "rgba(40, 167, 69, 0.1)",
          borderWidth: 2,
          fill: false,
          tension: 0.4,
          pointBackgroundColor: "#28a745",
          pointBorderColor: "#ffffff",
          pointBorderWidth: 2,
          pointRadius: 6,
          pointHoverRadius: 8,
        },
      ],
    };
  };

  const getTopCampaignsData = () => {
    if (!dashboardData?.charts?.top_campaigns) {
      return {
        labels: [],
        datasets: [],
      };
    }

    const data = dashboardData.charts.top_campaigns;
    const colors = [
      "#183ad6", // Primary blue
      "#28a745", // Green
      "#ffc107", // Yellow
      "#dc3545", // Red
      "#6f42c1", // Purple
    ];

    return {
      labels: data.map((item) => item.name || `Campaign ${item.campaign_id}`),
      datasets: [
        {
          data: data.map((item) => parseFloat(item.value)),
          backgroundColor: colors.slice(0, data.length),
          borderColor: colors
            .slice(0, data.length)
            .map((color) => color + "80"),
          borderWidth: 2,
          hoverOffset: 4,
        },
      ],
    };
  };

  const getTopCampaignTypesData = () => {
    if (!dashboardData?.charts?.most_impactful_types) {
      return {
        labels: [],
        datasets: [],
      };
    }

    const data = dashboardData.charts.most_impactful_types;
    const colors = [
      "#183ad6", // Primary blue
      "#28a745", // Green
      "#ffc107", // Yellow
      "#dc3545", // Red
      "#6f42c1", // Purple
    ];

    return {
      labels: data.map((item) => item.type || "Unknown"),
      datasets: [
        {
          data: data.map((item) => parseFloat(item.total_sales)),
          backgroundColor: colors.slice(0, data.length),
          borderColor: colors
            .slice(0, data.length)
            .map((color) => color + "80"),
          borderWidth: 2,
          hoverOffset: 4,
        },
      ],
    };
  };

  // Chart options
  const lineChartOptions: ChartOptions<"line"> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: true,
      },
      tooltip: {
        backgroundColor: "rgba(0, 0, 0, 0.8)",
        titleColor: "#ffffff",
        bodyColor: "#ffffff",
        borderColor: "#183ad6",
        borderWidth: 1,
        cornerRadius: 8,
        displayColors: false,
        callbacks: {
          label: function (context) {
            return `Value: ${formatCurrency(Number(context.parsed.y))}`;
          },
        },
      },
    },
    scales: {
      x: {
        grid: {
          display: false,
        },
        ticks: {
          color: "#757575",
          font: {
            size: 12,
          },
        },
      },
      y: {
        grid: {
          color: "#e0e0e0",
          // @ts-ignore
          borderDash: [5, 5],
        },
        ticks: {
          color: "#757575",
          font: {
            size: 12,
          },
          callback: function (value) {
            return formatCurrency(value as number);
          },
        },
      },
    },
    elements: {
      point: {
        hoverRadius: 8,
      },
    },
  };

  const barChartOptions: ChartOptions<"bar"> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        display: true,
      },
      tooltip: {
        backgroundColor: "rgba(0, 0, 0, 0.8)",
        titleColor: "#ffffff",
        bodyColor: "#ffffff",
        borderColor: "#183ad6",
        borderWidth: 1,
        cornerRadius: 8,
        displayColors: false,
        callbacks: {
          label: function (context) {
            return `Value: ${formatCurrency(Number(context.parsed.y))}`;
          },
        },
      },
    },
    scales: {
      x: {
        grid: {
          display: false,
        },
        ticks: {
          color: "#757575",
          font: {
            size: 12,
          },
        },
      },
      y: {
        grid: {
          color: "#e0e0e0",
          // @ts-ignore
          borderDash: [5, 5],
        },
        ticks: {
          color: "#757575",
          font: {
            size: 12,
          },
          callback: function (value) {
            return formatCurrency(value as number);
          },
        },
      },
    },
  };

  const doughnutChartOptions: ChartOptions<"doughnut"> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: "bottom",
        labels: {
          padding: 20,
          usePointStyle: true,
          font: {
            size: 12,
          },
          color: "#757575",
        },
      },
      tooltip: {
        backgroundColor: "rgba(0, 0, 0, 0.8)",
        titleColor: "#ffffff",
        bodyColor: "#ffffff",
        borderColor: "#183ad6",
        borderWidth: 1,
        cornerRadius: 8,
        callbacks: {
          label: function (context) {
            const total = context.dataset.data.reduce((a, b) => a + b, 0);
            const percentage = ((context.parsed / total) * 100).toFixed(1);
            return `${context.label}: ${formatCurrency(
              context.parsed,
            )} (${percentage}%)`;
          },
        },
      },
    },
  };

  const handleSelectDate = (date: Date) => {
    // setSelectedDate(date);
    const month = date.getMonth() + 1;
    const year = date.getFullYear();
    // console.log(month, year);
    navigate(`/calendar?month=${month}&year=${year}`);
  };

  return (
    <>
      {loading ? (
        <>
          <div className="campaignbay-p-x-page-default">
            <HeaderContainer className="campaignbay-py-[12px]">
              <Skeleton className="campaignbay-max-w-[300px] campaignbay-w-full">
                <Header>Dashboard</Header>
              </Skeleton>
              <Skeleton className="campaignbay-max-w-[150px] campaignbay-w-full">
                <Header>Dashboard</Header>
              </Skeleton>
            </HeaderContainer>
            {/* body content */}
            <div className="campaignbay-flex campaignbay-justify-start campaignbay-flex-col xl:campaignbay-flex-row campaignbay-gap-default-big">
              {/* left content */}
              <div className="campaignbay-flex-1 campaignbay-flex campaignbay-flex-col campaignbay-justify-start campaignbay-items-stretch campaignbay-gap-default">
                {/* kpi card container */}
                <div className="campaignbay-grid campaignbay-grid-cols-2 sm:campaignbay-grid-cols-4 campaignbay-gap-default">
                  {/* kpi */}
                  {Array.from({ length: 4 }).map((_, index) => (
                    <div
                      key={index}
                      className="campaignbay-bg-white campaignbay-p-[24px] campaignbay-rounded-[8px] campaignbay-flex campaignbay-flex-col campaignbay-gap-[0px] campaignbay-justify-center campaignbay-items-center campaignbay-gap-[6px]"
                    >
                      {/* icon */}
                      <Skeleton className="campaignbay-w-[34px] campaignbay-h-[34px] !campaignbay-rounded-[8px]"></Skeleton>

                      {/* value */}
                      <Skeleton className="campaignbay-w-[80px] campaignbay-h-[20px]" />
                      {/* title */}
                      <Skeleton className="campaignbay-w-[100px] campaignbay-h-[16px]" />
                    </div>
                  ))}
                </div>

                {/* chart */}

                <div className="campaignbay-grid campaignbay-grid-cols-2 campaignbay-gap-default">
                  {/* line chart */}
                  <Card
                    className="campaignbay-col-span-2"
                    header={
                      <>
                        <Skeleton className="campaignbay-max-w-[200px] campaignbay-w-[200px] campaignbay-h-[30px]" />
                        <Skeleton className="campaignbay-w-[100px] campaignbay-h-[35px]" />{" "}
                      </>
                    }
                  >
                    <Skeleton className="campaignbay-w-full campaignbay-h-[45vh]" />
                  </Card>
                  <Card
                    className="campaignbay-col-span-1"
                    header={
                      <Skeleton className="campaignbay-max-w-[200px] campaignbay-w-[200px] campaignbay-h-[30px]" />
                    }
                  >
                    <Skeleton className="campaignbay-w-full campaignbay-h-[45vh]" />
                  </Card>
                  <Card
                    className="campaignbay-col-span-1"
                    header={
                      <Skeleton className="campaignbay-max-w-[200px] campaignbay-w-[200px] campaignbay-h-[30px]" />
                    }
                  >
                    <div className="campaignbay-flex campaignbay-flex-col campaignbay-items-center campaignbay-justify-center campaignbay-gap-[12px]">
                      <Skeleton className="campaignbay-w-[80%] campaignbay-aspect-[1] !campaignbay-rounded-[100%]" />
                      <div className="campaignbay-flex campaignbay-gap-[6px] campaignbay-w-full campaignbay-justify-center">
                        <Skeleton className="campaignbay-w-[20%] campaignbay-max-w-[100px] campaignbay-h-[20px]" />
                        <Skeleton className="campaignbay-w-[20%] campaignbay-max-w-[100px] campaignbay-h-[20px]" />
                        <Skeleton className="campaignbay-w-[20%] campaignbay-max-w-[100px] campaignbay-h-[20px]" />
                        <Skeleton className="campaignbay-w-[20%] campaignbay-max-w-[100px] campaignbay-h-[20px]" />
                      </div>
                    </div>
                  </Card>
                </div>
              </div>
              {/* right content */}
              <div className="campaignbay-w-full xl:campaignbay-w-[500px] campaignbay-h-max campaignbay-grid campaignbay-grid-cols-1 campaignbay-gap-default">
                <Card
                  header={
                    <Skeleton className="campaignbay-max-w-[200px] campaignbay-w-[200px] campaignbay-h-[30px]" />
                  }
                >
                  <>
                    <table>
                      <tbody>
                        {Array.from({ length: 5 }).map((_, index) => (
                          <tr key={index}>
                            <td className="campaignbay-min-w-[150px] campaignbay-text-default campaignbay-align-top campaignbay-capitalize">
                              <Skeleton className="campaignbay-w-[70%] campaignbay-h-[20px]" />
                            </td>
                            <td className="campaignbay-w-full campaignbay-text-default campaignbay-align-top campaignbay-capitalize">
                              <Skeleton className="campaignbay-w-[90%] campaignbay-h-[20px]" />
                              <Skeleton className="campaignbay-w-[70%] campaignbay-h-[16px] campaignbay-mt-[6px] campaignbay-mb-[12px]" />
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                    <Skeleton className="campaignbay-w-[150px] campaignbay-h-[20px] campaignbay-mt-[6px]" />
                  </>
                </Card>
                <Card
                  header={
                    <Skeleton className="campaignbay-max-w-[200px] campaignbay-w-[200px] campaignbay-h-[30px]" />
                  }
                >
                  <table>
                    <tbody>
                      {Array.from({ length: 5 }).map((_, index) => (
                        <tr key={index}>
                          <td className="campaignbay-w-full campaignbay-align-top">
                            <Skeleton className="campaignbay-w-[90%] campaignbay-h-[20px] campaignbay-mb-[12px]" />
                          </td>
                          <td className="campaignbay-min-w-[100px]">
                            <Skeleton className="campaignbay-w-[70%] campaignbay-h-[20px] campaignbay-ml-auto" />
                          </td>
                          <td className="campaignbay-min-w-[100px]">
                            <Skeleton className="campaignbay-w-[70%] campaignbay-h-[20px] campaignbay-ml-auto" />
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </Card>
                <Card
                  header={
                    <Skeleton className="campaignbay-max-w-[200px] campaignbay-w-[200px] campaignbay-h-[30px]" />
                  }
                >
                  <table>
                    <tbody>
                      {Array.from({ length: 5 }).map((_, index) => (
                        <tr key={index}>
                          <td className="campaignbay-w-full campaignbay-align-top">
                            <Skeleton className="campaignbay-w-[90%] campaignbay-h-[20px] campaignbay-mb-[12px]" />
                          </td>
                          <td className="campaignbay-min-w-[100px]">
                            <Skeleton className="campaignbay-w-[70%] campaignbay-h-[20px] campaignbay-ml-auto" />
                          </td>
                          <td className="campaignbay-min-w-[100px]">
                            <Skeleton className="campaignbay-w-[70%] campaignbay-h-[20px] campaignbay-ml-auto" />
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </Card>
              </div>
            </div>
          </div>
        </>
      ) : (
        <Page>
          <HeaderContainer className="campaignbay-py-[12px]">
            <Header>Dashboard</Header>
            <div className="campaignbay-flex campaignbay-items-center campaignbay-gap-[8px]">
            {selectedPeriod === "custom" && (
              <>
                <Input
                  value={customStart}
                  max={customEnd || undefined}
                  onChange={(e) => setCustomStart(e.target.value)}
                  type="date"
                  size="small"
                  classNames={{ root: "campaignbay-w-[140px]" }}
                  aria-label={__("Start date", "campaignbay")}
                />
                <Input
                  value={customEnd}
                  min={customStart || undefined}
                  onChange={(e) => setCustomEnd(e.target.value)}
                  type="date"
                  size="small"
                  classNames={{ root: "campaignbay-w-[140px]" }}
                  aria-label={__("End date", "campaignbay")}
                />
              </>
            )}
            <Select
              value={selectedPeriod}
              isCompact
              className="campaignbay-w-max !campaignbay-radius-[8px]"
              classNames={{
                container: "!campaignbay-rounded-[8px]",
              }}
              options={[
                { value: "7days", label: "Last 7 Days" },
                { value: "30days", label: "30 Days" },
                { value: "90days", label: "90 Days" },
                { value: "year", label: "1 Year" },
                { value: "custom", label: "Custom Range" },
              ]}
              onChange={(value) => {
                setSelectedPeriod(value as ChartPeriod);
              }}
            />
            </div>
          </HeaderContainer>
          {/* body content */}
          <div className="campaignbay-flex campaignbay-justify-start campaignbay-flex-col xl:campaignbay-flex-row campaignbay-gap-default-big">
            {/* left content */}
            <div className="campaignbay-flex-1 campaignbay-flex campaignbay-flex-col campaignbay-justify-start campaignbay-items-stretch campaignbay-gap-default">
              {/* kpi card container */}
              <div className="campaignbay-grid campaignbay-grid-cols-2 sm:campaignbay-grid-cols-4 campaignbay-gap-default">
                {/* kpi */}
                <div className="campaignbay-bg-white campaignbay-p-[24px] campaignbay-rounded-[8px] campaignbay-flex campaignbay-flex-col campaignbay-gap-[0px] campaignbay-justify-center campaignbay-items-center">
                  {/* icon */}
                  <div className="campaignbay-flex campaignbay-justify-center campaignbay-items-center campaignbay-w-[34px] campaignbay-h-[34px] campaignbay-rounded-[8px] campaignbay-bg-[#C2FFC9]">
                    <svg
                      width="24"
                      height="24"
                      viewBox="0 0 24 24"
                      fill="none"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <rect width="24" height="24" rx="4" fill="#C2FFC9" />
                      <path
                        fill-rule="evenodd"
                        clip-rule="evenodd"
                        d="M6.863 13.644L5 13.25H4.5C4.36739 13.25 4.24021 13.1973 4.14645 13.1036C4.05268 13.0098 4 12.8826 4 12.75V9.75C4 9.61739 4.05268 9.49021 4.14645 9.39645C4.24021 9.30268 4.36739 9.25 4.5 9.25H5L18 6.5H20V16H18L14.146 15.185L14.172 15.193C13.8993 16.0913 13.2995 16.8547 12.4912 17.3322C11.6828 17.8098 10.7248 17.9667 9.80636 17.7721C8.88792 17.5775 8.07589 17.0455 7.53073 16.2811C6.98557 15.5168 6.74794 14.5758 6.863 13.644ZM8.34 13.957C8.30786 14.4952 8.47 15.0271 8.79692 15.4559C9.12384 15.8847 9.59382 16.1819 10.1214 16.2935C10.6489 16.405 11.199 16.3235 11.6715 16.0637C12.144 15.8039 12.5075 15.3832 12.696 14.878L8.34 13.957ZM5.5 10.677L18.157 8H18.5V14.5H18.157L5.5 11.823V10.677Z"
                        fill="#1E1E1E"
                      />
                    </svg>
                  </div>
                  {/* value */}
                  <Header className="campaignbay-pt-1">
                    {dashboardData?.kpis?.active_campaigns?.value}
                  </Header>
                  {/* title */}
                  <span className="campaignbay-text-default campaignbay-text-center">
                    Active Campaigns
                  </span>
                </div>

                {/* kpi */}
                <div className="campaignbay-bg-white campaignbay-p-[24px] campaignbay-rounded-[8px] campaignbay-flex campaignbay-flex-col campaignbay-gap-[0px] campaignbay-justify-center campaignbay-items-center">
                  {/* icon */}
                  <div className="campaignbay-flex campaignbay-justify-center campaignbay-items-center campaignbay-w-[34px] campaignbay-h-[34px] campaignbay-rounded-[8px] campaignbay-bg-[#FFEDC6]">
                    <svg
                      width="24"
                      height="24"
                      viewBox="0 0 24 24"
                      fill="none"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <rect width="24" height="24" rx="4" fill="#FFEDC6" />
                      <path
                        d="M4.75 4C4.55109 4 4.36032 4.07902 4.21967 4.21967C4.07902 4.36032 4 4.55109 4 4.75V12.576C4 12.776 4.08 12.966 4.22 13.106L10.94 19.822C11.1549 20.0375 11.4103 20.2084 11.6914 20.325C11.9725 20.4416 12.2739 20.5015 12.5782 20.5014C12.8825 20.5013 13.1839 20.4412 13.4649 20.3244C13.746 20.2077 14.0012 20.0366 14.216 19.821L19.826 14.21L19.295 13.68L19.827 14.208C20.2581 13.7743 20.5001 13.1876 20.5001 12.576C20.5001 11.9644 20.2581 11.3777 19.827 10.944L13.104 4.22C12.9635 4.07931 12.7728 4.00018 12.574 4H4.75ZM19 12.576C19.0001 12.791 18.9153 12.9973 18.764 13.15L13.154 18.761C13.0784 18.8369 12.9885 18.8971 12.8896 18.9381C12.7907 18.9792 12.6846 19.0003 12.5775 19.0003C12.4704 19.0003 12.3643 18.9792 12.2654 18.9381C12.1665 18.8971 12.0766 18.8369 12.001 18.761L5.5 12.264V5.5H12.263L18.763 12.002C18.9146 12.1546 18.9997 12.3609 19 12.576ZM8.75 9.75C9.01522 9.75 9.26957 9.64464 9.45711 9.45711C9.64464 9.26957 9.75 9.01522 9.75 8.75C9.75 8.48478 9.64464 8.23043 9.45711 8.04289C9.26957 7.85536 9.01522 7.75 8.75 7.75C8.48478 7.75 8.23043 7.85536 8.04289 8.04289C7.85536 8.23043 7.75 8.48478 7.75 8.75C7.75 9.01522 7.85536 9.26957 8.04289 9.45711C8.23043 9.64464 8.48478 9.75 8.75 9.75Z"
                        fill="#1E1E1E"
                      />
                    </svg>
                  </div>
                  {/* value */}
                  <Header className="campaignbay-pt-1">
                    {woocommerce_currency_symbol}
                    {dashboardData?.kpis?.total_discount_value?.value}
                  </Header>
                  {/* title */}
                  <span className="campaignbay-text-default campaignbay-text-center">
                    Total Discounts
                  </span>
                </div>

                {/* kpi */}
                <div className="campaignbay-bg-white campaignbay-py-[24px] campaignbay-px-[12px] campaignbay-rounded-[8px] campaignbay-flex campaignbay-flex-col campaignbay-gap-[0px] campaignbay-justify-center campaignbay-items-center">
                  {/* icon */}
                  <div className="campaignbay-flex campaignbay-justify-center campaignbay-items-center campaignbay-w-[34px] campaignbay-h-[34px] campaignbay-rounded-[8px] campaignbay-bg-[#C6F7FF]">
                    <svg
                      width="24"
                      height="24"
                      viewBox="0 0 24 24"
                      fill="none"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <rect width="24" height="24" rx="4" fill="#C6F7FF" />
                      <path
                        fill-rule="evenodd"
                        clip-rule="evenodd"
                        d="M5.5 9.5V7.5H18.5V9.5H5.5ZM5.5 12.5V16.5H18.5V12.5H5.5ZM4 7C4 6.73478 4.10536 6.48043 4.29289 6.29289C4.48043 6.10536 4.73478 6 5 6H19C19.2652 6 19.5196 6.10536 19.7071 6.29289C19.8946 6.48043 20 6.73478 20 7V17C20 17.2652 19.8946 17.5196 19.7071 17.7071C19.5196 17.8946 19.2652 18 19 18H5C4.73478 18 4.48043 17.8946 4.29289 17.7071C4.10536 17.5196 4 17.2652 4 17V7Z"
                        fill="#1E1E1E"
                      />
                    </svg>
                  </div>
                  {/* value */}
                  <Header className="campaignbay-pt-1">
                    {dashboardData?.kpis?.discounted_orders?.value}
                  </Header>
                  {/* title */}
                  <span className="campaignbay-text-default campaignbay-text-center">
                    Discounted Orders
                  </span>
                </div>

                {/* kpi */}
                <div className="campaignbay-bg-white campaignbay-p-[24px] campaignbay-rounded-[8px] campaignbay-flex campaignbay-flex-col campaignbay-gap-[0px] campaignbay-justify-center campaignbay-items-center">
                  {/* icon */}
                  <div className="campaignbay-flex campaignbay-justify-center campaignbay-items-center campaignbay-w-[34px] campaignbay-h-[34px] campaignbay-rounded-[8px] campaignbay-bg-[#EFC6FF]">
                    <svg
                      width="24"
                      height="24"
                      viewBox="0 0 24 24"
                      fill="none"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <rect width="24" height="24" rx="4" fill="#EFC6FF" />
                      <path
                        d="M3.25 12C3.25 9.67936 4.17187 7.45376 5.81282 5.81282C7.45376 4.17187 9.67936 3.25 12 3.25C14.3206 3.25 16.5462 4.17187 18.1872 5.81282C19.8281 7.45376 20.75 9.67936 20.75 12C20.75 14.3206 19.8281 16.5462 18.1872 18.1872C16.5462 19.8281 14.3206 20.75 12 20.75C9.67936 20.75 7.45376 19.8281 5.81282 18.1872C4.17187 16.5462 3.25 14.3206 3.25 12ZM12 4.75C11.0479 4.75 10.1052 4.93753 9.22554 5.30187C8.34593 5.66622 7.5467 6.20025 6.87348 6.87348C6.20025 7.5467 5.66622 8.34593 5.30187 9.22554C4.93753 10.1052 4.75 11.0479 4.75 12C4.75 12.9521 4.93753 13.8948 5.30187 14.7745C5.66622 15.6541 6.20025 16.4533 6.87348 17.1265C7.5467 17.7997 8.34593 18.3338 9.22554 18.6981C10.1052 19.0625 11.0479 19.25 12 19.25C13.9228 19.25 15.7669 18.4862 17.1265 17.1265C18.4862 15.7669 19.25 13.9228 19.25 12C19.25 10.0772 18.4862 8.23311 17.1265 6.87348C15.7669 5.51384 13.9228 4.75 12 4.75ZM10.662 9.627C10.348 9.847 10.25 10.079 10.25 10.25C10.25 10.421 10.348 10.653 10.662 10.873C10.974 11.091 11.445 11.25 12 11.25C12.825 11.25 13.605 11.483 14.198 11.898C14.788 12.312 15.25 12.955 15.25 13.75C15.25 14.545 14.789 15.188 14.198 15.602C13.788 15.888 13.291 16.088 12.75 16.184V16.5C12.75 16.6989 12.671 16.8897 12.5303 17.0303C12.3897 17.171 12.1989 17.25 12 17.25C11.8011 17.25 11.6103 17.171 11.4697 17.0303C11.329 16.8897 11.25 16.6989 11.25 16.5V16.184C10.7309 16.097 10.2369 15.8984 9.802 15.602C9.212 15.188 8.75 14.545 8.75 13.75C8.75 13.5511 8.82902 13.3603 8.96967 13.2197C9.11032 13.079 9.30109 13 9.5 13C9.69891 13 9.88968 13.079 10.0303 13.2197C10.171 13.3603 10.25 13.5511 10.25 13.75C10.25 13.921 10.348 14.153 10.662 14.373C10.974 14.591 11.445 14.75 12 14.75C12.555 14.75 13.026 14.591 13.338 14.373C13.652 14.153 13.75 13.921 13.75 13.75C13.75 13.579 13.652 13.347 13.338 13.127C13.026 12.909 12.555 12.75 12 12.75C11.175 12.75 10.395 12.517 9.802 12.102C9.212 11.688 8.75 11.045 8.75 10.25C8.75 9.455 9.211 8.812 9.802 8.398C10.2369 8.1016 10.7309 7.90303 11.25 7.816V7.5C11.25 7.30109 11.329 7.11032 11.4697 6.96967C11.6103 6.82902 11.8011 6.75 12 6.75C12.1989 6.75 12.3897 6.82902 12.5303 6.96967C12.671 7.11032 12.75 7.30109 12.75 7.5V7.816C13.29 7.912 13.789 8.112 14.198 8.398C14.788 8.812 15.25 9.455 15.25 10.25C15.25 10.4489 15.171 10.6397 15.0303 10.7803C14.8897 10.921 14.6989 11 14.5 11C14.3011 11 14.1103 10.921 13.9697 10.7803C13.829 10.6397 13.75 10.4489 13.75 10.25C13.75 10.079 13.652 9.847 13.338 9.627C13.026 9.409 12.555 9.25 12 9.25C11.445 9.25 10.974 9.409 10.662 9.627Z"
                        fill="#1E1E1E"
                      />
                    </svg>
                  </div>
                  {/* value */}
                  <Header className="campaignbay-pt-1">
                    {woocommerce_currency_symbol}
                    {dashboardData?.kpis?.sales_from_campaigns?.value}
                  </Header>
                  {/* title */}
                  <span className="campaignbay-text-default campaignbay-text-center">
                    Total Campaign Sales
                  </span>
                </div>
              </div>

              {/* chart */}
              <div className="campaignbay-grid campaignbay-grid-cols-2 campaignbay-gap-default">
                {/* line chart */}
                <Card
                  className="campaignbay-col-span-2"
                  header={
                    <CardHeader className="campaignbay-flex campaignbay-items-center campaignbay-justify-between campaignbay-w-full campaignbay-text-nowrap">
                      <span>Daily Discount Value Trends</span>
                      <Toggler
                        size="small"
                        classNames={{
                          button:
                            "campaignbay-text-[11px] campaignbay-leading-[14px] campaignbay-text-[#1e1e1e] campaignbay-py-[4px] !campaignbay-rounded-[3px]",
                        }}
                        value={chartType}
                        onChange={(value) => setChartType(value)}
                        options={[
                          { label: "Line", value: "line" },
                          { label: "Bar", value: "bar" },
                        ]}
                      />
                    </CardHeader>
                  }
                >
                  {dashboardData?.charts?.discount_trends?.length &&
                  dashboardData?.charts?.discount_trends?.length > 0 ? (
                    chartType === "line" ? (
                      <Line
                        data={getDiscountTrendsData()}
                        options={lineChartOptions}
                        style={{
                          height: "clamp(220px, 45vh, 400px)",
                        }}
                      />
                    ) : (
                      <Bar
                        data={getDiscountTrendsData()}
                        options={barChartOptions}
                        style={{
                          height: "clamp(220px, 45vh, 400px)",
                        }}
                      />
                    )
                  ) : (
                    <div className="cb-chart-placeholder">
                      <div className="cb-chart-message">
                        {__(
                          "No discount data available for the selected period",
                          "campaignbay",
                        )}
                      </div>
                    </div>
                  )}
                </Card>
                {/* Top Performing Campaigns */}
                <Card
                  className="campaignbay-col-span-1"
                  header={<CardHeader>Top Performing Campaigns</CardHeader>}
                >
                  {dashboardData?.charts?.top_campaigns?.length &&
                  dashboardData?.charts?.top_campaigns?.length > 0 ? (
                    <Doughnut
                      data={getTopCampaignsData()}
                      options={doughnutChartOptions}
                      height={220}
                    />
                  ) : (
                    <Placeholder
                      image={chart_placeholder}
                      mainText={__("No Data Available", "campaignbay")}
                      seconderyText={__(
                        "Run a campaign to see performance data.",
                        "campaignbay",
                      )}
                    />
                  )}
                </Card>
                <Card
                  className="campaignbay-col-span-1"
                  header={
                    <CardHeader>Top Performing Campaign Types</CardHeader>
                  }
                >
                  {dashboardData?.charts?.most_impactful_types?.length &&
                  dashboardData?.charts?.most_impactful_types?.length > 0 ? (
                    <Doughnut
                      data={getTopCampaignTypesData()}
                      options={doughnutChartOptions}
                      height={220}
                    />
                  ) : (
                    <Placeholder
                      image={chart_placeholder}
                      mainText={__("No Data Available", "campaignbay")}
                      seconderyText={__(
                        "Run a campaign to see performance data.",
                        "campaignbay",
                      )}
                    />
                  )}
                </Card>
              </div>
            </div>
            {/* right content */}
            <div className="campaignbay-w-full xl:campaignbay-w-[500px] campaignbay-h-max campaignbay-grid campaignbay-grid-cols-1 campaignbay-gap-default">
              <Card
                header={
                  <div className="campaignbay-flex campaignbay-items-center campaignbay-justify-between campaignbay-w-full">
                    <CardHeader>Calendar</CardHeader>
                    <a
                      className="campaignbay-text-[#3858e9] hover:!campaignbay-text-[#3858ff] campaignbay-underline campaignbay-underline-offset-4 campaignbay-text-default campaignbay-py-[8px] campaignbay-cursor-pointer"
                      href="#/calendar"
                    >
                      {" "}
                      View Full Calendar
                    </a>
                  </div>
                }
              >
                <Calendar
                  className="campaignbay-w-full campaignbay-max-campaignbay-w-2xl campaignbay-mx-auto campaignbay-bg-white campaignbay-rounded-2xl campaignbay-p-0 campaignbay-transition-colors"
                  hasEvent={(date: Date) => {
                    if (!dashboardData?.campaignsCalendar) return false;

                    const d = new Date(date);
                    d.setHours(0, 0, 0, 0);
                    const dayTime = d.getTime();
                    return dashboardData?.campaignsCalendar?.some(
                      (campaign) => {
                        // API returns seconds, convert to ms
                        const startDate = new Date(
                          Number(campaign.startDate) * 1000,
                        );
                        const endDate = campaign.endDate
                          ? new Date(Number(campaign.endDate) * 1000)
                          : new Date(9999, 11, 31);

                        const s = new Date(startDate);
                        s.setHours(0, 0, 0, 0);
                        const e = new Date(endDate);
                        e.setHours(0, 0, 0, 0);

                        return dayTime >= s.getTime() && dayTime <= e.getTime();
                      },
                    );
                  }}
                  selectedDate={selectedDate || serverDate}
                  onSelectDate={handleSelectDate}
                  variant="bordered"
                  // "circle" | "rounded" | "bordered" | "campaignbay-grid" | "gridFill"
                />
              </Card>
              <Card header={<CardHeader>Recent Activity</CardHeader>}>
                {dashboardData?.recent_activity?.length &&
                dashboardData?.recent_activity?.length > 0 ? (
                  <>
                    <table>
                      <tbody>
                        {dashboardData?.recent_activity
                          ?.slice(0, 5)
                          .map((activity, index) => (
                            <tr key={index}>
                              <td className="campaignbay-min-w-[130px] campaignbay-text-default campaignbay-align-top">
                                {timeDiff(activity.timestamp)}
                              </td>
                              <td className="campaignbay-w-full campaignbay-pb-[8px]">
                                <div className="campaignbay-flex campaignbay-items-start campaignbay-flex-col campaignbay-gap-[4px]">
                                  <span className="">
                                    <a
                                      href={`#/campaigns/${activity.campaign_id}`}
                                      className="campaignbay-text-default !campaignbay-text-[#3858e9] hover:!campaignbay-text-[#3858e9] campaignbay-cursor-pointer campaignbay-capitalize"
                                    >
                                      {activity.campaign_title}
                                    </a>
                                  </span>
                                  <span className="campaignbay-text-[#949494] campaignbay-text-small">
                                    {activity.action + " by " + activity.user}
                                  </span>
                                </div>
                              </td>
                            </tr>
                          ))}
                      </tbody>
                    </table>
                    <button
                      className="campaignbay-text-[#3858e9] hover:!campaignbay-text-[#3858ff] campaignbay-underline campaignbay-underline-offset-4 campaignbay-text-default campaignbay-py-[8px] campaignbay-cursor-pointer"
                      onClick={() => setIsActivityModalOpen(true)}
                    >
                      View Full Activity Log
                    </button>
                  </>
                ) : (
                  <>
                    <p>No recent activity</p>
                  </>
                )}
              </Card>
              <Card header={<CardHeader>Live Campaigns</CardHeader>}>
                {dashboardData?.live_and_upcoming?.active &&
                dashboardData.live_and_upcoming.active.length > 0 ? (
                  <>
                    <table>
                      <tbody>
                        {dashboardData?.live_and_upcoming?.active
                          ?.slice(0, 5)
                          .map((campaign, index) => (
                            <tr key={index}>
                              <td className="campaignbay-w-full campaignbay-text-default campaignbay-align-top campaignbay-capitalize">
                                <a
                                  href={`#/campaigns/${campaign.id}`}
                                  className="campaignbay-text-default !campaignbay-text-[#3858e9] hover:!campaignbay-text-[#3858e9] campaignbay-cursor-pointer campaignbay-capitalize hover:campaignbay-underline campaignbay-underline-offset-4 hover:campaignbay-text-[#3858ff] focus:campaignbay-shadow-none focus:campaignbay-underline"
                                >
                                  {campaign.title}
                                </a>
                              </td>
                              <td className="campaignbay-min-w-[100px] campaignbay-text-default campaignbay-align-top campaignbay-capitalize">
                                {getCampaignTypeText(campaign.type)}
                              </td>
                              <td className="campaignbay-min-w-[100px] campaignbay-w-max campaignbay-text-default campaignbay-align-top campaignbay-capitalize campaignbay-text-nowrap">
                                {formatDate(campaign?.end_date)}
                              </td>
                            </tr>
                          ))}
                      </tbody>
                    </table>
                  </>
                ) : (
                  <Placeholder
                    className="campaignbay-max-w-[400px] campaignbay-mx-auto"
                    image={table_placeholder}
                    mainText={__("No Live Campaigns", "campaignbay")}
                    seconderyText={
                      <Button
                        variant="solid"
                        color="primary"
                        className="campaignbay-mx-auto campaignbay-mt-3"
                        onClick={() => setIsModalOpen(true)}
                      >
                        Add New Campaign
                      </Button>
                    }
                    opacity={40}
                  />
                )}
              </Card>
              <Card header={<CardHeader>Upcoming Campaigns</CardHeader>}>
                {dashboardData?.live_and_upcoming?.scheduled &&
                dashboardData.live_and_upcoming.scheduled.length > 0 ? (
                  <>
                    <table>
                      <tbody>
                        {dashboardData?.live_and_upcoming?.scheduled
                          ?.slice(0, 5)
                          .map((campaign, index) => (
                            <tr key={index}>
                              <td className="campaignbay-w-full campaignbay-text-default campaignbay-align-top campaignbay-capitalize">
                                <a
                                  href={`#/campaigns/${campaign.id}`}
                                  className="campaignbay-text-default !campaignbay-text-[#3858e9] hover:!campaignbay-text-[#3858e9] campaignbay-cursor-pointer campaignbay-capitalize hover:campaignbay-underline campaignbay-underline-offset-4 hover:campaignbay-text-[#3858ff] focus:campaignbay-shadow-none focus:campaignbay-underline"
                                >
                                  {campaign.title}
                                </a>
                              </td>
                              <td className="campaignbay-min-w-[100px] campaignbay-text-default campaignbay-align-top campaignbay-capitalize">
                                {getCampaignTypeText(campaign.type)}
                              </td>
                              <td className="campaignbay-min-w-[100px] campaignbay-w-max campaignbay-text-default campaignbay-align-top campaignbay-capitalize campaignbay-text-nowrap">
                                {formatDate(campaign?.start_date)}
                              </td>
                            </tr>
                          ))}
                      </tbody>
                    </table>
                  </>
                ) : (
                  <Placeholder
                    className="campaignbay-max-w-[400px] campaignbay-mx-auto"
                    image={table_placeholder}
                    mainText={__("No Upcoming Campaigns", "campaignbay")}
                    seconderyText={
                      <Button
                        variant="solid"
                        color="primary"
                        className="campaignbay-mx-auto campaignbay-mt-3"
                        onClick={() => setIsModalOpen(true)}
                      >
                        Add New Campaign
                      </Button>
                    }
                    opacity={40}
                  />
                )}
              </Card>
            </div>
          </div>
          {/* Activity Log Modal */}
          <ActivityLogModal
            isActivityModalOpen={isActivityModalOpen}
            setIsActivityModalOpen={setIsActivityModalOpen}
          />
        </Page>
      )}
    </>
  );
};

export default Dashboard;

const CardHeader = ({
  children,
  className,
}: {
  children: React.ReactNode;
  className?: string;
}) => {
  return (
    <span
      className={`campaignbay-font-[700] campaignbay-text-[16px] campaignbay-leading-[40px] ${className}`}
    >
      {children}
    </span>
  );
};

interface PlaceholderProps {
  image: string;
  mainText: string;
  seconderyText: ReactNode;
  opacity?: number;
  className?: string;
}
const Placeholder: FC<PlaceholderProps> = ({
  image,
  mainText,
  seconderyText,
  opacity = 40,
  className = "",
}) => {
  return (
    <div
      className={`campaignbay-relative campaignbay-h-full campaignbay-w-full campaignbay-flex campaignbay-items-center campaignbay-justify-center campaignbay-overflow-hidden ${className}`}
    >
      <img
        src={image}
        alt="top Perfprming chart"
        className={`campaignbay-object-contain campaignbay-h-full campaignbay-w-full `}
        style={{ opacity: opacity / 100 }}
      />
      <div className="campaignbay-absolute campaignbay-m-[4px] campaignbay-top-1/2 campaignbay-left-1/2 campaignbay--translate-x-1/2 campaignbay--translate-y-1/2 campaignbay-w-3/4 campaignbay-text-center campaignbay-bg-white !campaignbay-border-0">
        <h2 className="campaignbay-text-gray-500 campaignbay-text-2xl campaignbay-font-bold campaignbay-text-wrap">
          {mainText}
        </h2>

        <p className="campaignbay-text-slate-700 campaignbay-mt-[2px]">
          {seconderyText || " "}
        </p>
      </div>
    </div>
  );
};

export const Card = ({
  children,
  header,
  className,
}: {
  children: ReactNode;
  header: ReactNode;
  className?: string;
}) => {
  return (
    <div
      className={`campaignbay-bg-white campaignbay-p-[24px] campaignbay-rounded-[8px] campaignbay-shadow-sm ${className}`}
    >
      <div className="campaignbay-flex campaignbay-justify-between campaignbay-items-center campaignbay-pb-[8px] campaignbay-border-b campaignbay-border-default">
        {header}
      </div>
      <div className="campaignbay-pt-[8px]">{children}</div>
    </div>
  );
};

export const getCampaignTypeText = (type: CampaignType) => {
  switch (type) {
    case "bogo_pro":
      return "BOGO Advanced";
    case "product_in_cart":
      return "Product in Cart";
    case "quantity":
      return "Quantity";
    case "earlybird":
      return "Earlybird";
    case "scheduled":
      return "Scheduled";
    case "bogo":
      return "BOGO";
    default:
      return type;
  }
};
