import { useEffect, useState } from 'react';
import dayjs from 'dayjs';
import { useQuery } from '@tanstack/react-query';
import { useLocation } from 'react-router-dom';
import { AppLayout } from '../components/layout/AppLayout';
import { Button } from '../components/ui/Button';
import { AllSchedulesList } from '../features/scheduling/AllSchedulesList';
import { NewPlanForm } from '../features/scheduling/NewPlanForm';
import { WeeklyScheduleGrid } from '../features/scheduling/WeeklyScheduleGrid';
import { fetchCompanySettings } from '../features/company-settings/api';
import { getWeekStart, formatWeekLabel } from '../lib/week';

interface NavigationState {
  weekStartDate?: string;
  scheduleId?: number;
}

export function WeeklySchedulePage() {
  const { data: companySettings } = useQuery({
    queryKey: ['company-settings'],
    queryFn: fetchCompanySettings,
  });

  // Set when arriving from the dashboard's planning calendar (clicking a specific week
  // there) - lets this page open directly on that week/plan instead of always defaulting
  // to the current week, which previously happened no matter which week was clicked.
  const location = useLocation();

  const [weekStartDate, setWeekStartDate] = useState<string | null>(null);
  const [selectedScheduleId, setSelectedScheduleId] = useState<number | null>(null);

  // Re-runs on every navigation TO this page, not just the first mount - the sidebar's
  // "Weekly Schedule" link points at this same /schedule route, so clicking it while a
  // plan is already open doesn't remount this component at all (same route = no
  // remount), and without this the page just silently kept showing whatever plan was
  // already open instead of returning to the list. Keyed on location.key (changes on
  // every navigation, even to an identical path) rather than location.state, so a plain
  // nav-link click (no state) still resets back to the list/current week.
  useEffect(() => {
    const state = location.state as NavigationState | null;
    setSelectedScheduleId(state?.scheduleId ?? null);

    if (state?.weekStartDate) {
      setWeekStartDate(state.weekStartDate);
    } else if (companySettings) {
      setWeekStartDate(getWeekStart(companySettings.week_start_day));
    }
    // If companySettings hasn't loaded yet, this effect re-runs once it does (it's a
    // dependency below) and sets today's week then. `location` itself (not just .state)
    // is the dependency, since React Router gives it a new identity on every navigation -
    // including a plain nav-link click back to this same route with no state at all.
  }, [location, companySettings]);

  function changeWeek(nextWeekStartDate: string) {
    setWeekStartDate(nextWeekStartDate);
    setSelectedScheduleId(null);
  }

  return (
    <AppLayout>
      <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-xl font-extrabold tracking-tight text-x-brown">Weekly Schedule</h1>
        {weekStartDate && (
          <div className="flex items-center gap-4">
            <span className="text-sm font-semibold text-slate-600">{formatWeekLabel(weekStartDate)}</span>
            <div className="flex items-center gap-2">
              <Button
                variant="secondary"
                onClick={() => changeWeek(dayjs(weekStartDate).subtract(7, 'day').format('YYYY-MM-DD'))}
              >
                <i className="fa-solid fa-chevron-left" />
              </Button>
              <Button
                variant="secondary"
                onClick={() => changeWeek(dayjs(weekStartDate).add(7, 'day').format('YYYY-MM-DD'))}
              >
                <i className="fa-solid fa-chevron-right" />
              </Button>
            </div>
          </div>
        )}
      </div>

      {weekStartDate &&
        (selectedScheduleId === null ? (
          <>
            <NewPlanForm weekStartDate={weekStartDate} onCreated={setSelectedScheduleId} />
            <AllSchedulesList onSelect={setSelectedScheduleId} />
          </>
        ) : (
          <WeeklyScheduleGrid scheduleId={selectedScheduleId} onBack={() => setSelectedScheduleId(null)} />
        ))}
    </AppLayout>
  );
}
