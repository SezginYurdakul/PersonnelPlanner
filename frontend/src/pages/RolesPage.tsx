import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { AppLayout } from '../components/layout/AppLayout';
import { Card } from '../components/ui/Card';
import { Button } from '../components/ui/Button';
import { Badge } from '../components/ui/Badge';
import { isAxiosError } from 'axios';
import { RoleFormModal } from '../features/lines/RoleFormModal';
import {
  createRole,
  deactivateRole,
  deleteRolePermanently,
  fetchRoles,
  updateRole,
} from '../features/lines/api';
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

  const deactivateMutation = useMutation({
    mutationFn: (id: number) => deactivateRole(id),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['roles'] }),
  });

  const activateMutation = useMutation({
    mutationFn: (role: SchedulingRole) =>
      updateRole(role.id, {
        name: role.name,
        line_id: role.line?.id,
        role_kind: role.role_kind,
        requires_coverage: role.requires_coverage,
        attachment_type: role.attachment_type ?? undefined,
        attached_station_role_id: role.attached_station?.id,
        is_active: true,
      }),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['roles'] }),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteRolePermanently(id),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['roles'] }),
    onError: (error) => {
      const message =
        isAxiosError<{ errors?: Record<string, string[]> }>(error) && error.response?.data.errors
          ? Object.values(error.response.data.errors).flat().join('\n')
          : t('roles.delete_failed');
      alert(message);
    },
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
                      className="mr-4 text-sm font-medium text-slate-600 hover:text-slate-900"
                      onClick={() => setEditing(role)}
                    >
                      {t('common.edit')}
                    </button>
                    {role.is_active ? (
                      <button
                        type="button"
                        className="mr-4 text-sm font-medium text-red-600 hover:text-red-800"
                        onClick={() => deactivateMutation.mutate(role.id)}
                      >
                        {t('roles.deactivate')}
                      </button>
                    ) : (
                      <button
                        type="button"
                        className="mr-4 text-sm font-medium text-emerald-600 hover:text-emerald-800"
                        onClick={() => activateMutation.mutate(role)}
                      >
                        {t('roles.activate')}
                      </button>
                    )}
                    <button
                      type="button"
                      className="text-sm font-medium text-red-800 hover:text-red-950"
                      onClick={() => {
                        if (window.confirm(t('roles.delete_confirm'))) {
                          deleteMutation.mutate(role.id);
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
