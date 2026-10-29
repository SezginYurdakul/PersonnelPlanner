import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { AppLayout } from '../components/layout/AppLayout';
import { Card } from '../components/ui/Card';
import { Button } from '../components/ui/Button';
import { Badge } from '../components/ui/Badge';
import { isAxiosError } from 'axios';
import { LineFormModal } from '../features/lines/LineFormModal';
import {
  createLine,
  deactivateLine,
  deleteLinePermanently,
  fetchLines,
  fetchShiftPatternGroups,
  updateLine,
} from '../features/lines/api';
import type { Line, LineFormValues } from '../types/lines';

export function LinesPage() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [editingLine, setEditingLine] = useState<Line | null>(null);
  const [isCreating, setIsCreating] = useState(false);

  const { data: lines, isLoading } = useQuery({ queryKey: ['lines'], queryFn: fetchLines });
  const { data: groups } = useQuery({ queryKey: ['shift-pattern-groups'], queryFn: fetchShiftPatternGroups });

  function groupName(groupId: number | null): string {
    if (groupId === null) return '—';
    return groups?.find((g) => g.id === groupId)?.name ?? '—';
  }

  const createMutation = useMutation({
    mutationFn: (values: LineFormValues) => createLine(values),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['lines'] }),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, values }: { id: number; values: LineFormValues }) => updateLine(id, values),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['lines'] }),
  });

  const deactivateMutation = useMutation({
    mutationFn: (id: number) => deactivateLine(id),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['lines'] }),
  });

  const activateMutation = useMutation({
    mutationFn: (line: Line) =>
      updateLine(line.id, {
        name: line.name,
        code: line.code,
        shift_pattern_group_id: line.shift_pattern_group_id,
        is_active: true,
      }),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['lines'] }),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteLinePermanently(id),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['lines'] }),
    onError: (error) => {
      const message =
        isAxiosError<{ errors?: Record<string, string[]> }>(error) && error.response?.data.errors
          ? Object.values(error.response.data.errors).flat().join('\n')
          : t('lines.delete_failed');
      alert(message);
    },
  });

  return (
    <AppLayout>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-slate-900">{t('lines.title')}</h1>
        <Button onClick={() => setIsCreating(true)}>{t('lines.new')}</Button>
      </div>

      <Card className="p-0">
        {isLoading ? (
          <p className="p-6 text-sm text-slate-500">{t('common.loading')}</p>
        ) : lines && lines.length > 0 ? (
          <table className="w-full text-sm">
            <thead className="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
              <tr>
                <th className="px-6 py-3">{t('lines.name')}</th>
                <th className="px-6 py-3">{t('lines.code')}</th>
                <th className="px-6 py-3">{t('lines.shift_pattern_group')}</th>
                <th className="px-6 py-3">{t('common.active')}</th>
                <th className="px-6 py-3" />
              </tr>
            </thead>
            <tbody>
              {lines.map((line) => (
                <tr key={line.id} className="border-b border-slate-100">
                  <td className="px-6 py-3 font-medium text-slate-900">{line.name}</td>
                  <td className="px-6 py-3 text-slate-600">{line.code}</td>
                  <td className="px-6 py-3 text-slate-600">{groupName(line.shift_pattern_group_id)}</td>
                  <td className="px-6 py-3">
                    <Badge tone={line.is_active ? 'success' : 'neutral'}>
                      {line.is_active ? t('common.active') : t('common.inactive')}
                    </Badge>
                  </td>
                  <td className="px-6 py-3 text-right">
                    <button
                      type="button"
                      className="mr-4 text-sm font-medium text-slate-600 hover:text-slate-900"
                      onClick={() => setEditingLine(line)}
                    >
                      {t('common.edit')}
                    </button>
                    {line.is_active ? (
                      <button
                        type="button"
                        className="mr-4 text-sm font-medium text-red-600 hover:text-red-800"
                        onClick={() => deactivateMutation.mutate(line.id)}
                      >
                        {t('lines.deactivate')}
                      </button>
                    ) : (
                      <button
                        type="button"
                        className="mr-4 text-sm font-medium text-emerald-600 hover:text-emerald-800"
                        onClick={() => activateMutation.mutate(line)}
                      >
                        {t('lines.activate')}
                      </button>
                    )}
                    <button
                      type="button"
                      className="text-sm font-medium text-red-800 hover:text-red-950"
                      onClick={() => {
                        if (window.confirm(t('lines.delete_confirm'))) {
                          deleteMutation.mutate(line.id);
                        }
                      }}
                    >
                      {t('common.delete')}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <p className="p-6 text-sm text-slate-500">{t('lines.empty')}</p>
        )}
      </Card>

      {isCreating && (
        <LineFormModal
          line={null}
          onClose={() => setIsCreating(false)}
          onSubmit={async (values) => {
            await createMutation.mutateAsync(values);
          }}
        />
      )}

      {editingLine && (
        <LineFormModal
          line={editingLine}
          onClose={() => setEditingLine(null)}
          onSubmit={async (values) => {
            await updateMutation.mutateAsync({ id: editingLine.id, values });
          }}
        />
      )}
    </AppLayout>
  );
}
