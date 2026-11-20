import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import { AppLayout } from '../components/layout/AppLayout';
import { Card } from '../components/ui/Card';
import { Button } from '../components/ui/Button';
import { Badge } from '../components/ui/Badge';
import { activateUser, fetchPendingActivationUsers } from '../features/auth/api';
import { EmployeeFormModal } from '../features/staff/EmployeeFormModal';
import {
  activateEmployee,
  createEmployee,
  deactivateEmployee,
  deleteEmployeePermanently,
  fetchEmployees,
  type EmployeeFilters,
} from '../features/staff/api';
import { isAxiosError } from 'axios';
import type { Employee, EmployeeFormValues } from '../types/staff';

function PendingActivationSection() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();

  const { data: pendingUsers } = useQuery({
    queryKey: ['pending-activation-users'],
    queryFn: fetchPendingActivationUsers,
  });

  const activateMutation = useMutation({
    mutationFn: (userId: number) => activateUser(userId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['pending-activation-users'] });
      void queryClient.invalidateQueries({ queryKey: ['employees'] });
    },
  });

  if (!pendingUsers || pendingUsers.length === 0) {
    return null;
  }

  return (
    <Card className="mb-4 p-0">
      <h2 className="border-b border-slate-100 px-6 py-3 text-sm font-semibold text-slate-700">
        {t('staff.pending_activation')}
      </h2>
      <ul>
        {pendingUsers.map((user) => (
          <li key={user.id} className="flex items-center justify-between border-b border-slate-100 px-6 py-3 last:border-0">
            <div>
              <p className="text-sm font-medium text-slate-900">{user.name}</p>
              <p className="text-xs text-slate-500">{user.email}</p>
            </div>
            <Button
              variant="secondary"
              disabled={activateMutation.isPending}
              onClick={() => activateMutation.mutate(user.id)}
            >
              {t('staff.activate_account')}
            </Button>
          </li>
        ))}
      </ul>
    </Card>
  );
}

