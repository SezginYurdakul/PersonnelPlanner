import { useEffect, useMemo, useState } from 'react';
import dayjs from 'dayjs';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { EmployeeAppLayout } from '../components/layout/EmployeeAppLayout';
import { Card } from '../components/ui/Card';
import { useAuth } from '../features/auth/AuthContext';
import { fetchMySchedule } from '../features/employee-schedule/api';
import { fetchMyCompanySettings } from '../features/company-settings/api';
import { NotificationPermissionPrompt } from '../features/notifications/NotificationPermissionPrompt';
import { DayStatus, isOnLeave, shiftNoticeFor } from '../features/employee-schedule/DayStatus';
import type { EmployeeScheduleAssignment } from '../types/employee-schedule';
import type { SlotType } from '../types/lines';
import { getMonthGridWeeks } from '../lib/week';

const SLOT_STYLES: Record<SlotType, { bg: string; text: string; icon: string }> = {
  day: { bg: 'bg-amber-100', text: 'text-amber-800', icon: 'fa-sun' },
  afternoon: { bg: 'bg-orange-100', text: 'text-orange-800', icon: 'fa-cloud-sun' },
  night: { bg: 'bg-indigo-100', text: 'text-indigo-800', icon: 'fa-moon' },
};

export function EmployeeSchedulePage() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const myEmployeeId = user?.employee?.id;

  const { data: companySettings } = useQuery({
    queryKey: ['my-company-settings'],
    queryFn: fetchMyCompanySettings,
  });

  // The first-of-month anchor for whichever month is currently displayed - navigation moves
  // this by whole months, never individual weeks (ProjectPlan's confirmed month-calendar
  // view for the employee self-service schedule).
  const [monthDate, setMonthDate] = useState<string | null>(null);
  const [selectedDate, setSelectedDate] = useState<string | null>(null);

  useEffect(() => {
    if (companySettings && monthDate === null) {
      setMonthDate(dayjs().startOf('month').format('YYYY-MM-DD'));
    }
  }, [companySettings, monthDate]);

  const weeks = useMemo(
    () => (companySettings && monthDate ? getMonthGridWeeks(companySettings.week_start_day, dayjs(monthDate)) : []),
    [companySettings, monthDate],
  );

  const gridStart = weeks[0];

  const { data: schedule, isLoading } = useQuery({
    queryKey: ['my-schedule', gridStart, weeks.length],
    queryFn: () => fetchMySchedule(gridStart, weeks.length),
    enabled: Boolean(gridStart),
  });

  const dayLabels = useMemo(
    () => (gridStart ? Array.from({ length: 7 }, (_, i) => dayjs(gridStart).add(i, 'day').format('ddd')) : []),
    [gridStart],
  );

  // Every assignment visible to this account for the grid's date range - for a `line` or
  // `company` visibility scope this includes coworkers, not just the logged-in employee, so
  // it must be filtered down to "mine" before it can drive the calendar cells or be treated
  // as the anchor for "who else is on this shift".
  const assignmentsByDate = useMemo(() => {
    const map = new Map<string, EmployeeScheduleAssignment[]>();
    for (const assignment of schedule?.assignments ?? []) {
      const list = map.get(assignment.work_date) ?? [];
      list.push(assignment);
      map.set(assignment.work_date, list);
    }
    return map;
  }, [schedule]);

  const myAssignmentsByDate = useMemo(() => {
    const map = new Map<string, EmployeeScheduleAssignment[]>();
    for (const [date, assignments] of assignmentsByDate) {
      map.set(
        date,
        assignments.filter((a) => a.employee.id === myEmployeeId),
      );
    }
    return map;
  }, [assignmentsByDate, myEmployeeId]);

  const today = dayjs().format('YYYY-MM-DD');

  function changeMonth(offset: number) {
    setMonthDate((current) => (current ? dayjs(current).add(offset, 'month').format('YYYY-MM-DD') : current));
  }

  function dayCellContent(date: string) {
    const dayAssignments = myAssignmentsByDate.get(date) ?? [];
    const onLeave = isOnLeave(schedule?.leave_days ?? [], date);
    const notice = shiftNoticeFor(schedule?.shift_notices ?? [], date);

    if (onLeave) return <i className="fa-solid fa-umbrella-beach text-emerald-600" />;
    if (notice) return <i className="fa-solid fa-bell text-amber-600" />;
    if (dayAssignments.length > 0) {
      const first = dayAssignments[0];
      const style = first.shift_pattern ? SLOT_STYLES[first.shift_pattern.slot_type] : null;
      return (
        <div className="flex flex-col items-center gap-0.5">
          <span
            className={`rounded px-1 py-0.5 text-[9px] font-bold leading-none ${style?.bg ?? 'bg-slate-100'} ${style?.text ?? 'text-slate-600'}`}
          >
            {style && <i className={`fa-solid ${style.icon} mr-0.5`} />}
            {first.line?.code ?? first.starts_at?.slice(0, 5)}
          </span>
          {dayAssignments.length > 1 && (
            <span className="text-[9px] font-semibold text-slate-400">+{dayAssignments.length - 1}</span>
          )}
        </div>
      );
    }
    return null;
  }

  const selectedDayAllAssignments = selectedDate ? (assignmentsByDate.get(selectedDate) ?? []) : [];
  const selectedAssignments = selectedDate ? (myAssignmentsByDate.get(selectedDate) ?? []) : [];
  const selectedOnLeave = selectedDate ? isOnLeave(schedule?.leave_days ?? [], selectedDate) : false;
  const selectedNotice = selectedDate ? shiftNoticeFor(schedule?.shift_notices ?? [], selectedDate) : undefined;

  // For each of my own shifts that day, who else shares the same line + shift, and who is
  // leading that shift (a shift lead's assignment carries a real line_id purely to resolve
  // hours, so it's matched on work_date + shift_pattern only, never on line).
  const shiftGroups = selectedAssignments
    .filter((mine) => mine.shift_pattern)
    .map((mine) => {
      const coworkers = selectedDayAllAssignments.filter(
        (a) =>
          a.employee.id !== myEmployeeId &&
          a.role?.attachment_type !== 'shift_lead' &&
          a.line?.id === mine.line?.id &&
          a.shift_pattern?.id === mine.shift_pattern?.id,
      );
      const leadsById = new Map<number, EmployeeScheduleAssignment>();
      for (const a of selectedDayAllAssignments) {
        if (a.role?.attachment_type === 'shift_lead' && a.shift_pattern?.id === mine.shift_pattern?.id) {
          leadsById.set(a.employee.id, a);
        }
      }
      return { mine, coworkers, leads: Array.from(leadsById.values()) };
    });

  return (
    <EmployeeAppLayout>
      <NotificationPermissionPrompt />

      <div className="mb-3 flex items-center justify-between">
        <button type="button" disabled={!monthDate} onClick={() => changeMonth(-1)} className="rounded-lg p-2 text-x-brown hover:bg-white">
          <i className="fa-solid fa-chevron-left" />
        </button>
        <h1 className="text-sm font-extrabold text-x-brown">{monthDate ? dayjs(monthDate).format('MMMM YYYY') : ''}</h1>
        <button type="button" disabled={!monthDate} onClick={() => changeMonth(1)} className="rounded-lg p-2 text-x-brown hover:bg-white">
          <i className="fa-solid fa-chevron-right" />
        </button>
      </div>

      <div className="mb-3 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-[10px] font-semibold text-slate-500">
        {(Object.keys(SLOT_STYLES) as SlotType[]).map((slot) => (
          <span key={slot} className="inline-flex items-center gap-1">
            <span className={`inline-flex h-3.5 w-3.5 items-center justify-center rounded ${SLOT_STYLES[slot].bg} ${SLOT_STYLES[slot].text}`}>
              <i className={`fa-solid ${SLOT_STYLES[slot].icon} text-[8px]`} />
            </span>
            {t(`shift_pattern_groups.slot.${slot}`)}
          </span>
        ))}
      </div>

      {isLoading ? (
        <p className="text-sm text-slate-500">{t('common.loading')}</p>
      ) : (
        <Card className="p-2">
          <div className="grid grid-cols-7 gap-1">
            {dayLabels.map((label) => (
              <div key={label} className="pb-1 text-center text-[10px] font-bold uppercase text-slate-400">
                {label}
              </div>
            ))}
            {weeks.map((weekStart) =>
              Array.from({ length: 7 }, (_, i) => dayjs(weekStart).add(i, 'day').format('YYYY-MM-DD')).map((date) => {
                const inCurrentMonth = dayjs(date).month() === dayjs(monthDate).month();
                const isToday = date === today;

                return (
                  <button
                    key={date}
                    type="button"
                    onClick={() => setSelectedDate(date)}
                    className={`flex aspect-square flex-col items-center justify-center gap-0.5 rounded-lg border text-xs ${
                      isToday
                        ? 'border-x-gold bg-x-warmbg ring-1 ring-x-gold/40'
                        : inCurrentMonth
                          ? 'border-transparent hover:bg-x-warmbg'
                          : 'border-transparent opacity-30'
                    }`}
                  >
                    <span className={`font-semibold ${inCurrentMonth ? 'text-x-brown' : 'text-slate-400'}`}>
                      {dayjs(date).format('D')}
                    </span>
                    {dayCellContent(date)}
                  </button>
                );
              }),
            )}
          </div>
        </Card>
      )}

      {selectedDate && (
        <div className="fixed inset-0 z-40 flex items-end justify-center bg-black/40 p-4 sm:items-center" onClick={() => setSelectedDate(null)}>
          <div
            className="max-h-[80vh] w-full max-w-sm overflow-y-auto rounded-2xl border border-x-border bg-white p-4 shadow-xl"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="mb-3 flex items-center justify-between">
              <h3 className="text-sm font-extrabold text-x-brown">
                {dayjs(selectedDate).format('dddd D MMMM')}
                {selectedDate === today && (
                  <span className="ml-2 rounded-full bg-x-gold px-2 py-0.5 text-[10px] font-bold text-x-brown">
                    {t('employee_schedule.today')}
                  </span>
                )}
              </h3>
              <button type="button" onClick={() => setSelectedDate(null)} className="text-slate-400 hover:text-slate-600">
                <i className="fa-solid fa-xmark" />
              </button>
            </div>

            <DayStatus assignments={selectedAssignments} onLeave={selectedOnLeave} notice={selectedNotice} />

            {shiftGroups.map((group, index) => (
              <div key={group.mine.id} className={index === 0 ? 'mt-4 border-t border-x-border pt-3' : 'mt-3 border-t border-x-border pt-3'}>
                {group.mine.shift_pattern && (
                  <p className="mb-2 text-[10px] font-bold uppercase tracking-wide text-slate-400">
                    {group.mine.line?.name} · {t(`shift_pattern_groups.slot.${group.mine.shift_pattern.slot_type}`)}
                  </p>
                )}

                <div className="mb-2">
                  <p className="mb-1 text-xs font-bold text-slate-500">{t('employee_schedule.shift_leads')}</p>
                  {group.leads.length > 0 ? (
                    <ul className="space-y-0.5">
                      {group.leads.map((lead) => (
                        <li key={lead.employee.id} className="text-sm text-x-brown">
                          <i className="fa-solid fa-star mr-1.5 text-x-gold-dark" />
                          {lead.employee.first_name} {lead.employee.last_name}
                        </li>
                      ))}
                    </ul>
                  ) : (
                    <p className="text-sm text-slate-400">{t('employee_schedule.no_data')}</p>
                  )}
                </div>

                <div>
                  <p className="mb-1 text-xs font-bold text-slate-500">{t('employee_schedule.also_working')}</p>
                  {group.coworkers.length > 0 ? (
                    <ul className="space-y-0.5">
                      {group.coworkers.map((coworker) => (
                        <li key={coworker.id} className="flex items-center justify-between text-sm text-slate-600">
                          <span>
                            {coworker.employee.first_name} {coworker.employee.last_name}
                          </span>
                          <span className="text-slate-400">{coworker.role?.name}</span>
                        </li>
                      ))}
                    </ul>
                  ) : (
                    <p className="text-sm text-slate-400">{t('employee_schedule.no_data')}</p>
                  )}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </EmployeeAppLayout>
  );
}
