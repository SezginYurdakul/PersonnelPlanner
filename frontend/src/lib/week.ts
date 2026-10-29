import dayjs, { type Dayjs } from 'dayjs';
import isoWeek from 'dayjs/plugin/isoWeek';
import type { WeekStartDay } from '../types/company-settings';

dayjs.extend(isoWeek);

/**
 * The most recent occurrence of the given week-start day, on or before `from` - e.g. with
 * `sunday` on a Wednesday, this returns the Sunday that started the current week (not the
 * upcoming one). This is the single place week-start math happens; every page must derive
 * its week boundaries through this function using the company-wide setting, never a local
 * per-page choice.
 */
export function getWeekStart(weekStartDay: WeekStartDay, from: Dayjs = dayjs()): string {
  if (weekStartDay === 'sunday') {
    return from.subtract(from.day(), 'day').format('YYYY-MM-DD');
  }

  const isoDay = from.day() === 0 ? 7 : from.day();
  return from.subtract(isoDay - 1, 'day').format('YYYY-MM-DD');
}

/**
 * ISO week number (1-53) for the week that starts on `weekStartDate` - anchored to the
 * Thursday of that week so it stays correct even when the company's week-start day (e.g.
 * Sunday) doesn't line up with ISO 8601's Monday-start convention.
 */
export function isoWeekNumber(weekStartDate: string): number {
  return dayjs(weekStartDate).add(3, 'day').isoWeek();
}

/**
 * "Week 34 (7 Sep - 13 Sep)" style label.
 */
export function formatWeekLabel(weekStartDate: string): string {
  const start = dayjs(weekStartDate);
  const end = start.add(6, 'day');
  return `Week ${isoWeekNumber(weekStartDate)} (${start.format('D MMM')} - ${end.format('D MMM')})`;
}

/**
 * Every week-start-date (one per row) needed to draw a full calendar-month grid for
 * `monthDate`'s month - starting from the week containing the 1st of the month through the
 * week containing the last day, so the grid always shows complete weeks (including the
 * leading/trailing days that spill into the adjacent month), same as any standard calendar.
 */
export function getMonthGridWeeks(weekStartDay: WeekStartDay, monthDate: Dayjs): string[] {
  const gridStart = dayjs(getWeekStart(weekStartDay, monthDate.startOf('month')));
  const gridEnd = dayjs(getWeekStart(weekStartDay, monthDate.endOf('month')));
  const totalWeeks = gridEnd.diff(gridStart, 'day') / 7 + 1;

  return Array.from({ length: totalWeeks }, (_, i) => gridStart.add(i * 7, 'day').format('YYYY-MM-DD'));
}
