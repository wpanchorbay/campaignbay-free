import { Dispatch, FC, SetStateAction, useEffect, useState } from "react";
import Page from "../components/common/Page";
import HeaderContainer from "../components/common/HeaderContainer";
import Header from "../components/common/Header";
import Button from "../components/common/Button";
import {
  arrowDown,
  arrowUp,
  blockTable,
  closeSmall,
  cog,
  copy,
  download,
  edit,
  funnel,
  Icon,
  moreVertical,
  next,
  previous,
  search,
  trash,
} from "@wordpress/icons";
import { Popover } from "../components/common/Popover";
import { Campaign, TargetType } from "../utils/types";
import { Checkbox } from "../components/common/Checkbox";
import apiFetch from "@wordpress/api-fetch";
import { addQueryArgs } from "@wordpress/url";
import { useToast } from "../store/toast/use-toast";
import { __, _n } from "@wordpress/i18n";
import { getCampaignTypeText } from "./Dashboard";
import formatDateTime, { formatDate, timeDiff } from "../utils/Dates";
import { Toggler } from "../components/common/Toggler";
import { ListSelect } from "../components/common/ListSelect";
import ImportExport from "../components/importExport/ImportExport";
import { useNavigate } from "react-router-dom";
import Select from "../components/common/Select";
import Skeleton from "../components/common/Skeleton";
import { Tooltip } from "../components/common/ToolTip";
import { ConfirmationModal } from "../components/common/ConfirmationModal";
import { exportDataToCsv } from "../components/importExport/exportDataToCsv";
import EmptyStateCampaigns from "../components/campaigns/EmptyStateCampaigns";

type ViewType = "table" | "grid";
interface TableHeader {
  key: string;
  label: string;
  sortable: boolean;
}
interface Filters {
  types: string[];
  status: string[];
  sort: string;
  order: "asc" | "desc";
  page: number;
  limit: number;
}

