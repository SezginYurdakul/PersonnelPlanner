import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { AppLayout } from '../components/layout/AppLayout';
import { Card } from '../components/ui/Card';
import { Button } from '../components/ui/Button';
import { Badge } from '../components/ui/Badge';
import { ShiftPatternFormModal } from '../features/lines/ShiftPatternFormModal';
import { createShiftPattern, fetchShiftPatterns, updateShiftPattern } from '../features/lines/api';
import type { ShiftPattern, ShiftPatternFormValues } from '../types/lines';

export function ShiftPatternsPage() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [editing, setEditing] = useState<ShiftPattern | null>(null);
  const [isCreating, setIsCreating] = useState(false);

  const { data: patterns, isLoading } = useQuery({
    queryKey: ['shift-patterns'],
    queryFn: fetchShiftPatterns,
  });

  const createMutation = useMutation({
    mutationFn: (values: ShiftPatternFormValues) => createShiftPattern(values),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['shift-patterns'] }),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, values }: { id: number; values: ShiftPatternFormValues }) =>
      updateShiftPattern(id, values),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['shift-patterns'] }),
  });

  return (
    <AppLayout>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-slate-900">{t('shift_patterns.title')}</h1>
        <Button onClick={() => setIsCreating(true)}>{t('shift_patterns.new')}</Button>
      </div>

      <Card className="p-0">
        {isLoading ? (
          <p className="p-6 text-sm text-slate-500">{t('common.loading')}</p>
        ) : patterns && patterns.length > 0 ? (
          <table className="w-full text-sm">
            <thead className="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
              <tr>
                <th className="px-6 py-3">{t('shift_patterns.name')}</th>
                <th className="px-6 py-3">{t('shift_patterns.start_time')}</th>
                <th className="px-6 py-3">{t('shift_patterns.end_time')}</th>
                <th className="px-6 py-3">{t('shift_patterns.crosses_midnight')}</th>
                <th className="px-6 py-3">{t('common.active')}</th>
                <th className="px-6 py-3" />
              </tr>
            </thead>
            <tbody>
              {patterns.map((pattern) => (
                <tr key={pattern.id} className="border-b border-slate-100">
                  <td className="px-6 py-3 font-medium text-slate-900">{pattern.name}</td>
                  <td className="px-6 py-3 tabular-nums text-slate-600">{pattern.start_time.slice(0, 5)}</td>
                  <td className="px-6 py-3 tabular-nums text-slate-600">{pattern.end_time.slice(0, 5)}</td>
                  <td className="px-6 py-3">
                    {pattern.crosses_midnight && <Badge tone="neutral">+1</Badge>}
                  </td>
                  <td className="px-6 py-3">
                    <Badge tone={pattern.is_active ? 'success' : 'neutral'}>
                      {pattern.is_active ? t('common.active') : t('common.inactive')}
                    </Badge>
                  </td>
                  <td className="px-6 py-3 text-right">
                    <button
                      type="button"
                      className="text-sm font-medium text-slate-600 hover:text-slate-900"
                      onClick={() => setEditing(pattern)}
                    >
                      {t('common.edit')}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <p className="p-6 text-sm text-slate-500">{t('shift_patterns.empty')}</p>
        )}
      </Card>

      {isCreating && (
        <ShiftPatternFormModal
          shiftPattern={null}
          onClose={() => setIsCreating(false)}
          onSubmit={async (values) => {
            await createMutation.mutateAsync(values);
          }}
        />
      )}

      {editing && (
        <ShiftPatternFormModal
          shiftPattern={editing}
          onClose={() => setEditing(null)}
          onSubmit={async (values) => {
            await updateMutation.mutateAsync({ id: editing.id, values });
          }}
        />
      )}
    </AppLayout>
  );
}
