import type { ReactNode } from 'react';
import dayjs from 'dayjs';
import type { SchedulingRole, SlotType } from '../../types/lines';
import { RoleKindIcon } from './RoleKindIcon';
import { roleCellRuns, roleSlotKey, roleSlotRows } from './roleSlots';

interface GenerateSlotGridProps {
  days: string[];
  rows: (SlotType | null)[];
  isEnabled?: (isoDay: number, slotType: SlotType | null) => boolean;
  isChecked: (isoDay: number, slotType: SlotType | null) => boolean;
  onChange: (isoDay: number, slotType: SlotType | null, checked: boolean) => void;
}

/**
 * The generate form's day x shift checkboxes, for a line or one of its stations and tasks.
 */
export function GenerateSlotGrid({ days, rows, isEnabled, isChecked, onChange }: GenerateSlotGridProps) {
  return (
    <table className="text-xs">
      <thead>
        <tr>
          <th className="w-20" />
          {days.map((d) => (
            <th key={d} className="px-2 py-1 text-center font-semibold text-slate-600">
              {dayjs(d).format('ddd D')}
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {rows.map((slotType) => (
          <tr key={slotType ?? 'flexible'}>
            <td className="pr-2 capitalize text-slate-600">{slotType ?? 'Flexible'}</td>
            {days.map((d) => {
              const isoDay = dayjs(d).isoWeekday();
              return (
                <td key={d} className="px-2 py-1 text-center">
                  <input
                    type="checkbox"
                    disabled={isEnabled ? !isEnabled(isoDay, slotType) : false}
                    checked={isChecked(isoDay, slotType)}
                    onChange={(e) => onChange(isoDay, slotType, e.target.checked)}
                  />
                </td>
              );
            })}
          </tr>
        ))}
      </tbody>
    </table>
  );
}

interface GenerateScopeCardProps {
  title: string;
  // The line-independent roles get their own color, apart from the lines.
  independent?: boolean;
  summary: string;
  children: ReactNode;
}

/**
 * One line (or the line-independent roles) in the generate form: a colored header so each
 * stands apart, then its day x shift grid and its "Stations & tasks".
 */
export function GenerateScopeCard({ title, independent = false, summary, children }: GenerateScopeCardProps) {
  return (
    <div className="overflow-hidden rounded-xl border border-x-border bg-white shadow-sm">
      <div
        className={`flex items-center justify-between gap-3 px-4 py-2 text-white ${
          independent ? 'bg-slate-700' : 'bg-x-brown'
        }`}
      >
        <span className="flex items-center gap-2 text-sm font-extrabold">
          <i className={`fa-solid ${independent ? 'fa-layer-group text-slate-300' : 'fa-industry text-x-gold'}`} />
          {title}
        </span>
        <span className="text-xs font-semibold text-white/70">{summary}</span>
      </div>
      {children}
    </div>
  );
}

interface GenerateRoleShiftsProps {
  days: string[];
  roles: SchedulingRole[];
  // Whether the line (or the line-independent group) runs this shift.
  isRunning: (isoDay: number, slotType: SlotType) => boolean;
  excludedKeys: string[];
  onChange: (roleId: number, isoDay: number, slotType: SlotType | null, included: boolean) => void;
  expanded: boolean;
  onExpandedChange: (expanded: boolean) => void;
}

/**
 * The "Stations & tasks" list that opens under a line's grid: each station and task with its
 * own day x shift checkboxes, ticked wherever the line runs - unticking one leaves just that
 * station or task out on that shift.
 */
export function GenerateRoleShifts({
  days,
  roles,
  isRunning,
  excludedKeys,
  onChange,
  expanded,
  onExpandedChange,
}: GenerateRoleShiftsProps) {
  const isoDays = days.map((d) => dayjs(d).isoWeekday());
  const isEnabled = (isoDay: number, slotType: SlotType | null) => roleCellRuns(isRunning, isoDay, slotType);
  const hasShiftsOff = (role: SchedulingRole) =>
    isoDays.some((isoDay) =>
      roleSlotRows(role).some(
        (slotType) => isEnabled(isoDay, slotType) && excludedKeys.includes(roleSlotKey(role.id, isoDay, slotType)),
      ),
    );
  const changedCount = roles.filter(hasShiftsOff).length;

  if (roles.length === 0) return null;

  return (
    <div className="border-t border-x-border bg-x-warmbg px-4 py-2.5">
      <button
        type="button"
        onClick={() => onExpandedChange(!expanded)}
        aria-expanded={expanded}
        className="flex items-center gap-2 text-xs font-semibold text-slate-700"
      >
        <i className={`fa-solid fa-chevron-right text-[10px] text-slate-400 transition-transform ${expanded ? 'rotate-90' : ''}`} />
        Stations &amp; tasks ({roles.length})
        <span className={changedCount > 0 ? 'font-semibold text-amber-700' : 'font-normal text-slate-400'}>
          {changedCount > 0 ? `· ${changedCount} with shifts left out` : '· on every shift'}
        </span>
      </button>

      {expanded && (
        <div className="mt-2.5 flex flex-wrap gap-3">
          {roles.map((role) => (
            <div key={role.id} className="overflow-x-auto rounded-lg border border-x-border bg-white p-2.5">
              <p className="mb-1 flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                <RoleKindIcon roleKind={role.role_kind} />
                {role.name}
              </p>
              <GenerateSlotGrid
                days={days}
                rows={roleSlotRows(role)}
                isEnabled={isEnabled}
                isChecked={(isoDay, slotType) =>
                  isEnabled(isoDay, slotType) && !excludedKeys.includes(roleSlotKey(role.id, isoDay, slotType))
                }
                onChange={(isoDay, slotType, checked) => onChange(role.id, isoDay, slotType, checked)}
              />
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