const headers: TableHeader[] = [
  { key: "title", label: "Title", sortable: true },
  { key: "status", label: "Status", sortable: true },
  { key: "type", label: "Type", sortable: true },
  { key: "target", label: "Target", sortable: false },
  { key: "duration", label: "Duration", sortable: false },
  { key: "usage", label: "Usage", sortable: true },
  { key: "last_modified", label: "Last Modified", sortable: true },
];
const Campaigns: FC = () => {
  const { addToast } = useToast();

  const [view, setView] = useState<ViewType>("table");

  const [filters, setFilters] = useState<Filters>({
    types: [],
    status: [],
    sort: "title",
    order: "asc",
    page: 1,
    limit: 10,
  });

  const [filterOpen, setFilterOpen] = useState<boolean>(false);
  const [loading, setLoading] = useState<boolean>(true);
  const [visibleColumns, setVisibleColumns] = useState<string[]>([
    "title",
    "status",
    "type",
    "target",
    "duration",
  ]);
  const [campaigns, setCampaigns] = useState<Campaign[]>([]);
  const [totalPages, setTotalPages] = useState<number>(1);
  const [searchQuery, setSearchQuery] = useState<string>("");
  const [selectedCampaigns, setSelectedCampaigns] = useState<number[]>([]);
  const [campaignsToDelete, setCampaignsToDelete] = useState<number[]>([]);
  const [confirmDelete, setConfirmDelete] = useState<boolean>(false);

  const handleSelectAll = (checked: boolean) => {
    if (checked) {
      setSelectedCampaigns(campaigns.map((campaign) => campaign.id));
    } else {
      setSelectedCampaigns([]);
    }
  };

  const fetchCampaigns = async () => {
    try {
      setLoading(true);
      let status = filters.status;
      if (status.includes("active")) {
        status = [...status, "scheduled"];
      }
      const queryParams = {
        page: filters.page,
        per_page: filters.limit,
        orderby: filters.sort,
        order: filters.order,
        search: searchQuery,
        status,
        type: filters.types,
        _timestamp: Date.now(),
      };
      const response: any = await apiFetch({
        path: addQueryArgs("/campaignbay/v1/campaigns", queryParams),
        method: "GET",
        parse: false,
      });

      setTotalPages(response?.headers?.get("x-wp-totalpages"));
      // setTotalItems(response?.headers?.get("x-wp-total"));
      setCampaigns(await response.json());
      setLoading(false);
    } catch (error) {
      addToast(__("Error fetching campaigns.", "campaignbay"), "error");
      setLoading(false);
    } finally {
    }
  };
  useEffect(() => {
    // @ts-ignore
    const savedView: ViewType = localStorage.getItem(
      "campaignbay_campaigns_view",
    ) as ViewType;

    if (savedView) {
      setView(savedView);
    } else {
      setView("table");
    }
  }, []);

  useEffect(() => {
    if (view) {
      localStorage.setItem("campaignbay_campaigns_view", view);
    }
  }, [view]);
  useEffect(() => {
    fetchCampaigns();
  }, [filters, searchQuery]);

  const deleteCampaigns = (ids: number[]) => {
    setCampaignsToDelete(ids);
    setConfirmDelete(true);
  };
  const performDelete = async () => {
    setConfirmDelete(false);
    setLoading(true);
    try {
      await apiFetch({
        path: `/campaignbay/v1/campaigns/bulk`,
        method: "DELETE",
        data: {
          ids: campaignsToDelete,
        },
      });
      addToast(
        _n(
          "Campaign deleted successfully",
          "Campaigns deleted successfully",
          campaignsToDelete.length,
          "campaignbay",
        ),
        "success",
      );
      setSelectedCampaigns([]);
      fetchCampaigns();
    } catch (error) {
      addToast(
        _n(
          "Error deleting campaign",
          "Error deleting campaigns",
          campaignsToDelete.length,
          "campaignbay",
        ),
        "error",
      );
    }
  };

  const duplicateCampaign = async (id: number) => {
    setLoading(true);
    try {
      await apiFetch({
        path: `/campaignbay/v1/campaigns/${id}/duplicate`,
        method: "POST",
      });
      addToast(
        __("Campaign duplicated successfully", "campaignbay"),
        "success",
      );
      fetchCampaigns();
    } catch (error) {
      addToast(__("Error duplicating campaign", "campaignbay"), "error");
    }
  };

  const exportSelectedCampaigns = () => {
    const campaignsToExport = campaigns.filter((campaign) =>
      selectedCampaigns.includes(campaign.id),
    );
    const date = new Date()
      .toISOString()
      .slice(0, 19)
      .replace("T", "-")
      .replace("_", "-");
    const filename = `campaignbay-export-${date}.csv`;
    exportDataToCsv(campaignsToExport, filename);
    addToast(__("Campaigns exported successfully.", "campaignbay"), "success");
  };
  return (
    <Page>
      <HeaderContainer className="campaignbay-py-[12px]">
        <Header> Campaigns </Header>
        <div className="campaignbay-flex campaignbay-gap-2 campaignbay-justify-end campaignbay-items-center">
          <ImportExport refresh={fetchCampaigns} />
        </div>
      </HeaderContainer>
      <div
        className={
          view === "table"
            ? "campaignbay-bg-white campaignbay-rounded-[8px] campaignbay-shadow-sm campaignbay-w-full"
            : "campaignbay-w-full campaignbay-flex campaignbay-flex-col campaignbay-gap-default"
        }
      >
        {/* serach and filter */}
        <div
          className={`campaignbay-p-[24px] campaignbay-flex campaignbay-flex-col campaignbay-items-center campaignbay-justify-between campaignbay-px-[48px] campaignbay-gap-[16px] ${
            view === "grid"
              ? "campaignbay-bg-white campaignbay-rounded-[8px] campaignbay-shadow-sm"
              : ""
          }`}
        >
          <div className="campaignbay-flex campaignbay-justify-between campaignbay-items-center campaignbay-gap-[12px]  campaignbay-w-full">
            {/* left */}
            <div className="campaignbay-flex campaignbay-items-center campaignbay-gap-[8px]">
              <label className="campaignbay-relative">
                <input
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="search"
                  className="campaignbay-w-[200px] campaignbay-text-default campaignbay-py-[5px] campaignbay-px-[8px] campaignbay-pr-[24px] campaignbay-border campaignbay-rounded-[0] campaignbay-bg-secondary campaignbay-border-1 campaignbay-border-secondary hover:campaignbay-border-primary focus:campaignbay-border-primary focus:campaignbay-outline-none campaignbay-rounded-[8px]"
                ></input>
                <Icon
                  icon={search}
                  size={20}
                  fill="currentColor"
                  className="campaignbay-text-gray-400 campaignbay-absolute campaignbay-top-[50%] campaignbay-right-[5px] campaignbay-transform campaignbay-translate-y-[-50%]"
                />
              </label>
              {/* filter triger */}
              <Button
                size="small"
                variant={filterOpen ? "solid" : "outline"}
                color="primary"
                className="!campaignbay-px-[5px] campaignbay-relative"
                onClick={() => setFilterOpen(!filterOpen)}
              >
                <Icon icon={funnel} size={20} fill="currentColor" />
                {/* badge */}
                {filters.status.length + filters.types.length > 0 ? (
                  <span className="campaignbay-block campaignbay-w-[12px] campaignbay-h-[12px] campaignbay-bg-primary campaignbay-text-white campaignbay-rounded-full campaignbay-absolute campaignbay-top-0 campaignbay-right-0 campaignbay-transform campaignbay-translate-y-[-30%] campaignbay-translate-x-[25%] campaignbay-flex campaignbay-items-center campaignbay-justify-center campaignbay-text-[9px]">
                    {filters.status.length + filters.types.length}
                  </span>
                ) : null}
              </Button>
            </div>
            <div className="campaignbay-flex campaignbay-items-center campaignbay-justify-end campaignbay-gap-[4px]">
              {/* right */}

              <Toggler
                size="small"
                classNames={{
                  root: "campaignbay-w-full",
                  button:
                    "campaignbay-text-[11px] campaignbay-leading-[14px] campaignbay-text-[#1e1e1e] campaignbay-py-[4px] !campaignbay-rounded-[3px]",
                }}
                value={view}
                onChange={(value) => setView(value)}
                options={[
                  { label: "Table", value: "table" },
                  { label: "Grid", value: "grid" },
                ]}
              />
              <Popover
                align="bottom-right"
                classNames={{
                  content: "campaignbay-w-[300px]",
                }}
                trigger={
                  <Button
                    size="small"
                    variant="ghost"
                    color="primary"
                    className="!campaignbay-px-[4px] campaignbay-text-[#1e1e1e]"
                  >
                    <Icon icon={cog} size={20} fill="currentColor" />
                  </Button>
                }
                content={
                  <PopoverContent
                    filters={filters}
                    setFilters={setFilters}
                    visibleColumns={visibleColumns}
                    setVisibleColumns={setVisibleColumns}
                    view={view}
                  />
                }
              />
            </div>
          </div>
          {/* filters */}
          {filterOpen ? (
            <FiltersAccordion filters={filters} setFilters={setFilters} />
          ) : null}
        </div>
        {/* table */}
        <div className="campaignbay-w-full">
          {loading ? (
            <CampaignsSkeleton view={view} limit={filters.limit} />
          ) : view === "table" ? (
            <Table
              campaigns={campaigns}
              visibleColumns={visibleColumns}
              selectedCampaigns={selectedCampaigns}
              setSelectedCampaigns={setSelectedCampaigns}
              handleSelectAll={handleSelectAll}
              filters={filters}
              setFilters={setFilters}
              handleDelete={deleteCampaigns}
              handleDuplicate={duplicateCampaign}
            />
          ) : (
            <CampaignsGrid
              campaigns={campaigns}
              selectedCampaigns={selectedCampaigns}
              setSelectedCampaigns={setSelectedCampaigns}
              handleDelete={deleteCampaigns}
              handleDuplicate={duplicateCampaign}
            />
          )}
        </div>
        <div
          className={`${
            view === "grid"
              ? "campaignbay-bg-white campaignbay-rounded-[8px] campaignbay-shadow-sm"
              : ""
          }`}
        >
          <Pagination
            totalPages={totalPages}
            totalItems={campaigns.length || 0}
            filters={filters}
            setFilters={setFilters}
            selectedCampaigns={selectedCampaigns}
            setSelectedCampaigns={setSelectedCampaigns}
            handleSelectAll={handleSelectAll}
            handleDelete={deleteCampaigns}
            handleExport={exportSelectedCampaigns}
          />
        </div>
      </div>
      {/* Confirmation Modal */}
      <ConfirmationModal
        isOpen={confirmDelete}
        title="Delete Campaign"
        message={`Are you sure you want to delete ${
          campaignsToDelete.length === 1 ? "this campaign" : "these campaigns"
        }?`}
        confirmLabel="Yes, Delete"
        cancelLabel="No, Cancel"
        onConfirm={performDelete}
        onCancel={() => {
          setConfirmDelete(false);
          setCampaignsToDelete([]);
        }}
        classNames={{
          button: {
            confirmColor: "danger",
          },
        }}
      />
    </Page>
  );
};

export default Campaigns;

