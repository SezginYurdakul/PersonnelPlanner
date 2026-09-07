/**
 * The native <input type="time"> displays 12-hour AM/PM in some browser/locale
 * combinations, which makes a value like "12:00" ambiguous (noon vs. midnight) at a
 * glance even though the underlying value is always a correct 24-hour "HH:MM" string.
 * Showing that raw value back explicitly avoids a planner mis-reading which one they picked.
 */
export function TimeInputHint({ time }: { time: string | undefined }) {
  if (!time || !/^\d{2}:\d{2}$/.test(time)) {
    return null;
  }

  return <p className="mt-1 text-xs font-semibold tabular-nums text-slate-400">24h: {time}</p>;
}
