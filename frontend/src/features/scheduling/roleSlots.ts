import type { SchedulingRole, SlotType } from '../../types/lines';

// Same top-to-bottom order as the schedule grid's rows.
const SLOT_ROWS: SlotType[] = ['night', 'day', 'afternoon'];

// "roleId|isoDayOfWeek|slotType" - "*" for a flexible-hours role's whole day.
export function roleSlotKey(roleId: number, isoDay: number, slotType: SlotType | null): string {
  return `${roleId}|${isoDay}|${slotType ?? '*'}`;
}

// A flexible-hours role has no slot type, so it gets one row of days.
export function roleSlotRows(role: SchedulingRole): (SlotType | null)[] {
  return role.attachment_type === 'flexible' ? [null] : SLOT_ROWS;
}

// Whether a role's cell runs where its line (or group) runs: a flexible role's day runs
// when any shift of that day does.
export function roleCellRuns(
  runs: (isoDay: number, slotType: SlotType) => boolean,
  isoDay: number,
  slotType: SlotType | null,
): boolean {
  return slotType === null ? SLOT_ROWS.some((s) => runs(isoDay, s)) : runs(isoDay, slotType);
}
