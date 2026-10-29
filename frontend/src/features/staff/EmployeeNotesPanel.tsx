import { useEffect, useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Button } from '../../components/ui/Button';
import { updateEmployeeNotes } from './api';
import type { Employee } from '../../types/staff';

export function EmployeeNotesPanel({ employee }: { employee: Employee }) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [notes, setNotes] = useState(employee.notes ?? '');

  useEffect(() => {
    setNotes(employee.notes ?? '');
  }, [employee.notes]);

  const mutation = useMutation({
    mutationFn: (value: string) => updateEmployeeNotes(employee.id, value || null),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['employee', employee.id] }),
  });

  const isDirty = notes !== (employee.notes ?? '');

  return (
    <div>
      <h2 className="mb-1 text-sm font-semibold uppercase text-slate-500">{t('staff.notes.title')}</h2>
      <p className="mb-3 text-xs text-slate-400">{t('staff.notes.hint')}</p>

      <textarea
        rows={3}
        className="w-full rounded border border-slate-300 px-3 py-2 text-sm"
        value={notes}
        onChange={(e) => setNotes(e.target.value)}
      />

      {isDirty && (
        <Button className="mt-3" onClick={() => mutation.mutate(notes)} disabled={mutation.isPending}>
          {t('common.save')}
        </Button>
      )}
    </div>
  );
}
