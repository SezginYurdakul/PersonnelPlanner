import { useTranslation } from 'react-i18next';
import type {
  EmployeeScheduleAssignment,
  EmployeeScheduleLeaveDay,
  EmployeeScheduleShiftNotice,
} from '../../types/employee-schedule';

export function isOnLeave(leaveDays: EmployeeScheduleLeaveDay[], date: string): boolean {
  return leaveDays.some(
    (leave) => leave.start_date && leave.end_date && leave.start_date <= date && leave.end_date >= date,
  );
}

export function shiftNoticeFor(
  shiftNotices: EmployeeScheduleShiftNotice[],
  date: string,
): EmployeeScheduleShiftNotice | undefined {
  return shiftNotices.find((notice) => notice.work_date === date);
}

/**
 * One day's full status (on leave / reported sick-or-late / shift list / day off) - shared
 * between the month calendar's day-detail popup and the dashboard's "today" card, so the
 * four-way "what does this day look like" logic lives in exactly one place.
 */
export function DayStatus({
  assignments,
  onLeave,
  notice,
}: {
  assignments: EmployeeScheduleAssignment[];
  onLeave: boolean;
  notice: EmployeeScheduleShiftNotice | undefined;
}) {
  const { t } = useTranslation();

  if (onLeave) {
    return (
      <p className="text-sm font-semibold text-emerald-700">
        <i className="fa-solid fa-umbrella-beach mr-1.5" />
        {t('employee_schedule.on_leave')}
      </p>
    );
  }

  if (notice) {
    return (
      <p className="text-sm font-semibold text-amber-700">
        <i className="fa-solid fa-bell mr-1.5" />
        {notice.type === 'sick'
          ? t('employee_schedule.reported_sick')
          : t('employee_schedule.reported_late', { minutes: notice.delay_minutes })}
      </p>
    );
  }

  if (assignments.length > 0) {
    return (
      <div className="space-y-1.5">
        {assignments.map((assignment, index) => (
          <div key={index} className="flex items-center justify-between text-sm">
            <span className="font-semibold text-x-brown">
              {assignment.starts_at?.slice(0, 5)}–{assignment.ends_at?.slice(0, 5)}
              {assignment.crosses_midnight && '+1'}
            </span>
            <span className="text-slate-500">
              {assignment.line?.name}
              {assignment.role?.name ? ` · ${assignment.role.name}` : ''}
            </span>
          </div>
        ))}
      </div>
    );
  }

  return <p className="text-sm text-slate-400">{t('employee_schedule.day_off')}</p>;
}
