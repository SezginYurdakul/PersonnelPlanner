import { useMemo, useState } from 'react';
import dayjs from 'dayjs';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { EmployeeAppLayout } from '../components/layout/EmployeeAppLayout';
import { fetchMySchedule } from '../features/employee-schedule/api';
import type { EmployeeScheduleAssignment } from '../types/employee-schedule';

function currentMonday(): string {
  const today = dayjs();
  const isoDay = today.day() === 0 ? 7 : today.day();
  return today.subtract(isoDay - 1, 'day').format('YYYY-MM-DD');
}

export function EmployeeSchedulePage() {
  const { t } = useTranslation();
  const [weekStartDate, setWeekStartDate] = useState(currentMonday);

  const { data: schedule, isLoading } = useQuery({
    queryKey: ['my-schedule', weekStartDate],
    queryFn: () => fetchMySchedule(weekStartDate),
  });

  const days = useMemo(
    () => Array.from({ length: 7 }, (_, i) => dayjs(weekStartDate).add(i, 'day').format('YYYY-MM-DD')),
    [weekStartDate],
  );

  const assignmentsByDate = useMemo(() => {
    const map = new Map<string, EmployeeScheduleAssignment[]>();
    for (const assignment of schedule?.assignments ?? []) {
      const list = map.get(assignment.work_date) ?? [];
      list.push(assignment);
      map.set(assignment.work_date, list);
    }
    return map;
  }, [schedule]);

  const today = dayjs().format('YYYY-MM-DD');

  function isOnLeave(date: string): boolean {
    return (schedule?.leave_days ?? []).some((leave) => leave.start_date && leave.end_date && leave.start_date <= date && leave.end_date >= date);
  }

  function shiftNoticeFor(date: string) {
    return (schedule?.shift_notices ?? []).find((notice) => notice.work_date === date);
  }

  return (
    <EmployeeAppLayout>
      <div className="mb-4 flex items-center justify-between">
        <button
          type="button"
          onClick={() => setWeekStartDate(dayjs(weekStartDate).subtract(7, 'day').format('YYYY-MM-DD'))}
          className="rounded-lg p-2 text-x-brown hover:bg-white"
        >
          <i className="fa-solid fa-chevron-left" />
        </button>
        <h1 className="text-sm font-extrabold text-x-brown">
          {t('employee_schedule.week_of', { date: dayjs(weekStartDate).format('D MMM YYYY') })}
        </h1>
        <button
          type="button"
          onClick={() => setWeekStartDate(dayjs(weekStartDate).add(7, 'day').format('YYYY-MM-DD'))}
          className="rounded-lg p-2 text-x-brown hover:bg-white"
        >
          <i className="fa-solid fa-chevron-right" />
        </button>
      </div>

      {isLoading ? (
        <p className="text-sm text-slate-500">{t('common.loading')}</p>
      ) : (
        <div className="space-y-2">
          {days.map((date) => {
            const dayAssignments = assignmentsByDate.get(date) ?? [];
            const onLeave = isOnLeave(date);
            const notice = shiftNoticeFor(date);
            const isToday = date === today;

            return (
              <div
                key={date}
                className={`rounded-xl border bg-white p-3.5 shadow-sm ${
                  isToday ? 'border-x-gold ring-1 ring-x-gold/40' : 'border-x-border'
                }`}
              >
                <div className="mb-1.5 flex items-center justify-between">
                  <span className="text-xs font-bold uppercase tracking-wide text-slate-500">
                    {dayjs(date).format('ddd D MMM')}
                  </span>
                  {isToday && (
                    <span className="rounded-full bg-x-gold px-2 py-0.5 text-[10px] font-bold text-x-brown">
                      {t('employee_schedule.today')}
                    </span>
                  )}
                </div>

                {onLeave ? (
                  <p className="text-sm font-semibold text-emerald-700">
                    <i className="fa-solid fa-umbrella-beach mr-1.5" />
                    {t('employee_schedule.on_leave')}
                  </p>
                ) : notice ? (
                  <p className="text-sm font-semibold text-amber-700">
                    <i className="fa-solid fa-bell mr-1.5" />
                    {notice.type === 'sick'
                      ? t('employee_schedule.reported_sick')
                      : t('employee_schedule.reported_late', { minutes: notice.delay_minutes })}
                  </p>
                ) : dayAssignments.length > 0 ? (
                  <div className="space-y-1.5">
                    {dayAssignments.map((assignment, index) => (
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
                ) : (
                  <p className="text-sm text-slate-400">{t('employee_schedule.day_off')}</p>
                )}
              </div>
            );
          })}
        </div>
      )}
    </EmployeeAppLayout>
  );
}
