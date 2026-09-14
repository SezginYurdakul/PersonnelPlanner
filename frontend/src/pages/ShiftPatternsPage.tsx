import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { AppLayout } from '../components/layout/AppLayout';
import { Card } from '../components/ui/Card';
import { Button } from '../components/ui/Button';
import { Badge } from '../components/ui/Badge';
import { ShiftPatternGroupFormModal } from '../features/lines/ShiftPatternGroupFormModal';
import { createShiftPatternGroup, fetchShiftPatternGroups, updateShiftPatternGroup } from '../features/lines/api';
import type { ShiftPatternGroup, ShiftPatternGroupFormValues, SlotType } from '../types/lines';

const SLOT_TYPES: SlotType[] = ['day', 'afternoon', 'night'];

export function ShiftPatternsPage() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [editing, setEditing] = useState<ShiftPatternGroup | null>(null);
  const [isCreating, setIsCreating] = useState(false);

  const { data: groups, isLoading } = useQuery({
    queryKey: ['shift-pattern-groups'],
    queryFn: fetchShiftPatternGroups,
  });

  const createMutation = useMutation({
    mutationFn: (values: ShiftPatternGroupFormValues) => createShiftPatternGroup(values),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['shift-pattern-groups'] }),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, values }: { id: number; values: ShiftPatternGroupFormValues }) =>
      updateShiftPatternGroup(id, values),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['shift-pattern-groups'] }),
  });

  function slotHours(group: ShiftPatternGroup, slotType: SlotType): string {
    const pattern = group.patterns.find((p) => p.slot_type === slotType);
    if (!pattern) return '—';
    return `${pattern.start_time.slice(0, 5)}–${pattern.end_time.slice(0, 5)}${pattern.crosses_midnight ? ' +1' : ''}`;
  }

  return (
    <AppLayout>
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-xl font-semibold text-slate-900">{t('shift_pattern_groups.title')}</h1>
          <p className="mt-1 text-sm text-slate-500">{t('shift_pattern_groups.subtitle')}</p>
        </div>
        <Button onClick={() => setIsCreating(true)}>{t('shift_pattern_groups.new')}</Button>
      </div>

      <Card className="p-0">
        {isLoading ? (
          <p className="p-6 text-sm text-slate-500">{t('common.loading')}</p>
        ) : groups && groups.length > 0 ? (
          <table className="w-full text-sm">
            <thead className="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
              <tr>
                <th className="px-6 py-3">{t('shift_pattern_groups.name')}</th>
                {SLOT_TYPES.map((slotType) => (
                  <th key={slotType} className="px-6 py-3">
                    {t(`shift_pattern_groups.slot.${slotType}`)}
                  </th>
                ))}
                <th className="px-6 py-3">{t('common.active')}</th>
                <th className="px-6 py-3" />
              </tr>
            </thead>
            <tbody>
              {groups.map((group) => (
                <tr key={group.id} className="border-b border-slate-100">
                  <td className="px-6 py-3 font-medium text-slate-900">{group.name}</td>
                  {SLOT_TYPES.map((slotType) => (
                    <td key={slotType} className="px-6 py-3 tabular-nums text-slate-600">
                      {slotHours(group, slotType)}
                    </td>
                  ))}
                  <td className="px-6 py-3">
                    <Badge tone={group.is_active ? 'success' : 'neutral'}>
                      {group.is_active ? t('common.active') : t('common.inactive')}
                    </Badge>
                  </td>
                  <td className="px-6 py-3 text-right">
                    <button
                      type="button"
                      className="text-sm font-medium text-slate-600 hover:text-slate-900"
                      onClick={() => setEditing(group)}
                    >
                      {t('common.edit')}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <p className="p-6 text-sm text-slate-500">{t('shift_pattern_groups.empty')}</p>
        )}
      </Card>

      {isCreating && (
        <ShiftPatternGroupFormModal
          group={null}
          onClose={() => setIsCreating(false)}
          onSubmit={async (values) => {
            await createMutation.mutateAsync(values);
          }}
        />
      )}

      {editing && (
        <ShiftPatternGroupFormModal
          group={editing}
          onClose={() => setEditing(null)}
          onSubmit={async (values) => {
            await updateMutation.mutateAsync({ id: editing.id, values });
          }}
        />
      )}
    </AppLayout>
  );
}