const CampaignsSkeleton = ({
  view,
  limit = 10,
  visibleColumnsLength = 5,
}: {
  view: "table" | "grid";
  limit?: number;
  visibleColumnsLength?: number;
}) => {
  return (
    <div className="campaignbay-w-full">
      {view === "table" ? (
        <div className="campaignbay-w-full campaignbay-overflow-x-auto">
          <table className="campaignbay-w-full">
            <thead>
              <tr>
                <th className="campaignbay-text-left campaignbay-font-[11px] campaignbay-leading-[16px] campaignbay-px-[8px] campaignbay-py-[12px] campaignbay-pl-[40px]  campaignbay-text-[#1e1e1e] campaignbay-uppercase campaignbay-border-b-[1px] campaignbay-border-[#e0e0e0] campaignbay-w-[68px]">
                  <Skeleton className="campaignbay-w-[16px] campaignbay-h-[16px]" />
                </th>
                {Array.from({ length: visibleColumnsLength }).map(
                  (_, index) => (
                    <th
                      key={index}
                      className="campaignbay-text-left campaignbay-font-[11px] campaignbay-leading-[16px] campaignbay-px-[8px] campaignbay-py-[12px] campaignbay-text-[#1e1e1e] campaignbay-uppercase campaignbay-border-b-[1px] campaignbay-border-[#e0e0e0]"
                    >
                      <Skeleton className="campaignbay-w-[75%] campaignbay-h-[20px]" />
                    </th>
                  ),
                )}
                <th className="campaignbay-text-right  campaignbay-font-[11px] campaignbay-leading-[16px] campaignbay-px-[8px] campaignbay-py-[12px] campaignbay-pr-[32px] campaignbay-text-[#1e1e1e] campaignbay-uppercase campaignbay-border-b-[1px] campaignbay-border-[#e0e0e0] campaignbay-w-[182px]">
                  <Skeleton className="campaignbay-ml-auto campaignbay-w-[75%] campaignbay-h-[16px]" />
                </th>
              </tr>
            </thead>
            <tbody>
              {[...Array(limit)].map((_, index) => {
                return (
                  <tr key={index}>
                    <td className="campaignbay-text-left campaignbay-font-[11px] campaignbay-leading-[16px] campaignbay-px-[8px] campaignbay-py-[12px] campaignbay-pl-[40px]  campaignbay-text-[#1e1e1e] campaignbay-uppercase campaignbay-border-b-[1px] campaignbay-border-[#e0e0e0]">
                      <Skeleton className="campaignbay-w-[16px] campaignbay-h-[16px]" />
                    </td>
                    {Array.from({ length: visibleColumnsLength }).map(
                      (_, index) => (
                        <td
                          key={index}
                          className="campaignbay-text-left campaignbay-font-[11px] campaignbay-leading-[16px] campaignbay-px-[8px] campaignbay-py-[12px] campaignbay-text-[#1e1e1e] campaignbay-uppercase campaignbay-border-b-[1px] campaignbay-border-[#e0e0e0]"
                        >
                          <Skeleton className="campaignbay-w-[75%] campaignbay-h-[20px]" />
                        </td>
                      ),
                    )}
                    <td className="campaignbay-text-right campaignbay-font-[11px] campaignbay-leading-[16px] campaignbay-px-[8px] campaignbay-py-[12px] campaignbay-pr-[32px] campaignbay-text-[#1e1e1e] campaignbay-uppercase campaignbay-border-b-[1px] campaignbay-border-[#e0e0e0]">
                      <Skeleton className="campaignbay-ml-auto campaignbay-w-[32px] campaignbay-h-[32px]" />
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="campaignbay-grid campaignbay-grid-cols-1 md:campaignbay-grid-cols-2 2xl:campaignbay-grid-cols-5 campaignbay-gap-default ">
          {[...Array(limit)].map((_, index) => {
            return (
              <div
                key={index}
                className="campaignbay-flex campaignbay-flex-col campaignbay-items-stretch campaignbay-justify-between campaignbay-p-[12px] campaignbay-rounded-[8px] campaignbay-bg-white campaignbay-border campaignbay-border-[#e0e0e0] campaignbay-shadow-sm"
              >
                <Row>
                  <Row>
                    <Skeleton className="campaignbay-w-[16px] campaignbay-h-[16px]" />
                    <Skeleton className="campaignbay-w-[60px] campaignbay-h-[24px] !campaignbay-rounded-full" />
                  </Row>
                  <Skeleton className="campaignbay-w-[32px] campaignbay-h-[32px]" />
                </Row>
                <Column className="campaignbay-pt-4">
                  <Skeleton className="campaignbay-w-full campaignbay-h-[24px]" />
                  <Skeleton className="campaignbay-w-full campaignbay-h-[16px]" />
                  <Row>
                    <Skeleton className="campaignbay-w-[30%] campaignbay-h-[16px]" />
                    <Skeleton className="campaignbay-w-[70%] campaignbay-h-[16px]" />
                  </Row>
                  <Row>
                    <Skeleton className="campaignbay-w-[30%] campaignbay-h-[16px]" />
                    <Skeleton className="campaignbay-w-[70%] campaignbay-h-[16px]" />
                  </Row>
                  <Row>
                    <Skeleton className="campaignbay-w-[30%] campaignbay-h-[16px]" />
                    <Skeleton className="campaignbay-w-[70%] campaignbay-h-[16px]" />
                  </Row>
                  <Row>
                    <Skeleton className="campaignbay-w-[30%] campaignbay-h-[16px]" />
                    <Skeleton className="campaignbay-w-[70%] campaignbay-h-[16px]" />
                  </Row>
                  <Row>
                    <Skeleton className="campaignbay-w-[30%] campaignbay-h-[16px]" />
                    <Skeleton className="campaignbay-w-[70%] campaignbay-h-[16px]" />
                  </Row>
                  <Row>
                    <Skeleton className="campaignbay-w-[30%] campaignbay-h-[16px]" />
                    <Skeleton className="campaignbay-w-[70%] campaignbay-h-[16px]" />
                  </Row>
                </Column>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
};

interface PopoverContentProps {
  filters: Filters;
  setFilters: Dispatch<SetStateAction<Filters>>;
  visibleColumns: string[];
  setVisibleColumns: Dispatch<SetStateAction<string[]>>;
  view: ViewType;
}
const PopoverContent = ({
  filters,
  setFilters,
  visibleColumns,
  setVisibleColumns,
  view,
}: PopoverContentProps) => {
  const handleVisibleColumnsChange = (value: string) => {
    setVisibleColumns((prev) => {
      if (prev.includes(value)) {
        return prev.filter((item) => item !== value);
      } else {
        return [...prev, value];
      }
    });
  };
  return (
    <div className="campaignbay-w-full campaignbay-p-3">
      <div className="campaignbay-grid campaignbay-grid-cols-2 campaignbay-gap-2">
        {/* row 1 */}
        {/* sort by */}
        <div className="campaignbay-col-span-2">
          <span className="campaignbay-text-[11px] campaignbay-leading-[16px] campaignbay-text-[#1e1e1e] campaignbay-pb-[4px] campaignbay-uppercase">
            Sort by
          </span>
          <Select
            border="campaignbay-border-[#e0e0e0]"
            classNames={{
              container: "!campaignbay-rounded-[8px]",
              label:
                "campaignbay-uppercase campaignbay-text-[11px] campaignbay-leading-[16px] campaignbay-text-[#1e1e1e]",
            }}
            options={headers
              .filter((header) => header.sortable)
              .map((header) => ({ value: header.key, label: header.label }))}
            value={filters.sort}
            onChange={(value) =>
              setFilters({ ...filters, sort: value as string, page: 1 })
            }
          />
        </div>
        {/* order */}
        <div className="campaignbay-flex campaignbay-items-start campaignbay-justify-between campaignbay-flex-col campaignbay-col-span-2">
          <span className="campaignbay-text-[11px] campaignbay-leading-[16px] campaignbay-text-[#1e1e1e] campaignbay-pb-[4px] campaignbay-uppercase">
            Order
          </span>
          <Toggler
            classNames={{
              root: "campaignbay-w-full",
              button:
                "campaignbay-text-[11px] campaignbay-leading-[16px] campaignbay-text-[#1e1e1e] campaignbay-py-[7px]",
            }}
            options={[
              { value: "asc", label: "Ascending" },
              { value: "desc", label: "Descending" },
            ]}
            value={filters.order}
            onChange={(value) =>
              setFilters({
                ...filters,
                order: value as "asc" | "desc",
                page: 1,
              })
            }
          />
        </div>
        {/* row 2 */}
        {/* item per page */}
        <div className="campaignbay-flex campaignbay-items-start campaignbay-justify-between campaignbay-flex-col campaignbay-col-span-2">
          <span className="campaignbay-text-[11px] campaignbay-leading-[16px] campaignbay-text-[#1e1e1e] campaignbay-pb-[4px] campaignbay-uppercase">
            Item per page
          </span>
          <Toggler
            classNames={{
              root: "campaignbay-w-full",
              button:
                "campaignbay-text-[11px] campaignbay-leading-[16px] campaignbay-text-[#1e1e1e] campaignbay-py-[7px]",
            }}
            options={[
              { value: "10", label: "10" },
              { value: "25", label: "25" },
              { value: "50", label: "50" },
              { value: "100", label: "100" },
            ]}
            value={filters.limit}
            onChange={(value) =>
              setFilters({ ...filters, limit: value as number, page: 1 })
            }
          />
        </div>
        {/* row 3 */}
        {/* visible columns */}
        {view === "table" ? (
          <div className="campaignbay-flex campaignbay-items-start campaignbay-justify-between campaignbay-flex-col campaignbay-col-span-2">
            <span className="campaignbay-text-[11px] campaignbay-leading-[16px] campaignbay-text-[#1e1e1e] campaignbay-pb-[4px] campaignbay-uppercase">
              Properties
            </span>
            <ListSelect
              classNames={{
                root: "campaignbay-w-full",
                item: "campaignbay-p-0",
                label:
                  "campaignbay-text-[11px] campaignbay-leading-[16px] campaignbay-text-[#1e1e1e] campaignbay-py-[2px]",
              }}
              items={headers.map((header) => ({
                value: header.key,
                label: header.label,
              }))}
              selectedValues={visibleColumns}
              onChange={(value) => handleVisibleColumnsChange(value)}
            />
          </div>
        ) : null}
      </div>
    </div>
  );
};

const FiltersAccordion = ({
  filters,
  setFilters,
}: {
  filters: Filters;
  setFilters: Dispatch<SetStateAction<Filters>>;
}) => {
  const statusOptions = [
    { label: "Active", value: "active" },
    { label: "Inactive", value: "inactive" },
    // { label: "Scheduled", value: "scheduled" },
    { label: "Expired", value: "expired" },
  ];

  const typeOptions = [
    { label: "BOGO", value: "bogo" },
    { label: "Quantity", value: "quantity" },
    { label: "Earlybird", value: "earlybird" },
    { label: "Scheduled", value: "scheduled" },
  ];

  const removeFilter = (key: "status" | "types", value: string) => {
    setFilters((prev) => ({
      ...prev,
      [key]: prev[key].filter((item) => item !== value),
    }));
  };

  const addFilter = (key: "status" | "types", value: string) => {
    setFilters((prev) => {
      if (prev[key].includes(value)) return prev;
      return {
        ...prev,
        [key]: [...prev[key], value],
        page: 1,
      };
    });
  };

  const resetFilters = () => {
    setFilters((prev) => ({
      ...prev,
      status: [],
      types: [],
      page: 1,
    }));
  };

  const hasActiveFilters =
    filters.status.length > 0 || filters.types.length > 0;

  // Add Filter Menu Content Component
  const AddFilterMenu = () => {
    const [view, setView] = useState<"root" | "status" | "types">("root");

    if (view === "status") {
      return (
        <div className="campaignbay-p-2 campaignbay-w-[200px]">
          <div
            className="campaignbay-flex campaignbay-items-center campaignbay-gap-2 campaignbay-px-3 campaignbay-py-2 campaignbay-mb-1 campaignbay-text-xs campaignbay-font-semibold campaignbay-text-gray-500 campaignbay-uppercase campaignbay-cursor-pointer hover:campaignbay-text-primary"
            onClick={() => setView("root")}
          >
            <Icon icon={previous} size={12} /> Back
          </div>
          {statusOptions.map((option) => (
            <div
              key={option.value}
              className={`campaignbay-px-3 campaignbay-py-2 campaignbay-rounded-md campaignbay-cursor-pointer campaignbay-text-sm ${
                filters.status.includes(option.value)
                  ? "campaignbay-bg-primary/5 campaignbay-text-primary"
                  : "hover:campaignbay-bg-gray-50 campaignbay-text-gray-700"
              }`}
              onClick={() => addFilter("status", option.value)}
            >
              {option.label}
            </div>
          ))}
        </div>
      );
    }

    if (view === "types") {
      return (
        <div className="campaignbay-p-2 campaignbay-w-[200px]">
          <div
            className="campaignbay-flex campaignbay-items-center campaignbay-gap-2 campaignbay-px-3 campaignbay-py-2 campaignbay-mb-1 campaignbay-text-xs campaignbay-font-semibold campaignbay-text-gray-500 campaignbay-uppercase campaignbay-cursor-pointer hover:campaignbay-text-primary"
            onClick={() => setView("root")}
          >
            <Icon icon={previous} size={12} /> Back
          </div>
          {typeOptions.map((option) => (
            <div
              key={option.value}
              className={`campaignbay-px-3 campaignbay-py-2 campaignbay-rounded-md campaignbay-cursor-pointer campaignbay-text-sm ${
                filters.types.includes(option.value)
                  ? "campaignbay-bg-primary/5 campaignbay-text-primary"
                  : "hover:campaignbay-bg-gray-50 campaignbay-text-gray-700"
              }`}
              onClick={() => addFilter("types", option.value)}
            >
              {option.label}
            </div>
          ))}
        </div>
      );
    }

    return (
      <div className="campaignbay-p-2 campaignbay-w-[200px]">
        <div className="campaignbay-px-3 campaignbay-py-2 campaignbay-text-xs campaignbay-font-semibold campaignbay-text-gray-400 campaignbay-uppercase">
          Filter by
        </div>
        <div
          className="campaignbay-flex campaignbay-items-center campaignbay-justify-between campaignbay-px-3 campaignbay-py-2 hover:campaignbay-bg-gray-50 campaignbay-cursor-pointer campaignbay-text-sm campaignbay-text-gray-700 campaignbay-rounded-md"
          onClick={() => setView("status")}
        >
          Status
          <Icon icon={next} size={16} className="campaignbay-text-gray-400" />
        </div>
        <div
          className="campaignbay-flex campaignbay-items-center campaignbay-justify-between campaignbay-px-3 campaignbay-py-2 hover:campaignbay-bg-gray-50 campaignbay-cursor-pointer campaignbay-text-sm campaignbay-text-gray-700 campaignbay-rounded-md"
          onClick={() => setView("types")}
        >
          Type
          <Icon icon={next} size={16} className="campaignbay-text-gray-400" />
        </div>
      </div>
    );
  };

  return (
    <div className="campaignbay-w-full campaignbay-flex campaignbay-flex-wrap campaignbay-items-center campaignbay-gap-[8px]">
      {/* Active Filters: Status */}
      {filters.status.map((status) => (
        <span
          key={`status-${status}`}
          className="campaignbay-inline-flex campaignbay-items-center campaignbay-gap-[6px] campaignbay-bg-blue-50 campaignbay-text-primary campaignbay-px-[12px] campaignbay-py-[6px] campaignbay-rounded-full campaignbay-text-[13px] campaignbay-font-medium campaignbay-transition-colors group has-[button:hover]:!campaignbay-bg-red-100 has-[button:hover]:!campaignbay-text-red-700"
        >
          Status is{" "}
          {statusOptions.find((o) => o.value === status)?.label || status}
          <button
            onClick={() => removeFilter("status", status)}
            className="campaignbay-flex campaignbay-items-center campaignbay-justify-center campaignbay-w-[16px] campaignbay-h-[16px] campaignbay-rounded-full  campaignbay-transition-colors"
          >
            <Icon icon={closeSmall} size={16} fill="currentColor" />
          </button>
        </span>
      ))}

      {/* Active Filters: Type */}
      {filters.types.map((type) => (
        <span
          key={`type-${type}`}
          className="campaignbay-inline-flex campaignbay-items-center campaignbay-gap-[6px] campaignbay-bg-blue-50 campaignbay-text-primary campaignbay-px-[12px] campaignbay-py-[6px] campaignbay-rounded-full campaignbay-text-[13px] campaignbay-font-medium campaignbay-transition-colors group has-[button:hover]:!campaignbay-bg-red-100 has-[button:hover]:!campaignbay-text-red-700"
        >
          Type is {typeOptions.find((o) => o.value === type)?.label || type}
          <button
            onClick={() => removeFilter("types", type)}
            className="campaignbay-flex campaignbay-items-center campaignbay-justify-center campaignbay-w-[16px] campaignbay-h-[16px] campaignbay-rounded-full  campaignbay-transition-colors"
          >
            <Icon icon={closeSmall} size={16} fill="currentColor" />
          </button>
        </span>
      ))}

      {/* Add Filter */}
      <Popover
        classNames={{
          content: "campaignbay-w-[200px]",
        }}
        trigger={
          <Button
            variant="ghost"
            size="small"
            className="campaignbay-text-[13px] campaignbay-font-medium campaignbay-px-[8px] campaignbay-py-[4px] campaignbay-rounded-full campaignbay-transition-colors hover:campaignbay-bg-blue-100 hover:campaignbay-text-blue-700 disabled:hover:campaignbay-bg-white disabled:hover:campaignbay-text-primary"
          >
            Add filter
          </Button>
        }
        content={<AddFilterMenu />}
      />

      {/* Reset Text */}

      <Button
        onClick={resetFilters}
        variant="ghost"
        size="small"
        disabled={!hasActiveFilters}
        className="campaignbay-text-[13px] campaignbay-font-medium campaignbay-px-[8px] campaignbay-py-[4px] campaignbay-rounded-full campaignbay-transition-colors hover:campaignbay-bg-blue-100 hover:campaignbay-text-blue-700 disabled:hover:campaignbay-bg-white disabled:hover:campaignbay-text-primary"
      >
        Reset
      </Button>
    </div>
  );
};

interface TableProps {
  campaigns: Campaign[];
  visibleColumns: string[];
  selectedCampaigns: number[];
  setSelectedCampaigns: Dispatch<SetStateAction<number[]>>;
  filters: Filters;
  setFilters: Dispatch<SetStateAction<Filters>>;
  handleSelectAll: (checked: boolean) => void;
  handleDuplicate: (id: number) => void;
  handleDelete: (ids: number[]) => void;
}
const Table = ({
  campaigns,
  visibleColumns,
  selectedCampaigns,
  setSelectedCampaigns,
  filters,
  setFilters,
  handleSelectAll,
  handleDuplicate,
  handleDelete,
}: TableProps) => {
  const handleSortChange = (header: string) => {
    if (filters.sort === header) {
      setFilters({
        ...filters,
        order: filters.order === "asc" ? "desc" : "asc",
      });
    } else {
      setFilters({
        ...filters,
        sort: header,
        order: "asc",
      });
    }
  };
  const renderHeaderRow = () => {
    return visibleColumns.map((header) => {
      const head: TableHeader | undefined = headers.find(
        (h: TableHeader) => h.key === header,
      );
      return (
        <th
          key={header}
          className="campaignbay-text-left campaignbay-font-[11px] campaignbay-leading-[16px] campaignbay-px-[8px] campaignbay-py-[12px] campaignbay-text-[#1e1e1e] campaignbay-uppercase campaignbay-border-b-[1px] campaignbay-border-[#e0e0e0]"
        >
          {head?.sortable ? (
            <button
              className="campaignbay-flex campaignbay-items-center campaignbay-justify-start campaignbay-w-full campaignbay-p-0 campaignbay-m-0 campaignbay-uppercase "
              onClick={() => handleSortChange(header)}
            >
              <span>{head?.label}</span>
              {filters.sort === header ? (
                filters.order === "asc" ? (
                  <Icon icon={arrowUp} size={14} fill="currentColor" />
                ) : (
                  <Icon icon={arrowDown} size={14} fill="currentColor" />
                )
              ) : null}
            </button>
          ) : (
            head?.label
          )}
        </th>
      );
    });
  };

  const handleCheckboxChange = (checked: boolean, id: number) => {
    if (checked) {
      setSelectedCampaigns([...selectedCampaigns, id]);
    } else {
      setSelectedCampaigns(
        selectedCampaigns.filter((campaignId) => campaignId !== id),
      );
    }
  };

  const renderRow = (campaign: Campaign) => {
    return (
      <tr className="">
        <td className="campaignbay-pl-[40px] campaignbay-border-b-[1px] campaignbay-border-[#e0e0e0] campaignbay-w-[42px]">
          <Checkbox
            checked={selectedCampaigns.includes(campaign.id)}
            onChange={(checked) => handleCheckboxChange(checked, campaign.id)}
          />
        </td>
        {visibleColumns.map((header) => {
          return renderColumn(campaign, header);
        })}
        <td className="campaignbay-text-right campaignbay-font-[11px] campaignbay-leading-[16px] campaignbay-px-[8px] campaignbay-py-[12px] campaignbay-pr-[32px] campaignbay-text-[#1e1e1e] campaignbay-border-b-[1px] campaignbay-border-[#e0e0e0] campaignbay-w-[180px]">
          <ActionMenu
            id={campaign.id}
            handleDuplicate={handleDuplicate}
            handleDelete={handleDelete}
          />
        </td>
      </tr>
    );
  };

  const renderColumn = (campaign: Campaign, header: string) => {
    switch (header) {
      case "title":
        return (
          <td className="campaignbay-text-left campaignbay-font-[11px] campaignbay-leading-[16px] campaignbay-px-[8px] campaignbay-py-[12px] campaignbay-text-[#1e1e1e] campaignbay-border-b-[1px] campaignbay-border-[#e0e0e0] ">
            <a
              href={`#/campaigns/${campaign.id}`}
              className="campaignbay-font-[11px] campaignbay-leading-[16px]  !campaignbay-text-[#3858e9]  campaignbay-cursor-pointer campaignbay-capitalize hover:campaignbay-underline campaignbay-underline-offset-4 hover:!campaignbay-text-[#3858ff] focus:campaignbay-shadow-none focus:campaignbay-underline"
            >
              {campaign.title}
            </a>
          </td>
        );
      case "status":
        return (
          <td className="campaignbay-text-left campaignbay-font-[11px] campaignbay-leading-[16px] campaignbay-px-[8px] campaignbay-py-[12px] campaignbay-text-[#1e1e1e] campaignbay-border-b-[1px] campaignbay-border-[#e0e0e0] campaignbay-whitespace-nowrap">
            <StatusBadge status={campaign.status} />
          </td>
        );
      case "type":
        return (
          <td className="campaignbay-text-left campaignbay-font-[11px] campaignbay-leading-[16px] campaignbay-px-[8px] campaignbay-py-[12px] campaignbay-text-[#1e1e1e] campaignbay-border-b-[1px] campaignbay-border-[#e0e0e0] campaignbay-whitespace-nowrap">
            {getCampaignTypeText(campaign.type)}
          </td>
        );
      case "target":
        return (
          <td className="campaignbay-text-left campaignbay-font-[11px] campaignbay-leading-[16px] campaignbay-px-[8px] campaignbay-py-[12px] campaignbay-text-[#757575] campaignbay-border-b-[1px] campaignbay-border-[#e0e0e0] campaignbay-whitespace-nowrap">
            {getTargetType(campaign.target_type)}
          </td>
        );
      case "duration":
        return (
          <td className="campaignbay-text-left campaignbay-font-[11px] campaignbay-leading-[16px] campaignbay-px-[8px] campaignbay-py-[12px] campaignbay-text-[#757575] campaignbay-border-b-[1px] campaignbay-border-[#e0e0e0] campaignbay-whitespace-nowrap">
            {campaign.schedule_enabled
              ? `${formatDate(
                  campaign?.start_datetime_unix ?? campaign.date_created_unix,
                )} - ${formatDate(campaign?.end_datetime_unix)}`
              : "Always Active"}
          </td>
        );
      case "usage":
        return (
          <td className="campaignbay-text-left campaignbay-font-[11px] campaignbay-leading-[16px] campaignbay-px-[8px] campaignbay-py-[12px] campaignbay-text-[#757575] campaignbay-border-b-[1px] campaignbay-border-[#e0e0e0] campaignbay-whitespace-nowrap">
            {campaign.usage_count || 0} / {campaign.usage_limit ?? "∞"}
          </td>
        );
      case "last_modified":
        return (
          <td className="campaignbay-text-left campaignbay-font-[11px] campaignbay-leading-[16px] campaignbay-px-[8px] campaignbay-py-[12px] campaignbay-text-[#757575] campaignbay-border-b-[1px] campaignbay-border-[#e0e0e0] campaignbay-whitespace-nowrap">
            {timeDiff(campaign.date_modified_unix)}
          </td>
        );
      default:
        return (
          <td className="campaignbay-text-left campaignbay-font-[11px] campaignbay-leading-[16px] campaignbay-px-[8px] campaignbay-py-[12px] campaignbay-text-[#1e1e1e] campaignbay-border-b-[1px] campaignbay-border-[#e0e0e0] campaignbay-whitespace-nowrap">
            {" "}
            --{" "}
          </td>
        );
    }
  };

  return (
    <div className="campaignbay-w-full campaignbay-overflow-x-auto">
      <table className="campaignbay-w-full">
        <thead>
          <tr>
            <th className="campaignbay-text-left campaignbay-font-[11px] campaignbay-leading-[16px] campaignbay-px-[8px] campaignbay-py-[12px] campaignbay-pl-[40px]  campaignbay-text-[#1e1e1e] campaignbay-uppercase campaignbay-border-b-[1px] campaignbay-border-[#e0e0e0]">
              <Checkbox
                checked={
                  selectedCampaigns.length === campaigns.length &&
                  selectedCampaigns.length > 0
                }
                onChange={(checked) => handleSelectAll(checked)}
              />
            </th>
            {renderHeaderRow()}
            <th className="campaignbay-text-right campaignbay-font-[11px] campaignbay-leading-[16px] campaignbay-px-[8px] campaignbay-py-[12px] campaignbay-pr-[32px] campaignbay-text-[#1e1e1e] campaignbay-uppercase campaignbay-border-b-[1px] campaignbay-border-[#e0e0e0]">
              Actions
            </th>
          </tr>
        </thead>
        <tbody>
          {campaigns.length && campaigns.length > 0 ? (
            campaigns.map((campaign) => {
              return renderRow(campaign);
            })
          ) : (
            <tr>
              <td colSpan={visibleColumns.length + 2}>
                <EmptyStateCampaigns />
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
};

const ActionMenu = ({
  id,
  handleDuplicate,
  handleDelete,
}: {
  id: number;
  handleDuplicate: (id: number) => void;
  handleDelete: (ids: number[]) => void;
}) => {
  const navigate = useNavigate();
  const [isOpen, setIsOpen] = useState(false);
  return (
    <div className="campaignbay-flex campaignbay-items-center campaignbay-justify-end campaignbay-gap-[4px] campaignbay-min-w-[141px] campaignbay-group">
      <div
        className={`campaignbay-overflow-hidden campaignbay-transition-all campaignbay-duration-200 ${
          isOpen
            ? "campaignbay-w-[104px]"
            : "campaignbay-w-[0] group-hover:campaignbay-w-[104px]"
        }`}
      >
        <div
          className={`campaignbay-gap-[4px] campaignbay-transition-all campaignbay-duration-200 campaignbay-flex campaignbay-flex-nowrap`}
        >
          <Tooltip content="Edit">
            <Button
              variant="ghost"
              color="secondary"
              size="small"
              className="!campaignbay-p-[4px]"
              onClick={() => navigate(`/campaigns/${id}`)}
            >
              <Icon icon={edit} fill="currentColor" />
            </Button>
          </Tooltip>
          <Tooltip content="Delete">
            <Button
              variant="ghost"
              color="secondary"
              size="small"
              className="!campaignbay-p-[4px]"
              onClick={() => handleDelete([id])}
            >
              <Icon icon={trash} fill="currentColor" />
            </Button>
          </Tooltip>
          <Tooltip content="Duplicate">
            <Button
              variant="ghost"
              color="secondary"
              size="small"
              className="!campaignbay-p-[4px]"
              onClick={() => handleDuplicate(id)}
            >
              <Icon icon={copy} fill="currentColor" />
            </Button>
          </Tooltip>
        </div>
      </div>
      <Button
        variant="ghost"
        size="small"
        className="!campaignbay-p-[4px]"
        onClick={() => setIsOpen(!isOpen)}
      >
        <Icon icon={moreVertical} />
      </Button>
    </div>
  );
};

const getTargetType = (target_type: TargetType) => {
  if (target_type === "product") {
    return "Selected Products";
  }
  if (target_type === "category") {
    return "Selected Categories";
  }
  if (target_type === "tag") {
    return "Selected Tags";
  }
  if (target_type === "entire_store") {
    return "All Products";
  }
  return "--";
};

interface PaginationProps {
  filters: Filters;
  setFilters: Dispatch<SetStateAction<Filters>>;
  totalPages: number;
  totalItems: number;
  selectedCampaigns: number[];
  setSelectedCampaigns: Dispatch<SetStateAction<number[]>>;
  handleSelectAll: (checked: boolean) => void;
  handleDelete: (ids: number[]) => void;
  handleExport: () => void;
}
const Pagination = ({
  totalPages,
  totalItems,
  filters,
  setFilters,
  selectedCampaigns,
  setSelectedCampaigns,
  handleSelectAll,
  handleDelete,
  handleExport,
}: PaginationProps) => {
  return (
    // pagination container
    <div className="campaignbay-flex campaignbay-items-center campaignbay-justify-between campaignbay-px-[40px] campaignbay-py-[12px]">
      {/* left -  */}
      <div className="campaignbay-flex campaignbay-items-center campaignbay-gap-[8px]">
        <Checkbox
          checked={
            selectedCampaigns.length === totalItems &&
            selectedCampaigns.length > 0
          }
          onChange={(checked) => handleSelectAll(checked)}
        />
        <span className="campaignbay-text-[11px] campaignbay-leading-[16px] campaignbay-text-[#1e1e1e] campaignbay-uppercase">
          {selectedCampaigns.length} Item Selected
        </span>
        <div className="campaignbay-flex campaignbay-items-center campaignbay-gap-[4px]">
          <Tooltip
            content="Delete Selected Campaign"
            disabled={selectedCampaigns.length === 0}
          >
            <Button
              variant="ghost"
              size="small"
              className="!campaignbay-p-[4px]"
              disabled={selectedCampaigns.length === 0}
              onClick={() => handleDelete(selectedCampaigns)}
            >
              <Icon icon={trash} />
            </Button>
          </Tooltip>
          <Tooltip
            content="Export Selected Campaign"
            disabled={selectedCampaigns.length === 0}
          >
            <Button
              variant="ghost"
              size="small"
              className="!campaignbay-p-[4px]"
              disabled={selectedCampaigns.length === 0}
              onClick={() => handleExport()}
            >
              <Icon icon={download} />
            </Button>
          </Tooltip>
          <Tooltip
            content="Clear Selection"
            disabled={selectedCampaigns.length === 0}
          >
            <Button
              variant="ghost"
              size="small"
              className="!campaignbay-p-[4px]"
              onClick={() => setSelectedCampaigns([])}
              disabled={selectedCampaigns.length === 0}
            >
              <Icon icon={closeSmall} />
            </Button>
          </Tooltip>
        </div>
      </div>
      {/* right -  */}
      <div className="campaignbay-flex campaignbay-nowrap campaignbay-items-center campaignbay-justify-end campaignbay-gap-[8px]">
        <span className="campaignbay-text-[11px] campaignbay-leading-[16px] campaignbay-text-[#1e1e1e] campaignbay-uppercase">
          page{" "}
        </span>
        <Select
          classNames={{
            container: "!campaignbay-px-0",
          }}
          differentDropdownWidth
          border="campaignbay-border-none"
          options={Array.from({ length: totalPages }, (_, i) => i + 1).map(
            (page) => ({
              value: page,
              label: page.toString(),
            }),
          )}
          value={filters.page}
          onChange={(value) =>
            setFilters({ ...filters, page: parseInt(value as string) })
          }
        />

        <span className="campaignbay-text-[11px] campaignbay-leading-[16px] campaignbay-text-[#1e1e1e] campaignbay-uppercase campaignbay-text-nowrap">
          of {totalPages}
        </span>
        <div className="campaignbay-flex campaignbay-items-center campaignbay-gap-[4px]">
          <Button
            variant="ghost"
            size="small"
            className="!campaignbay-p-[4px]"
            disabled={filters.page === 1}
            onClick={() => setFilters({ ...filters, page: filters.page - 1 })}
          >
            <Icon icon={previous} />
          </Button>
          <Button
            variant="ghost"
            size="small"
            className="!campaignbay-p-[4px]"
            // @ts-ignore
            disabled={filters.page === parseInt(totalPages)}
            onClick={() => setFilters({ ...filters, page: filters.page + 1 })}
          >
            <Icon icon={next} />
          </Button>
        </div>
      </div>
    </div>
  );
};

interface CampaignsGridProps {
  campaigns: Campaign[];
  selectedCampaigns: number[];
  setSelectedCampaigns: Dispatch<SetStateAction<number[]>>;
  handleDelete: (ids: number[]) => void;
  handleDuplicate: (id: number) => void;
}

const CampaignsGrid = ({
  campaigns,
  selectedCampaigns,
  setSelectedCampaigns,
  handleDelete,
  handleDuplicate,
}: CampaignsGridProps) => {
  const handleCheckboxChange = (checked: boolean, id: number) => {
    if (checked) {
      setSelectedCampaigns([...selectedCampaigns, id]);
    } else {
      setSelectedCampaigns(
        selectedCampaigns.filter((campaignId) => campaignId !== id),
      );
    }
  };

  if (campaigns.length > 0) {
    return (
      <div className="campaignbay-grid campaignbay-grid-cols-1 md:campaignbay-grid-cols-2 2xl:campaignbay-grid-cols-5 campaignbay-gap-default ">
        {campaigns.map((campaign) => (
          <CampaignCard
            key={campaign.id}
            campaign={campaign}
            isSelected={selectedCampaigns.includes(campaign.id)}
            onSelect={handleCheckboxChange}
            handleDelete={handleDelete}
            handleDuplicate={handleDuplicate}
          />
        ))}
      </div>
    );
  }
  return (
    <div className="campaignbay-bg-white campaignbay-rounded-[8px]">
      <EmptyStateCampaigns />
    </div>
  );
};

interface CampaignCardProps {
  campaign: Campaign;
  isSelected: boolean;
  onSelect: (checked: boolean, id: number) => void;
  handleDelete: (ids: number[]) => void;
  handleDuplicate: (id: number) => void;
}

const CampaignCard = ({
  campaign,
  isSelected,
  onSelect,
  handleDelete,
  handleDuplicate,
}: CampaignCardProps) => {
  return (
    <div
      className={`campaignbay-border campaignbay-rounded-[8px] campaignbay-p-[20px] campaignbay-flex campaignbay-flex-col campaignbay-transition-all hover:campaignbay-shadow-md ${
        isSelected
          ? "campaignbay-border-blue-200 campaignbay-bg-blue-50/10"
          : "campaignbay-border-[#e0e0e0] campaignbay-bg-white"
      }`}
    >
      {/* Header - Checkbox, Status, Actions */}
      <div className="campaignbay-flex campaignbay-items-center campaignbay-justify-between campaignbay-mb-[12px]">
        <div className="campaignbay-flex campaignbay-items-center campaignbay-gap-[12px]">
          <Checkbox
            checked={isSelected}
            onChange={(checked) => onSelect(checked, campaign.id)}
          />
          <StatusBadge status={campaign.status} />
        </div>
        <ActionMenu
          id={campaign.id}
          handleDuplicate={handleDuplicate}
          handleDelete={handleDelete}
        />
      </div>

      {/* Main Content */}
      <div className="campaignbay-mb-[20px]">
        <h3
          className="campaignbay-text-[16px] campaignbay-font-semibold campaignbay-text-[#1e1e1e] campaignbay-mb-[8px] campaignbay-leading-tight"
          title={campaign.title}
        >
          <a
            href={`#/campaigns/${campaign.id}`}
            className="!campaignbay-text-[#3858e9]  campaignbay-cursor-pointer campaignbay-capitalize hover:campaignbay-underline campaignbay-underline-offset-4 hover:!campaignbay-text-[#3858ff] focus:campaignbay-shadow-none focus:campaignbay-underline"
          >
            {campaign.title}
          </a>
        </h3>
        <p className="campaignbay-text-[13px] campaignbay-text-[#4a4a4a] campaignbay-leading-[20px] campaignbay-line-clamp-2">
          {getCampaignTypeText(campaign.type)} campaign targeting{" "}
          {getTargetType(campaign.target_type).toLowerCase()}.
        </p>
      </div>

      {/* Properties List */}
      <div className="campaignbay-flex campaignbay-flex-col campaignbay-gap-[8px] campaignbay-mt-auto">
        <PropertyRow label="Type" value={getCampaignTypeText(campaign.type)} />
        <PropertyRow
          label="Target"
          value={
            <span
              className="campaignbay-truncate campaignbay-block"
              title={getTargetType(campaign.target_type)}
            >
              {getTargetType(campaign.target_type)}
            </span>
          }
        />
        <PropertyRow
          label="Usage"
          value={`${campaign.usage_count || 0} / ${
            campaign.usage_limit ?? "∞"
          }`}
        />
        <PropertyRow
          label="Duration"
          value={
            campaign.schedule_enabled
              ? `${formatDate(
                  campaign?.start_datetime_unix ?? campaign.date_created_unix,
                )} - ${formatDate(campaign?.end_datetime_unix)}`
              : "Always Active"
          }
        />
        <PropertyRow
          label="Last Updated"
          value={formatDate(campaign.date_modified_unix)}
        />
      </div>
    </div>
  );
};

const PropertyRow = ({
  label,
  value,
}: {
  label: string;
  value: React.ReactNode;
}) => {
  return (
    <div className="campaignbay-grid campaignbay-grid-cols-[100px_1fr] campaignbay-gap-[8px] campaignbay-items-baseline">
      <span className="campaignbay-text-[13px] campaignbay-text-[#9e9e9e] campaignbay-font-medium">
        {label}
      </span>
      <span className="campaignbay-text-[13px] campaignbay-text-[#1e1e1e] campaignbay-font-normal">
        {value}
      </span>
    </div>
  );
};

const StatusBadge: FC<{ status: string }> = ({ status }) => {
  const classes = {
    scheduled: "campaignbay-bg-[#4ab866] campaignbay-text-white",
    active: "campaignbay-bg-[#4ab866] campaignbay-text-white",
    inactive: "campaignbay-bg-gray-300 campaignbay-text-gray-500",
    expired: "campaignbay-bg-gray-300 campaignbay-text-gray-500",
  };

  return (
    <span
      className={`campaignbay-inline-block campaignbay-py-[5px] campaignbay-px-[10px] campaignbay-rounded-full campaignbay-text-[11px] campaignbay-leading-[16px] campaignbay-font-bold campaignbay-uppercase campaignbay-leading-none ${
        classes[status as keyof typeof classes]
      }`}
    >
      {status === "active" || status === "scheduled"
        ? "Active"
        : status === "expired"
        ? "Expired"
        : "Inactive"}
    </span>
  );
};

export const Row = ({
  className = "",
  children,
}: {
  className?: string;
  children: React.ReactNode;
}) => {
  return (
    <div
      className={`campaignbay-flex campaignbay-gap-[8px] campaignbay-justify-between campaignbay-items-center campaignbay-w-full ${className}`}
    >
      {children}
    </div>
  );
};

export const Column = ({
  className = "",
  children,
}: {
  className?: string;
  children: React.ReactNode;
}) => {
  return (
    <div
      className={`campaignbay-flex campaignbay-flex-col campaignbay-gap-[8px] campaignbay-items-center campaignbay-w-full ${className}`}
    >
      {children}
    </div>
  );
};
