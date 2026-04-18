import React, {
  useState,
  useEffect,
  useCallback,
  FC,
  Dispatch,
  SetStateAction,
} from "react";
import { Modal, Spinner } from "@wordpress/components";
import apiFetch from "@wordpress/api-fetch";

// Define the shape of the component's props
interface LogViewerModalProps {
  isLogModalOpen: boolean;
  setIsLogModalOpen: Dispatch<SetStateAction<boolean>>;
  isClearingLogs: boolean;
  handleClearLogs: () => Promise<boolean>;
}

// Define the shape of the API response for log content
interface LogContentResponse {
  log_content: string;
}

const LogViewerModal: FC<LogViewerModalProps> = ({
  isLogModalOpen,
  setIsLogModalOpen,
  isClearingLogs,
  handleClearLogs,
}) => {
  const [availableDates, setAvailableDates] = useState<string[]>([]);
  const [selectedDate, setSelectedDate] = useState<string>("today");
  const [logContent, setLogContent] = useState<string>("");
  const [isLoading, setIsLoading] = useState<boolean>(false);

  const fetchAvailableDates = useCallback(async () => {
    try {
      const dates = await apiFetch<string[]>({
        path: "/campaignbay/v1/logs/list",
      });
      setAvailableDates(dates || []);
    } catch (error) {
      console.error("Error fetching log dates:", error);
      setAvailableDates([]);
    }
  }, []);

  const fetchLogs = useCallback(async () => {
    setIsLoading(true);
    let path = "/campaignbay/v1/logs";
    if (selectedDate !== "today") {
      path = `/campaignbay/v1/logs/${selectedDate}`;
    }
    try {
      const response = await apiFetch<LogContentResponse>({ path });
      setLogContent(
        response.log_content || `No logs found for ${selectedDate}.`,
      );
    } catch (error: unknown) {
      if (error instanceof Error) {
        setLogContent(`Error fetching logs: ${error.message}`);
      } else {
        setLogContent("An unknown error occurred while fetching logs.");
      }
    }
    setIsLoading(false);
  }, [selectedDate]);

  useEffect(() => {
    if (isLogModalOpen) {
      fetchAvailableDates();
    }
  }, [isLogModalOpen, fetchAvailableDates]);

  useEffect(() => {
    if (isLogModalOpen) {
      fetchLogs();
    }
  }, [isLogModalOpen, fetchLogs]);

  const closeModal = () => {
    setIsLogModalOpen(false);
  };

  const onClearLogsClick = async () => {
    const success = await handleClearLogs();
    if (success) {
      setSelectedDate("today");
      // No need to call fetch functions here, useEffects will trigger on state change if needed
      // Or call them explicitly if the logic requires an immediate refetch post-clear.
      fetchAvailableDates();
      fetchLogs();
    }
  };

  const handleDownloadLog = () => {
    const dateStr =
      selectedDate === "today"
        ? new Date().toISOString().slice(0, 10)
        : selectedDate;
    const filename = `campaignbay-log-${dateStr}.log`;
    const blob = new Blob([logContent], { type: "text/plain" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  };

  if (!isLogModalOpen) {
    return null;
  }

  return (
    <Modal
      title="Debug Log Viewer"
      onRequestClose={closeModal}
      className="wpab-cb-log-viewer-modal campaignbay-w-[90vw] md:campaignbay-w-[80vw] lg:campaignbay-w-[70vw] campaignbay-max-w-5xl campaignbay-rounded-none"
    >
      <div className="campaignbay-flex campaignbay-justify-between campaignbay-items-center campaignbay-mb-[4px] campaignbay-pb-[4px] campaignbay-border-b campaignbay-border-gray-200 campaignbay-gap-4">
        <div className="campaignbay-inline-flex campaignbay-justify-between campaignbay-items-center campaignbay-gap-4">
          <label
            htmlFor="log-date-selector"
            className="campaignbay-text-sm campaignbay-font-medium campaignbay-text-gray-700"
          >
            Select Log Date
          </label>
          <select
            id="log-date-selector"
            value={selectedDate}
            onChange={(e: React.ChangeEvent<HTMLSelectElement>) =>
              setSelectedDate(e.target.value)
            }
            className="campaignbay-mt-[1px] campaignbay-block campaignbay-w-full md:campaignbay-w-auto campaignbay-pl-3 campaignbay-pr-10 campaignbay-py-[2px] campaignbay-text-base campaignbay-border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:campaignbay-text-sm campaignbay-rounded-md"
          >
            <option value="today">Today</option>
            {availableDates.map((date) => (
              <option key={date} value={date}>
                {date}
              </option>
            ))}
          </select>
        </div>
        <div className="campaignbay-inline-flex campaignbay-justify-end campaignbay-gap-3">
          <button
            className="campaignbay-text-blue-600 hover:campaignbay-bg-blue-100 campaignbay-px-[12px] campaignbay-py-[6px] campaignbay-rounded-sm campaignbay-border campaignbay-border-blue-600 campaignbay-transition-colors disabled:campaignbay-text-blue-400"
            onClick={fetchLogs}
            disabled={isLoading}
          >
            {isLoading ? "Refreshing..." : "Refresh"}
          </button>
          <button
            className="campaignbay-text-blue-600 hover:campaignbay-bg-blue-100 campaignbay-px-[12px] campaignbay-py-[6px] campaignbay-rounded-sm campaignbay-border campaignbay-border-blue-600 campaignbay-transition-colors disabled:campaignbay-text-blue-400"
            onClick={handleDownloadLog}
            disabled={
              isLoading || !logContent || logContent.startsWith("No logs")
            }
          >
            Download
          </button>
          <button
            className="campaignbay-text-red-600 hover:campaignbay-bg-red-100 campaignbay-px-[12px] campaignbay-py-[6px] campaignbay-rounded-sm campaignbay-border campaignbay-border-red-200 campaignbay-transition-colors disabled:campaignbay-text-red-400"
            onClick={onClearLogsClick}
            disabled={isClearingLogs}
          >
            {isClearingLogs ? "Clearing..." : "Clear Log Files"}
          </button>
        </div>
      </div>
      <pre className="campaignbay-p-[4px] campaignbay-bg-gray-50 campaignbay-border campaignbay-border-gray-200 campaignbay-overflow-auto campaignbay-text-xs campaignbay-h-[50vh] campaignbay-scrollbar">
        {isLoading ? <Spinner /> : <LogContent logContent={logContent} />}
      </pre>
    </Modal>
  );
};

export default LogViewerModal;

const LogContent = ({ logContent }: { logContent: string }) => {
  const logLines = logContent.split("\n");
  let lastLevel = "INFO";

  return (
    <>
      {logLines.map((line, index) => {
        // Detect level in current line
        if (line.includes("[INFO]")) {
          lastLevel = "INFO";
        } else if (line.includes("[ERROR]") || line.toLowerCase().includes("error")) {
          lastLevel = "ERROR";
        } else if (line.includes("[DEBUG]") || line.toLowerCase().includes("debug")) {
          lastLevel = "DEBUG";
        } else if (line.includes("[WARNING]")) {
          lastLevel = "WARNING";
        }

        // Determine color class
        let colorClass = "campaignbay-text-gray-600";
        if (lastLevel === "INFO") {
          colorClass = "campaignbay-text-blue-500";
        } else if (lastLevel === "ERROR") {
          colorClass = "campaignbay-text-red-500";
        } else if (lastLevel === "DEBUG") {
          colorClass = "campaignbay-text-orange-500";
        } else if (lastLevel === "WARNING") {
          colorClass = "campaignbay-text-orange-500";
        }

        return (
          <div className={`campaignbay-block ${colorClass}`} key={index}>
            {line}
          </div>
        );
      })}
    </>
  );
};
