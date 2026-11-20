import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import dayjs from 'dayjs';
import { Card } from '../../components/ui/Card';
import { fetchEmployees } from '../staff/api';
import type { ShiftAssignment } from '../../types/scheduling';
import type { SlotType } from '../../types/lines';

interface WeeklySummaryPanelProps {
  assignments: ShiftAssignment[];
  days: string[];
}

type SummaryType = SlotType | 'flexible';

// Same order as the grid's rows (00-08, 08-16, 16-24), flexible-hours roles last.
const SUMMARY_TYPES: SummaryType[] = ['night', 'day', 'afternoon', 'flexible'];

const TYPE_STYLES: Record<SummaryType, { label: string; badge: string; icon: string }> = {
  night: { label: 'Night', badge: 'bg-indigo-100 text-indigo-800', icon: 'fa-moon' },
  day: { label: 'Day', badge: 'bg-amber-100 text-amber-800', icon: 'fa-sun' },
  afternoon: { label: 'Afternoon', badge: 'bg-orange-100 text-orange-800', icon: 'fa-cloud-sun' },
  flexible: { label: 'Flexible', badge: 'bg-slate-100 text-slate-700', icon: 'fa-clock' },
};

interface SummaryRow {
  employeeId: number;
  firstName: string;
  lastName: string;
  typesByDay: Map<string, Set<SummaryType>>;
  minutesByType: Map<SummaryType, number>;
  totalMinutes: number;
  contract: { min: number | null; max: number } | null;
}

function summaryTypeOf(assignment: ShiftAssignment): SummaryType {
  if (assignment.role?.attachment_type === 'flexible') return 'flexible';
  return assignment.shift_pattern?.slot_type ?? 'flexible';
}

function toMinutes(time: string): number {
  const [hours, minutes] = time.split(':').map(Number);
  return hours * 60 + minutes;
}

// Mirrors the backend's ShiftAssignment::effectiveStart()/effectiveEnd()/
// effectiveCrossesMidnight(), as minutes from the start of the work date.
function rangeOf(assignment: ShiftAssignment): [number, number] | null {
  const start = assignment.starts_at ?? assignment.shift_pattern?.start_time;
  const end = assignment.ends_at ?? assignment.shift_pattern?.end_time;
  if (!start || !end) return null;

  const crossesMidnight =
    assignment.starts_at !== null && assignment.ends_at !== null
      ? assignment.ends_at < assignment.starts_at
      : (assignment.shift_pattern?.crosses_midnight ?? false);

  return [toMinutes(start), toMinutes(end) + (crossesMidnight ? 24 * 60 : 0)];
}

// The union of a day's ranges, like EffectiveWorkedHoursCalculator - a lead's secondary
// tasks ride the same shift as their lead placement, so they add no hours of their own.
function mergedMinutes(ranges: [number, number][]): number {
  const sorted = [...ranges].sort((a, b) => a[0] - b[0]);
  let total = 0;
  let current: [number, number] | null = null;

  for (const [start, end] of sorted) {
    if (current && start <= current[1]) {
      current = [current[0], Math.max(current[1], end)];
      continue;
    }
    if (current) total += current[1] - current[0];
    current = [start, end];
  }

  return current ? total + current[1] - current[0] : total;
}

function formatHours(minutes: number): string {
  return `${Number((minutes / 60).toFixed(1))}h`;
}

/**
 * Collapsible per-person view of the week: which shift type each day, hours per shift type
 * and in total against the contract - closed by default so it doesn't push the grid down.
 */
