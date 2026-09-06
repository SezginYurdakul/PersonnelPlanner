import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { AppLayout } from '../components/layout/AppLayout';
import { Card } from '../components/ui/Card';
import { Button } from '../components/ui/Button';
import { Badge } from '../components/ui/Badge';
import { LeaveRequestFormModal } from '../features/leave/LeaveRequestFormModal';
import {
  approveLeaveRequest,
  createLeaveRequest,
  fetchLeaveRequests,
  fetchLeaveTypes,
  rejectLeaveRequest,
  type LeaveRequestFilters,
} from '../features/leave/api';
import type { LeaveRequestFormValues, LeaveStatus } from '../types/leave';

const statusTone: Record<LeaveStatus, 'warning' | 'success' | 'danger'> = {
  pending: 'warning',
  approved: 'success',
  rejected: 'danger',
};

export function LeaveRequestsPage() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [isCreating, setIsCreating] = useState(false);
  const [filters, setFilters] = useState<LeaveRequestFilters>({});

  const { data: leaveRequests, isLoading } = useQuery({
    queryKey: ['leave-requests', filters],
    queryFn: () => fetchLeaveRequests(filters),
  });

  const { data: leaveTypes } = useQuery({ queryKey: ['leave-types'], queryFn: fetchLeaveTypes });

  const createMutation = useMutation({
    mutationFn: (values: LeaveRequestFormValues) => createLeaveRequest(values),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['leave-requests'] }),
  });

  const approveMutation = useMutation({
    mutationFn: (id: number) => approveLeaveRequest(id),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['leave-requests'] }),
  });

  const rejectMutation = useMutation({
    mutationFn: (id: number) => rejectLeaveRequest(id),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['leave-requests'] }),
  });

  return (
    <AppLayout>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-slate-900">{t('leave.title')}</h1>
        <Button onClick={() => setIsCreating(true)}>{t('leave.new')}</Button>
      </div>

      <div className="mb-4 flex gap-3">
        <select
          className="rounded border border-slate-300 px-3 py-2 text-sm"
          value={filters.status ?? ''}
          onChange={(e) => setFilters((f) => ({ ...f, status: e.target.value || undefined }))}
        >
          <option value="">{t('leave.filter.all_statuses')}</option>
          <option value="pending">{t('leave.status.pending')}</option>
          <option value="approved">{t('leave.status.approved')}</option>
          <option value="rejected">{t('leave.status.rejected')}</option>
        </select>

        <select
          className="rounded border border-slate-300 px-3 py-2 text-sm"
          value={filters.leave_type_id ?? ''}
          onChange={(e) =>
            setFilters((f) => ({
              ...f,
              leave_type_id: e.target.value ? Number(e.target.value) : undefined,
            }))
          }
        >
          <option value="">{t('leave.filter.all_types')}</option>
          {leaveTypes?.map((leaveType) => (
            <option key={leaveType.id} value={leaveType.id}>
              {leaveType.name}
            </option>
          ))}
        </select>
      </div>

      <Card className="p-0">
        {isLoading ? (
          <p className="p-6 text-sm text-slate-500">{t('common.loading')}</p>
        ) : leaveRequests && leaveRequests.length > 0 ? (
          <table className="w-full text-sm">
            <thead className="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
              <tr>
                <th className="px-6 py-3">{t('leave.employee')}</th>
                <th className="px-6 py-3">{t('leave.leave_type')}</th>
                <th className="px-6 py-3">{t('leave.start_date')}</th>
                <th className="px-6 py-3">{t('leave.end_date')}</th>
                <th className="px-6 py-3">{t('leave.status')}</th>
                <th className="px-6 py-3" />
              </tr>
            </thead>
            <tbody>
              {leaveRequests.map((request) => (
                <tr key={request.id} className="border-b border-slate-100">
                  <td className="px-6 py-3 font-medium text-slate-900">
                    {request.employee.first_name} {request.employee.last_name}
                  </td>
                  <td className="px-6 py-3 text-slate-600">{request.leave_type.name}</td>
                  <td className="px-6 py-3 tabular-nums text-slate-600">{request.start_date}</td>
                  <td className="px-6 py-3 tabular-nums text-slate-600">{request.end_date}</td>
                  <td className="px-6 py-3">
                    <Badge tone={statusTone[request.status]}>{t(`leave.status.${request.status}`)}</Badge>
                  </td>
                  <td className="px-6 py-3 text-right">
                    {request.status === 'pending' && (
                      <div className="flex justify-end gap-3">
                        <button
                          type="button"
                          className="text-sm font-medium text-emerald-600 hover:text-emerald-800"
                          onClick={() => approveMutation.mutate(request.id)}
                        >
                          {t('leave.approve')}
                        </button>
                        <button
                          type="button"
                          className="text-sm font-medium text-red-600 hover:text-red-800"
                          onClick={() => rejectMutation.mutate(request.id)}
                        >
                          {t('leave.reject')}
                        </button>
                      </div>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <p className="p-6 text-sm text-slate-500">{t('leave.empty')}</p>
        )}
      </Card>

      {isCreating && (
        <LeaveRequestFormModal
          leaveRequest={null}
          onClose={() => setIsCreating(false)}
          onSubmit={async (values) => {
            await createMutation.mutateAsync(values);
          }}
        />
      )}
    </AppLayout>
  );
}