export function StaffPage() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [isCreating, setIsCreating] = useState(false);
  const [filters, setFilters] = useState<EmployeeFilters>({});

  const { data: employees, isLoading } = useQuery({
    queryKey: ['employees', filters],
    queryFn: () => fetchEmployees(filters),
  });

  const createMutation = useMutation({
    mutationFn: (values: EmployeeFormValues) => createEmployee(values),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['employees'] }),
  });

  const deactivateMutation = useMutation({
    mutationFn: (id: number) => deactivateEmployee(id),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['employees'] }),
  });

  const activateMutation = useMutation({
    mutationFn: (employee: Employee) => activateEmployee(employee),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['employees'] }),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteEmployeePermanently(id),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['employees'] }),
    onError: (error) => {
      const message =
        isAxiosError<{ errors?: Record<string, string[]> }>(error) && error.response?.data.errors
          ? Object.values(error.response.data.errors).flat().join('\n')
          : t('staff.delete_failed');
      alert(message);
    },
  });

  return (
    <AppLayout>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-slate-900">{t('staff.title')}</h1>
        <Button onClick={() => setIsCreating(true)}>{t('staff.new')}</Button>
      </div>

      <PendingActivationSection />

      <div className="mb-4 flex gap-3">
        <select
          className="rounded border border-slate-300 px-3 py-2 text-sm"
          value={filters.employee_type ?? ''}
          onChange={(e) =>
            setFilters((f) => ({ ...f, employee_type: e.target.value || undefined }))
          }
        >
          <option value="">{t('staff.filter.all_types')}</option>
          <option value="vast">{t('staff.employee_type.vast')}</option>
          <option value="uitzendkracht">{t('staff.employee_type.uitzendkracht')}</option>
        </select>

        <select
          className="rounded border border-slate-300 px-3 py-2 text-sm"
          value={filters.has_account === undefined ? '' : String(filters.has_account)}
          onChange={(e) =>
            setFilters((f) => ({
              ...f,
              has_account: e.target.value === '' ? undefined : e.target.value === 'true',
            }))
          }
        >
          <option value="">{t('staff.filter.all')}</option>
          <option value="true">{t('staff.has_account')}</option>
          <option value="false">{t('staff.no_account')}</option>
        </select>

        <select
          className="rounded border border-slate-300 px-3 py-2 text-sm"
          value={filters.is_active === undefined ? '' : String(filters.is_active)}
          onChange={(e) =>
            setFilters((f) => ({
              ...f,
              is_active: e.target.value === '' ? undefined : e.target.value === 'true',
            }))
          }
        >
          <option value="">{t('staff.filter.all')}</option>
          <option value="true">{t('common.active')}</option>
          <option value="false">{t('common.inactive')}</option>
        </select>
      </div>

      <Card className="p-0">
        {isLoading ? (
          <p className="p-6 text-sm text-slate-500">{t('common.loading')}</p>
        ) : employees && employees.length > 0 ? (
          <table className="w-full text-sm">
            <thead className="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
              <tr>
                <th className="px-6 py-3">{t('staff.first_name')}</th>
                <th className="px-6 py-3">{t('staff.employee_type')}</th>
                <th className="px-6 py-3">{t('staff.agency')}</th>
                <th className="px-6 py-3">{t('staff.linked_account')}</th>
                <th className="px-6 py-3">{t('common.active')}</th>
                <th className="px-6 py-3" />
              </tr>
            </thead>
            <tbody>
              {employees.map((employee) => (
                <tr key={employee.id} className="border-b border-slate-100">
                  <td className="px-6 py-3 font-medium text-slate-900">
                    <div className="flex items-center gap-2">
                      <span>
                        {employee.first_name} {employee.last_name}
                      </span>
                      {employee.is_shift_lead && <Badge tone="info">{t('staff.is_shift_lead')}</Badge>}
                    </div>
                  </td>
                  <td className="px-6 py-3 text-slate-600">
                    {t(`staff.employee_type.${employee.employee_type}`)}
                  </td>
                  <td className="px-6 py-3 text-slate-600">{employee.agency?.name ?? '—'}</td>
                  <td className="px-6 py-3">
                    {!employee.has_account ? (
                      <Badge tone="warning">{t('staff.no_account')}</Badge>
                    ) : employee.account_active ? (
                      <Badge tone="success">{t('staff.has_account')}</Badge>
                    ) : (
                      <Badge tone="warning">{t('staff.awaiting_activation')}</Badge>
                    )}
                  </td>
                  <td className="px-6 py-3">
                    <Badge tone={employee.is_active ? 'success' : 'neutral'}>
                      {employee.is_active ? t('common.active') : t('common.inactive')}
                    </Badge>
                  </td>
                  <td className="px-6 py-3 text-right">
                    <Link
                      to={`/staff/${employee.id}`}
                      className="mr-4 text-sm font-medium text-slate-600 hover:text-slate-900"
                    >
                      {t('common.edit')}
                    </Link>
                    {employee.is_active ? (
                      <button
                        type="button"
                        className="mr-4 text-sm font-medium text-red-600 hover:text-red-800"
                        onClick={() => deactivateMutation.mutate(employee.id)}
                      >
                        {t('staff.deactivate')}
                      </button>
                    ) : (
                      <button
                        type="button"
                        className="mr-4 text-sm font-medium text-emerald-600 hover:text-emerald-800"
                        onClick={() => activateMutation.mutate(employee)}
                      >
                        {t('staff.activate')}
                      </button>
                    )}
                    <button
                      type="button"
                      className="text-sm font-medium text-red-800 hover:text-red-950"
                      onClick={() => {
                        if (window.confirm(t('staff.delete_confirm'))) {
                          deleteMutation.mutate(employee.id);
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
          <p className="p-6 text-sm text-slate-500">{t('staff.empty')}</p>
        )}
      </Card>

      {isCreating && (
        <EmployeeFormModal
          employee={null}
          onClose={() => setIsCreating(false)}
          onSubmit={async (values) => {
            await createMutation.mutateAsync(values);
          }}
        />
      )}
    </AppLayout>
  );
}
