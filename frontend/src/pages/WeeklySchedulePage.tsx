import { useState } from 'react';
import dayjs from 'dayjs';
import { AppLayout } from '../components/layout/AppLayout';
import { Button } from '../components/ui/Button';
import { WeeklyScheduleGrid } from '../features/scheduling/WeeklyScheduleGrid';

type WeekStartDay = 'monday' | 'sunday';

/**
 * The most recent occurrence of the chosen week-start day, on or before today - e.g. with
 * `sunday` chosen on a Wednesday, this returns the Sunday that started the current week
 * (not the upcoming one).
 */
function currentWeekStart(weekStartDay: WeekStartDay): string {
  const today = dayjs();

  if (weekStartDay === 'sunday') {
    return today.subtract(today.day(), 'day').format('YYYY-MM-DD');
  }

  const isoDay = today.day() === 0 ? 7 : today.day();
  return today.subtract(isoDay - 1, 'day').format('YYYY-MM-DD');
}

export function WeeklySchedulePage() {
  const [weekStartDay, setWeekStartDay] = useState<WeekStartDay>('monday');
  const [weekStartDate, setWeekStartDate] = useState(() => currentWeekStart('monday'));

  function handleWeekStartDayChange(next: WeekStartDay) {
    setWeekStartDay(next);
    setWeekStartDate(currentWeekStart(next));
  }

  return (
    <AppLayout>
      <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-xl font-extrabold tracking-tight text-x-brown">Weekly Schedule</h1>
        <div className="flex items-center gap-4">
          <label className="flex items-center gap-2 text-xs font-semibold text-slate-600">
            Week starts on
            <select
              className="rounded border border-slate-300 px-2 py-1.5 text-sm"
              value={weekStartDay}
              onChange={(e) => handleWeekStartDayChange(e.target.value as WeekStartDay)}
            >
              <option value="monday">Monday</option>
              <option value="sunday">Sunday</option>
            </select>
          </label>
          <div className="flex items-center gap-2">
            <Button
              variant="secondary"
              onClick={() => setWeekStartDate(dayjs(weekStartDate).subtract(7, 'day').format('YYYY-MM-DD'))}
            >
              <i className="fa-solid fa-chevron-left" />
            </Button>
            <Button
              variant="secondary"
              onClick={() => setWeekStartDate(dayjs(weekStartDate).add(7, 'day').format('YYYY-MM-DD'))}
            >
              <i className="fa-solid fa-chevron-right" />
            </Button>
          </div>
        </div>
      </div>

      <WeeklyScheduleGrid weekStartDate={weekStartDate} />
    </AppLayout>
  );
}
