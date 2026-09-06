import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { AppLayout } from '../components/layout/AppLayout';
import { Card } from '../components/ui/Card';
import { Button } from '../components/ui/Button';
import { Badge } from '../components/ui/Badge';
import { RoleFormModal } from '../features/lines/RoleFormModal';
import { createRole, fetchRoles, updateRole } from '../features/lines/api';
import type { SchedulingRole, SchedulingRoleFormValues } from '../types/lines';

export function RolesPage() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [editing, setEditing] = useState<SchedulingRole | null>(null);
  const [isCreating, setIsCreating] = useState(false);

  const { data: roles, isLoading } = useQuery({ queryKey: ['roles', {}], queryFn: () => fetchRoles() });

  const createMutation = useMutation({
    mutationFn: (values: SchedulingRoleFormValues) => createRole(values),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['roles'] }),
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, values }: { id: number; values: SchedulingRoleFormValues }) => updateRole(id, values),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['roles'] }),
  });

  return (
    <AppLayout>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-slate-900">{t('roles.title')}</h1>
        <Button onClick={() => setIsCreating(true)}>{t('roles.new')}</Button>
      </div>

      <Card className="p-0">
        {isLoading ? (
          <p className="p-6 text-sm text-slate-500">{t('common.loading')}</p>
        ) : roles && roles.length > 0 ? (
          <table className="w-full text-sm">
            <thead className="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
              <tr>
                <th className="px-6 py-3">{t('roles.name')}</th>
                <th className="px-6 py-3">{t('roles.line')}</th>
                <th className="px-6 py-3">{t('roles.role_kind')}</th>
                <th className="px-6 py-3">{t('common.active')}</th>
                <th className="px-6 py-3" />
              </tr>
            </thead>
            <tbody>
              {roles.map((role) => (
                <tr key={role.id} className="border-b border-slate-100">
                  <td className="px-6 py-3 font-medium text-slate-900">{role.name}</td>
                  <td className="px-6 py-3 text-slate-600">{role.line?.name ?? t('roles.line_independent')}</td>
                  <td className="px-6 py-3">
                    <Badge tone={role.role_kind === 'station' ? 'neutral' : 'warning'}>
                      {t(`roles.role_kind.${role.role_kind}`)}
                    </Badge>
                  </td>
                  <td className="px-6 py-3">
                    <Badge tone={role.is_active ? 'success' : 'neutral'}>
                      {role.is_active ? t('common.active') : t('common.inactive')}
                    </Badge>
                  </td>
                  <td className="px-6 py-3 text-right">
                    <button
                      type="button"
                      className="text-sm font-medium text-slate-600 hover:text-slate-900"
                      onClick={() => setEditing(role)}
                    >
                      {t('common.edit')}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <p className="p-6 text-sm text-slate-500">{t('roles.empty')}</p>
        )}
      </Card>

      {isCreating && (
        <RoleFormModal
          role={null}
          onClose={() => setIsCreating(false)}
          onSubmit={async (values) => {
            await createMutation.mutateAsync(values);
          }}
        />
      )}

      {editing && (
        <RoleFormModal
          role={editing}
          onClose={() => setEditing(null)}
          onSubmit={async (values) => {
            await updateMutation.mutateAsync({ id: editing.id, values });
          }}
        />
      )}
    </AppLayout>
  );
}
