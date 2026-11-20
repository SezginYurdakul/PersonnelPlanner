import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import dayjs from 'dayjs';
import { useTranslation } from 'react-i18next';
import { Badge } from '../../components/ui/Badge';
import { fetchPlanningCalendar } from './api';
import { fetchCompanySettings } from '../company-settings/api';
import { getWeekStart } from '../../lib/week';
import type { PlanningCalendarSchedule } from '../../types/dashboard';

const statusTone: Record<PlanningCalendarSchedule['status'], 'neutral' | 'info' | 'success'> = {
  draft: 'neutral',
  proposed: 'info',
  approved: 'success',
};

export function PlanningCalendar() {
  const { t } = useTranslation();
  const navigate = useNavigate();

  const { data: companySettings } = useQuery({
    queryKey: ['company-settings'],
    queryFn: fetchCompanySettings,
  });

  const { data: weeks, isLoading } = useQuery({
    queryKey: ['planning-calendar'],
    queryFn: () => fetchPlanningCalendar(2, 6),
  });

  const currentWeekStart = getWeekStart(companySettings?.week_start_day ?? 'monday');

  return (
    <div>
      <h2 className="mb-1 text-sm font-bold uppercase tracking-wide text-slate-500">
        {t('dashboard.planning_calendar.title')}
      </h2>
      <p className="mb-3 text-xs text-slate-400">{t('dashboard.planning_calendar.subtitle')}</p>

      {isLoading ? (
        <p className="text-sm text-slate-500">{t('common.loading')}</p>
      ) : (
        <div className="space-y-1.5">
          {weeks?.map((week) => {
            const isCurrentWeek = week.week_start_date === currentWeekStart;

            return (
              <button
                key={week.week_start_date}
                type="button"
                onClick={() =>
                  navigate('/schedule', {
                    // A week with exactly one plan opens that plan directly; zero or
                    // several plans just lands on that week's plan list (nothing
                    // unambiguous to jump straight into) - either way, the week itself
                    // is always passed through so /schedule never falls back to today's
                    // week regardless of which one was clicked here.
                    state: {
                      weekStartDate: week.week_start_date,
                      scheduleId: week.schedules.length === 1 ? week.schedules[0].id : undefined,
                    },
                  })
                }
                className={`flex w-full items-center justify-between gap-3 rounded-xl border px-3.5 py-2.5 text-left transition-colors hover:border-x-gold ${
                  isCurrentWeek ? 'border-x-gold bg-x-warmbg' : 'border-x-border bg-white'
                }`}
              >
                <span className="flex min-w-[150px] flex-col text-sm font-bold text-x-brown">
                  <span className="flex items-center gap-2">
                    Week {week.iso_week}
                    {isCurrentWeek && (
                      <span className="rounded-full bg-x-gold px-2 py-0.5 text-[10px] font-bold text-x-brown">
                        {t('dashboard.planning_calendar.today')}
                      </span>
                    )}
                  </span>
                  <span className="text-xs font-normal text-slate-400">
                    {dayjs(week.week_start_date).format('D MMM')} - {dayjs(week.week_start_date).add(6, 'day').format('D MMM')}
                  </span>
                </span>

                <span className="flex flex-1 flex-wrap justify-end gap-1.5">
                  {week.schedules.length === 0 ? (
                    <Badge tone="danger">{t('dashboard.planning_calendar.no_plan')}</Badge>
                  ) : (
                    week.schedules.map((schedule, index) => (
                      <Badge key={schedule.id} tone={statusTone[schedule.status]}>
                        {schedule.label ||
                          (schedule.status === 'approved'
                            ? t('dashboard.planning_calendar.approved_plan')
                            : t('dashboard.planning_calendar.untitled_plan', { number: index + 1 }))}
                      </Badge>
                    ))
                  )}
                </span>

                <i className="fa-solid fa-chevron-right text-xs text-slate-300" />
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}
