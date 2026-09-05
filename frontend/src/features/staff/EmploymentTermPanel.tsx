import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Button } from '../../components/ui/Button';
import { updateEmploymentTerm } from './api';
import type { Employee } from '../../types/staff';

export function EmploymentTermPanel({ employee }: { employee: Employee }) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [isEditing, setIsEditing] = useState(false);
  const [maxWeeklyHours, setMaxWeeklyHours] = useState(
    employee.employment_terms?.max_weekly_hours ?? 40,
  );
  const [effectiveFrom, setEffectiveFrom] = useState(
    new Date().toISOString().slice(0, 10),
  );

  const mutation = useMutation({
    mutationFn: () =>
      updateEmploymentTerm(employee.id, {
        max_weekly_hours: maxWeeklyHours,
        effective_from: effectiveFrom,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['employee', employee.id] });
      setIsEditing(false);
    },
  });

  return (
    <div>
      <h2 className="mb-3 text-sm font-semibold uppercase text-slate-500">
        {t('staff.employment_terms')}
      </h2>

      {isEditing ? (
        <div className="flex items-end gap-3">
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">
              {t('staff.max_weekly_hours')}
            </label>
            <input
              type="number"
              className="w-28 rounded border border-slate-300 px-3 py-2 text-sm"
              value={maxWeeklyHours}
              onChange={(e) => setMaxWeeklyHours(Number(e.target.value))}
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">
              {t('staff.effective_from')}
            </label>
            <input
              type="date"
              className="rounded border border-slate-300 px-3 py-2 text-sm"
              value={effectiveFrom}
              onChange={(e) => setEffectiveFrom(e.target.value)}
            />
          </div>
          <Button onClick={() => mutation.mutate()} disabled={mutation.isPending}>
            {t('common.save')}
          </Button>
          <Button variant="secondary" onClick={() => setIsEditing(false)}>
            {t('common.cancel')}
          </Button>
        </div>
      ) : (
        <div className="flex items-center justify-between">
          <p className="text-sm text-slate-700">
            {employee.employment_terms
              ? `${employee.employment_terms.max_weekly_hours}h / week (from ${employee.employment_terms.effective_from})`
              : '—'}
          </p>
          <Button variant="secondary" onClick={() => setIsEditing(true)}>
            {t('common.edit')}
          </Button>
        </div>
      )}
    </div>
  );
}
