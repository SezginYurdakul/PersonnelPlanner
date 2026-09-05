import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { AppLayout } from '../components/layout/AppLayout';
import { Card } from '../components/ui/Card';
import { Button } from '../components/ui/Button';
import { Badge } from '../components/ui/Badge';
import { AgencyFormModal } from '../features/staff/AgencyFormModal';
import { createAgency, fetchAgencies, updateAgency } from '../features/staff/api';
import type { Agency, AgencyFormValues } from '../types/staff';

export function AgenciesPage() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [editingAgency, setEditingAgency] = useState<Agency | null>(null);
  const [isCreating, setIsCreating] = useState(false);

  const { data: agencies, isLoading } = useQuery({
    queryKey: ['agencies'],
    queryFn: fetchAgencies,
  });

  const createMutation = useMutation({
    mutationFn: (values: AgencyFormValues) => createAgency(values),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['agencies'] }),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, values }: { id: number; values: AgencyFormValues }) => updateAgency(id, values),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['agencies'] }),
  });

  return (
    <AppLayout>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-slate-900">{t('agencies.title')}</h1>
        <Button onClick={() => setIsCreating(true)}>{t('agencies.new')}</Button>
      </div>

      <Card className="p-0">
        {isLoading ? (
          <p className="p-6 text-sm text-slate-500">{t('common.loading')}</p>
        ) : agencies && agencies.length > 0 ? (
          <table className="w-full text-sm">
            <thead className="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
              <tr>
                <th className="px-6 py-3">{t('agencies.name')}</th>
                <th className="px-6 py-3">{t('agencies.code')}</th>
                <th className="px-6 py-3">{t('agencies.contact_email')}</th>
                <th className="px-6 py-3">{t('common.active')}</th>
                <th className="px-6 py-3" />
              </tr>
            </thead>
            <tbody>
              {agencies.map((agency) => (
                <tr key={agency.id} className="border-b border-slate-100">
                  <td className="px-6 py-3 font-medium text-slate-900">{agency.name}</td>
                  <td className="px-6 py-3 text-slate-600">{agency.code}</td>
                  <td className="px-6 py-3 text-slate-600">{agency.contact_email ?? '—'}</td>
                  <td className="px-6 py-3">
                    <Badge tone={agency.is_active ? 'success' : 'neutral'}>
                      {agency.is_active ? t('common.active') : t('common.inactive')}
                    </Badge>
                  </td>
                  <td className="px-6 py-3 text-right">
                    <button
                      type="button"
                      className="text-sm font-medium text-slate-600 hover:text-slate-900"
                      onClick={() => setEditingAgency(agency)}
                    >
                      {t('common.edit')}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <p className="p-6 text-sm text-slate-500">{t('agencies.empty')}</p>
        )}
      </Card>

      {isCreating && (
        <AgencyFormModal
          agency={null}
          onClose={() => setIsCreating(false)}
          onSubmit={async (values) => {
            await createMutation.mutateAsync(values);
          }}
        />
      )}

      {editingAgency && (
        <AgencyFormModal
          agency={editingAgency}
          onClose={() => setEditingAgency(null)}
          onSubmit={async (values) => {
            await updateMutation.mutateAsync({ id: editingAgency.id, values });
          }}
        />
      )}
    </AppLayout>
  );
}
