import { useQuery } from '@tanstack/react-query';
import { fetchScheduleAcknowledgments } from '../schedule-acknowledgments/api';

/**
 * Who has confirmed seeing this schedule's current version (user-confirmed requirement) -
 * shown for any approved schedule, not just corrections (version 1 just shows nobody needed
 * to acknowledge anything yet, which is itself useful confirmation nothing is pending).
 */
export function ScheduleAcknowledgmentPanel({ scheduleId, version }: { scheduleId: number; version: number }) {
  const { data: statuses } = useQuery({
    queryKey: ['schedule-acknowledgments', scheduleId, version],
    queryFn: () => fetchScheduleAcknowledgments(scheduleId),
  });

  if (!statuses || statuses.length === 0) {
    return null;
  }

  const acknowledgedCount = statuses.filter((s) => s.acknowledged).length;
  const notYet = statuses.filter((s) => !s.acknowledged);

  return (
    <div className="rounded-2xl border border-x-border bg-white p-4">
      <h3 className="mb-2 text-sm font-extrabold text-x-brown">
        Acknowledgments (v{version}) — {acknowledgedCount}/{statuses.length} confirmed
      </h3>
      {notYet.length > 0 ? (
        <p className="text-xs text-slate-500">
          Not yet seen by: {notYet.map((s) => `${s.employee.first_name} ${s.employee.last_name}`).join(', ')}
        </p>
      ) : (
        <p className="text-xs text-emerald-700">Everyone has confirmed seeing this version.</p>
      )}
    </div>
  );
}
