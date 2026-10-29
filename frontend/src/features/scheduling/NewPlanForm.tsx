import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { Button } from '../../components/ui/Button';
import { Card } from '../../components/ui/Card';
import { createSchedule } from './api';

export function NewPlanForm({ weekStartDate, onCreated }: { weekStartDate: string; onCreated: (id: number) => void }) {
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
