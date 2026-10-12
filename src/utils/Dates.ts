import {
  date,
  getDate,
  humanTimeDiff,
  getSettings as getDateSettings,
} from "@wordpress/date";
import { useCbStore } from "../store/cbStore";

/**
 * Formats a given date-time string according to WordPress settings.
 *
 * @since 1.0.0
 *
 * @param {string|number} dateTimeString - The date-time string or timestamp to format.
 * @returns {string} The formatted date and time, or "—" if invalid.
 */
export default function formatDateTime(dateTimeString: string | number | null | undefined): string {
  const { wpSettings } = useCbStore();
  const { timezone } = getDateSettings();



  if (
    !dateTimeString ||
    new Date(dateTimeString).toString() === "Invalid Date"
  ) {
    return "—";
  }


  const format: string = `${wpSettings.dateFormat} ${wpSettings.timeFormat}`;
  // @ts-ignore
  const dateTime: Date = getDate(typeof dateTimeString === "number" ? dateTimeString * 1000 : dateTimeString);
  return date(format, dateTime, timezone?.offset);
}
/**
 * Formats a given date string according to WordPress settings.
 *
 * @since 1.0.0
 *
 * @param {string|number} dateTimeString - The date-time string or timestamp to format.
 * @returns {string} The formatted date, or "—" if invalid.
 */
export function formatDate(dateTimeString: string | number | null | undefined): string {
  const { wpSettings } = useCbStore();
  const { timezone } = getDateSettings();
  if (
    !dateTimeString ||
    new Date(dateTimeString).toString() === "Invalid Date"
  ) {
    return "—";
  }


  const format: string = `${wpSettings.dateFormat}`;
  // @ts-ignore
  const dateTime: Date = getDate(typeof dateTimeString === "number" ? dateTimeString * 1000 : dateTimeString);
  return date(format, dateTime, timezone?.offset);
}

/**
 * Returns a human-readable difference between the given date-time and the current time.
 * If the difference is more than 10 days, returns the formatted date-time instead.
 *
 * @since 1.0.0
 *
 * @param {string|number} dateTimeString - The date-time string or timestamp to compare.
 * @returns {string} Human-readable time difference or formatted date-time, or "—" if invalid.
 */
export function timeDiff(dateTimeString: string | number | null | undefined): string {
  if (
    !dateTimeString ||
    new Date(dateTimeString).toString() === "Invalid Date"
  ) {
    return "—";
  }

  const { timezone } = getDateSettings();
  // @ts-ignore
  const dateTime = getDate(typeof dateTimeString === "number" ? dateTimeString * 1000 : dateTimeString);
  const currentTime = new Date();
  const diffDays = Math.abs(
    (currentTime.getTime() - dateTime.getTime()) / (1000 * 60 * 60 * 24)
  ).toFixed(2);
  // console.table({ dateTimeString, diffDays, currentTime: currentTime, ct: currentTime.getTime(), dateTime: dateTime, dt: dateTime.getTime() });
  // @ts-ignore
  if (diffDays > 10) {
    return formatDateTime(dateTimeString);
  }
  return humanTimeDiff(dateTime, currentTime.getTime());
}


export function currentDateTime() {
  const { timezone } = getDateSettings();
  const { wpSettings } = useCbStore();
  const localTime = new Date(new Date().setDate(new Date().getDate()));
  const format = `${wpSettings?.dateFormat} ${wpSettings?.timeFormat}`;
  // @ts-ignore
  const formatedDate = date(format, localTime, timezone?.offset);
  // @ts-ignore
  return new Date(formatedDate);
};