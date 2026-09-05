import { useParams, Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useState } from 'react';
import { AppLayout } from '../components/layout/AppLayout';
import { Card } from '../components/ui/Card';
import { Button } from '../components/ui/Button';
import { LinkAccountPanel } from '../features/staff/LinkAccountPanel';
import { EmploymentTermPanel } from '../features/staff/EmploymentTermPanel';
import { EmployeeFormModal } from '../features/staff/EmployeeFormModal';
import { fetchEmployee, updateEmployee } from '../features/staff/api';
import type { EmployeeFormValues } from '../types/staff';

export function EmployeeDetailPage() {
  const { t } = useTranslation();
  const { id } = useParams<{ id: string }>();
  const employeeId = Number(id);
  const queryClient = useQueryClient();
  const [isEditing, setIsEditing] = useState(false);

  const { data: employee, isLoading } = useQuery({
    queryKey: ['employee', employeeId],
    queryFn: () => fetchEmployee(employeeId),
  });

  const updateMutation = useMutation({
    mutationFn: (values: EmployeeFormValues) => updateEmployee(employeeId, values),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['employee', employeeId] }),
  });

  if (isLoading || !employee) {
    return (
      <AppLayout>
        <p className="text-sm text-slate-500">{t('common.loading')}</p>
      </AppLayout>
    );
  }

  return (
    <AppLayout>
      <Link to="/staff" className="mb-4 inline-block text-sm text-slate-500 hover:text-slate-700">
        &larr; {t('staff.title')}
      </Link>

      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-slate-900">
          {employee.first_name} {employee.last_name}
        </h1>
        <Button variant="secondary" onClick={() => setIsEditing(true)}>
          {t('common.edit')}
        </Button>
      </div>

      <div className="grid gap-6">
        <Card>
          <h2 className="mb-3 text-sm font-semibold uppercase text-slate-500">
            {t('staff.title')}
          </h2>
          <dl className="grid grid-cols-2 gap-3 text-sm">
            <div>
              <dt className="text-slate-500">{t('staff.phone')}</dt>
              <dd className="text-slate-900">{employee.phone ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-slate-500">{t('staff.email')}</dt>
              <dd className="text-slate-900">{employee.email ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-slate-500">{t('staff.employee_type')}</dt>
              <dd className="text-slate-900">{t(`staff.employee_type.${employee.employee_type}`)}</dd>
            </div>
            <div>
              <dt className="text-slate-500">{t('staff.agency')}</dt>
              <dd className="text-slate-900">{employee.agency?.name ?? '—'}</dd>
            </div>
          </dl>
        </Card>

        <Card>
          <LinkAccountPanel employee={employee} />
        </Card>

        <Card>
          <EmploymentTermPanel employee={employee} />
        </Card>
      </div>

      {isEditing && (
        <EmployeeFormModal
          employee={employee}
          onClose={() => setIsEditing(false)}
          onSubmit={async (values) => {
            await updateMutation.mutateAsync(values);
          }}
        />
      )}
    </AppLayout>
  );
}
