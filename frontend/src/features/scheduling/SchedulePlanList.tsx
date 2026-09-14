import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Card } from '../../components/ui/Card';
import { ConfirmDialog } from '../../components/ui/ConfirmDialog';
import type { Schedule } from '../../types/scheduling';
import { createSchedule, deleteSchedule, fetchSchedulesForWeek } from './api';

interface SchedulePlanListProps {
  weekStartDate: string;
  onSelect: (scheduleId: number) => void;
}

const statusTone: Record<Schedule['status'], 'neutral' | 'info' | 'success'> = {
  draft: 'neutral',
  proposed: 'info',
  approved: 'success',
};

function NewPlanForm({ weekStartDate, onCreated }: { weekStartDate: string; onCreated: (id: number) => void }) {
  const [label, setLabel] = useState('');
  const [note, setNote] = useState('');

  const createMutation = useMutation({
    mutationFn: () =>
      createSchedule({
        week_start_date: weekStartDate,
        label: label || undefined,
        note: note || undefined,
      }),
    onSuccess: (schedule) => onCreated(schedule.id),
  });

  return (
    <Card>
      <h3 className="mb-3 text-sm font-extrabold text-x-brown">New plan</h3>
      <div className="flex flex-col gap-3">
        <div>
          <label className="mb-1 block text-xs font-semibold text-slate-600">Label</label>
          <input
            type="text"
            placeholder="e.g. Plan A"
            className="w-full rounded border border-slate-300 px-3 py-2 text-sm"
            value={label}
            onChange={(e) => setLabel(e.target.value)}
          />
        </div>
        <div>
          <label className="mb-1 block text-xs font-semibold text-slate-600">Note (optional)</label>
          <textarea
            rows={2}
            placeholder="Why this scenario, what trade-off it makes…"
            className="w-full rounded border border-slate-300 px-3 py-2 text-sm"
            value={note}
            onChange={(e) => setNote(e.target.value)}
          />
        </div>
        <Button
          className="self-start"
          disabled={createMutation.isPending}
          onClick={() => createMutation.mutate()}
        >
          {createMutation.isPending ? 'Creating…' : 'Create plan'}
        </Button>
      </div>
    </Card>
  );
}

export function SchedulePlanList({ weekStartDate, onSelect }: SchedulePlanListProps) {
  const queryClient = useQueryClient();
  const [pendingDelete, setPendingDelete] = useState<Schedule | null>(null);

  const { data: schedules, isLoading } = useQuery({
    queryKey: ['schedules-for-week', weekStartDate],
    queryFn: () => fetchSchedulesForWeek(weekStartDate),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteSchedule(id),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['schedules-for-week', weekStartDate] }),
  });

  return (
    <div className="space-y-6">
      {isLoading ? (
        <p className="text-sm text-slate-500">Loading plans…</p>
      ) : schedules && schedules.length > 0 ? (
        <div className="space-y-3">
          {schedules.map((schedule, index) => (
            <Card key={schedule.id} className="flex items-center justify-between gap-4">
              <button type="button" className="flex-1 text-left" onClick={() => onSelect(schedule.id)}>
                <div className="flex items-center gap-2">
                  <h3 className="text-sm font-extrabold text-x-brown">{schedule.label || `Draft ${index + 1}`}</h3>
                  <Badge tone={statusTone[schedule.status]}>{schedule.status}</Badge>
                </div>
                {schedule.note && <p className="mt-1 text-xs text-slate-500">{schedule.note}</p>}
              </button>
              <div className="flex items-center gap-2">
                <Button variant="secondary" onClick={() => onSelect(schedule.id)}>
                  Open
                </Button>
                {schedule.status !== 'approved' && (
                  <Button variant="danger" onClick={() => setPendingDelete(schedule)}>
                    Delete
                  </Button>
                )}
              </div>
            </Card>
          ))}
        </div>
      ) : (
        <Card>
          <p className="text-sm text-slate-500">No plans exist for this week yet.</p>
        </Card>
      )}

      <NewPlanForm weekStartDate={weekStartDate} onCreated={onSelect} />

      {pendingDelete && (
        <ConfirmDialog
          title="Delete this plan?"
          message={`"${pendingDelete.label || 'This draft'}" and all its assignments will be permanently deleted.`}
          confirmLabel="Delete"
          danger
          onCancel={() => setPendingDelete(null)}
          onConfirm={() => {
            deleteMutation.mutate(pendingDelete.id);
            setPendingDelete(null);
          }}
        />
      )}
    </div>
  );
}
