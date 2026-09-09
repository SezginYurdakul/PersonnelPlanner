import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Badge } from '../../components/ui/Badge';
import { Card } from '../../components/ui/Card';
import { fetchEmployees } from '../staff/api';
import type { TimeClockEntry, TimeClockEntryFilters, TimeClockEntryFormValues } from '../../types/time-attendance';
import { deleteTimeClockEntry, fetchTimeClockEntries, updateTimeClockEntry } from './api';

const sourceTone: Record<TimeClockEntry['source'], 'neutral' | 'info' | 'warning'> = {
  import_simple: 'info',
  import_detailed: 'info',
  manual: 'warning',
};

function toDateTimeLocal(value: string): string {
  return value.slice(0, 16);
}

interface EditState {
  work_date: string;
  clock_in: string;
  clock_out: string;
  break_minutes: string;
  employee_id: string;
}

export function TimeClockEntryTable() {
  const queryClient = useQueryClient();
  const [filters, setFilters] = useState<TimeClockEntryFilters>({});
  const [editingId, setEditingId] = useState<number | null>(null);
  const [editState, setEditState] = useState<EditState | null>(null);

  const { data: entries, isLoading } = useQuery({
    queryKey: ['time-clock-entries', filters],
    queryFn: () => fetchTimeClockEntries(filters),
  });

  const { data: employees } = useQuery({ queryKey: ['employees', {}], queryFn: () => fetchEmployees() });

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: TimeClockEntryFormValues }) =>
      updateTimeClockEntry(id, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['time-clock-entries'] });
      setEditingId(null);
      setEditState(null);
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteTimeClockEntry(id),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['time-clock-entries'] }),
  });

  function startEdit(entry: TimeClockEntry) {
    setEditingId(entry.id);
    setEditState({
      work_date: entry.work_date,
      clock_in: toDateTimeLocal(entry.clock_in),
      clock_out: toDateTimeLocal(entry.clock_out),
      break_minutes: String(entry.break_minutes),
      employee_id: String(entry.employee.id),
    });
  }

  function saveEdit(id: number) {
    if (!editState) return;
    updateMutation.mutate({
      id,
      payload: {
        employee_id: Number(editState.employee_id),
        work_date: editState.work_date,
        clock_in: editState.clock_in,
        clock_out: editState.clock_out,
        break_minutes: Number(editState.break_minutes),
      },
    });
  }

  return (
    <Card className="p-0">
      <div className="flex flex-wrap items-center gap-3 border-b border-x-border p-4">
        <select
          className="rounded border border-slate-300 px-2 py-1.5 text-sm"
          value={filters.employee_id ?? ''}
          onChange={(e) =>
            setFilters((f) => ({ ...f, employee_id: e.target.value ? Number(e.target.value) : undefined }))
          }
        >
          <option value="">All employees</option>
          {employees?.map((emp) => (
            <option key={emp.id} value={emp.id}>
              {emp.first_name} {emp.last_name}
            </option>
          ))}
        </select>
        <input
          type="date"
          className="rounded border border-slate-300 px-2 py-1.5 text-sm"
          value={filters.date_from ?? ''}
          onChange={(e) => setFilters((f) => ({ ...f, date_from: e.target.value || undefined }))}
        />
        <span className="text-xs text-slate-400">to</span>
        <input
          type="date"
          className="rounded border border-slate-300 px-2 py-1.5 text-sm"
          value={filters.date_to ?? ''}
          onChange={(e) => setFilters((f) => ({ ...f, date_to: e.target.value || undefined }))}
        />
      </div>

      {isLoading ? (
        <p className="p-6 text-sm text-slate-500">Loading…</p>
      ) : entries && entries.length > 0 ? (
        <table className="w-full text-sm">
          <thead className="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
            <tr>
              <th className="px-4 py-3">Employee</th>
              <th className="px-4 py-3">Date</th>
              <th className="px-4 py-3">Clock in</th>
              <th className="px-4 py-3">Clock out</th>
              <th className="px-4 py-3">Break (min)</th>
              <th className="px-4 py-3">Worked (min)</th>
              <th className="px-4 py-3">Source</th>
              <th className="px-4 py-3" />
            </tr>
          </thead>
          <tbody>
            {entries.map((entry) =>
              editingId === entry.id && editState ? (
                <tr key={entry.id} className="border-b border-slate-100 bg-x-warmbg/40">
                  <td className="px-4 py-2">
                    <select
                      className="w-full rounded border border-slate-300 px-2 py-1 text-sm"
                      value={editState.employee_id}
                      onChange={(e) => setEditState((s) => (s ? { ...s, employee_id: e.target.value } : s))}
                    >
                      {employees?.map((emp) => (
                        <option key={emp.id} value={emp.id}>
                          {emp.first_name} {emp.last_name}
                        </option>
                      ))}
                    </select>
                  </td>
                  <td className="px-4 py-2">
                    <input
                      type="date"
                      className="w-full rounded border border-slate-300 px-2 py-1 text-sm"
                      value={editState.work_date}
                      onChange={(e) => setEditState((s) => (s ? { ...s, work_date: e.target.value } : s))}
                    />
                  </td>
                  <td className="px-4 py-2">
                    <input
                      type="datetime-local"
                      className="w-full rounded border border-slate-300 px-2 py-1 text-sm"
                      value={editState.clock_in}
                      onChange={(e) => setEditState((s) => (s ? { ...s, clock_in: e.target.value } : s))}
                    />
                  </td>
                  <td className="px-4 py-2">
                    <input
                      type="datetime-local"
                      className="w-full rounded border border-slate-300 px-2 py-1 text-sm"
                      value={editState.clock_out}
                      onChange={(e) => setEditState((s) => (s ? { ...s, clock_out: e.target.value } : s))}
                    />
                  </td>
                  <td className="px-4 py-2">
                    <input
                      type="number"
                      min={0}
                      className="w-20 rounded border border-slate-300 px-2 py-1 text-sm"
                      value={editState.break_minutes}
                      onChange={(e) => setEditState((s) => (s ? { ...s, break_minutes: e.target.value } : s))}
                    />
                  </td>
                  <td className="px-4 py-2 text-slate-400">—</td>
                  <td className="px-4 py-2">
                    <Badge tone="warning">manual</Badge>
                  </td>
                  <td className="px-4 py-2 text-right">
                    <div className="flex justify-end gap-2">
                      <button
                        type="button"
                        className="text-xs font-semibold text-emerald-700"
                        onClick={() => saveEdit(entry.id)}
                      >
                        Save
                      </button>
                      <button
                        type="button"
                        className="text-xs font-semibold text-slate-500"
                        onClick={() => {
                          setEditingId(null);
                          setEditState(null);
                        }}
                      >
                        Cancel
                      </button>
                    </div>
                  </td>
                </tr>
              ) : (
                <tr key={entry.id} className="border-b border-slate-100">
                  <td className="px-4 py-3 font-medium text-slate-900">
                    {entry.employee.first_name} {entry.employee.last_name}
                  </td>
                  <td className="px-4 py-3 tabular-nums text-slate-600">{entry.work_date}</td>
                  <td className="px-4 py-3 tabular-nums text-slate-600">
                    {new Date(entry.clock_in).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                  </td>
                  <td className="px-4 py-3 tabular-nums text-slate-600">
                    {new Date(entry.clock_out).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                  </td>
                  <td className="px-4 py-3 tabular-nums text-slate-600">{entry.break_minutes}</td>
                  <td className="px-4 py-3 tabular-nums text-slate-600">{entry.worked_minutes}</td>
                  <td className="px-4 py-3">
                    <Badge tone={sourceTone[entry.source]}>{entry.source}</Badge>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <div className="flex justify-end gap-3">
                      <button
                        type="button"
                        className="text-xs font-semibold text-slate-600 hover:text-slate-900"
                        onClick={() => startEdit(entry)}
                      >
                        Edit
                      </button>
                      <button
                        type="button"
                        className="text-xs font-semibold text-rose-600 hover:text-rose-800"
                        onClick={() => deleteMutation.mutate(entry.id)}
                      >
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              ),
            )}
          </tbody>
        </table>
      ) : (
        <p className="p-6 text-sm text-slate-500">No time clock entries yet.</p>
      )}
    </Card>
  );
}