export function WeeklySummaryPanel({ assignments, days }: WeeklySummaryPanelProps) {
  const [open, setOpen] = useState(false);

  // Only needed for the contract column and for listing active staff with no hours yet,
  // so it isn't fetched until the panel is opened.
  const { data: employees } = useQuery({
    queryKey: ['employees', {}],
    queryFn: () => fetchEmployees(),
    enabled: open,
  });

  const rows = useMemo(() => {
    const byEmployee = new Map<number, SummaryRow>();
    const rowFor = (id: number, firstName: string, lastName: string) => {
      let row = byEmployee.get(id);
      if (!row) {
        row = {
          employeeId: id,
          firstName,
          lastName,
          typesByDay: new Map(),
          minutesByType: new Map(),
          totalMinutes: 0,
          contract: null,
        };
        byEmployee.set(id, row);
      }
      return row;
    };

    const rangesByDay = new Map<string, [number, number][]>();
    const rangesByDayAndType = new Map<string, [number, number][]>();

    for (const assignment of assignments) {
      const row = rowFor(assignment.employee.id, assignment.employee.first_name, assignment.employee.last_name);
      const type = summaryTypeOf(assignment);
      const types = row.typesByDay.get(assignment.work_date) ?? new Set<SummaryType>();
      types.add(type);
      row.typesByDay.set(assignment.work_date, types);

      const range = rangeOf(assignment);
      if (!range) continue;
      const dayKey = `${assignment.employee.id}|${assignment.work_date}`;
      rangesByDay.set(dayKey, [...(rangesByDay.get(dayKey) ?? []), range]);
      const typeKey = `${dayKey}|${type}`;
      rangesByDayAndType.set(typeKey, [...(rangesByDayAndType.get(typeKey) ?? []), range]);
    }

    for (const [key, ranges] of rangesByDay) {
      byEmployee.get(Number(key.split('|')[0]))!.totalMinutes += mergedMinutes(ranges);
    }
    for (const [key, ranges] of rangesByDayAndType) {
      const [employeeId, , type] = key.split('|');
      const row = byEmployee.get(Number(employeeId))!;
      const summaryType = type as SummaryType;
      row.minutesByType.set(summaryType, (row.minutesByType.get(summaryType) ?? 0) + mergedMinutes(ranges));
    }

    for (const employee of employees ?? []) {
      const assigned = byEmployee.has(employee.id);
      if (!assigned && !employee.is_active) continue;
      const row = rowFor(employee.id, employee.first_name, employee.last_name);
      if (employee.employment_terms) {
        row.contract = {
          min: employee.employment_terms.min_weekly_hours,
          max: employee.employment_terms.max_weekly_hours,
        };
      }
    }

    return [...byEmployee.values()].sort(
      (a, b) => a.lastName.localeCompare(b.lastName) || a.firstName.localeCompare(b.firstName),
    );
  }, [assignments, employees]);

  const typesInWeek = SUMMARY_TYPES.filter((type) => rows.some((row) => row.minutesByType.has(type)));
  const weekMinutes = rows.reduce((sum, row) => sum + row.totalMinutes, 0);
  const assignedCount = rows.filter((row) => row.typesByDay.size > 0).length;

  return (
    <Card>
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        aria-expanded={open}
        className="flex w-full items-center justify-between text-left"
      >
        <span>
          <span className="block text-sm font-extrabold text-x-brown">Weekly Summary</span>
          <span className="text-xs text-slate-500">
            {assignedCount} people scheduled · {formatHours(weekMinutes)} in total
          </span>
        </span>
        <i className={`fa-solid fa-chevron-down text-xs text-slate-400 transition-transform ${open ? 'rotate-180' : ''}`} />
      </button>

      {open && (
        <div className="mt-4 overflow-x-auto">
          <table className="w-full border-collapse text-xs">
            <thead>
              <tr className="text-left font-bold uppercase text-slate-500">
                <th className="border-b border-x-border p-2">Employee</th>
                {days.map((day) => (
                  <th key={day} className="border-b border-x-border p-2 text-center">
                    {dayjs(day).format('ddd')}
                  </th>
                ))}
                {typesInWeek.map((type) => (
                  <th key={type} className="border-b border-x-border p-2 text-right">
                    {TYPE_STYLES[type].label}
                  </th>
                ))}
                <th className="border-b border-x-border p-2 text-right">Total</th>
                <th className="border-b border-x-border p-2 text-right">Contract</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => {
                const under = row.contract?.min != null && row.totalMinutes < row.contract.min * 60;
                const over = row.contract != null && row.totalMinutes > row.contract.max * 60;
                return (
                  <tr key={row.employeeId} className="hover:bg-x-warmbg">
                    <td className="whitespace-nowrap border-b border-x-border p-2 font-semibold text-slate-700">
                      {row.firstName} {row.lastName}
                    </td>
                    {days.map((day) => (
                      <td key={day} className="border-b border-x-border p-1 text-center">
                        <div className="flex justify-center gap-1">
                          {SUMMARY_TYPES.filter((type) => row.typesByDay.get(day)?.has(type)).map((type) => (
                            <span
                              key={type}
                              title={TYPE_STYLES[type].label}
                              className={`inline-flex h-6 w-6 items-center justify-center rounded-md ${TYPE_STYLES[type].badge}`}
                            >
                              <i className={`fa-solid ${TYPE_STYLES[type].icon} text-[10px]`} />
                            </span>
                          ))}
                        </div>
                      </td>
                    ))}
                    {typesInWeek.map((type) => (
                      <td key={type} className="border-b border-x-border p-2 text-right tabular-nums text-slate-600">
                        {row.minutesByType.has(type) ? formatHours(row.minutesByType.get(type)!) : '–'}
                      </td>
                    ))}
                    <td
                      className={`border-b border-x-border p-2 text-right font-bold tabular-nums ${
                        over ? 'text-rose-700' : under ? 'text-amber-700' : 'text-x-brown'
                      }`}
                    >
                      {formatHours(row.totalMinutes)}
                    </td>
                    <td className="whitespace-nowrap border-b border-x-border p-2 text-right tabular-nums text-slate-400">
                      {row.contract
                        ? row.contract.min != null && row.contract.min !== row.contract.max
                          ? `${row.contract.min}–${row.contract.max}h`
                          : `${row.contract.max}h`
                        : '–'}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
          <p className="mt-3 text-[11px] text-slate-400">
            Hours count overlapping tasks on the same shift once. Totals below the contract minimum are amber, above the
            maximum red.
          </p>
        </div>
      )}
    </Card>
  );
}
