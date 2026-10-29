import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import dayjs from 'dayjs';
import { AppLayout } from '../components/layout/AppLayout';
import { Card } from '../components/ui/Card';
import { Button } from '../components/ui/Button';
import { Badge } from '../components/ui/Badge';
import { acknowledgeShiftNotice, fetchShiftNotices } from '../features/shift-notices/api';
import type { ShiftNoticeFilters, ShiftNoticeStatus } from '../types/shift-notices';

const statusTone: Record<ShiftNoticeStatus, 'warning' | 'success'> = {
  submitted: 'warning',
  acknowledged: 'success',
};

export function ShiftNoticesPage() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [filters, setFilters] = useState<ShiftNoticeFilters>({ status: 'submitted' });

  const { data: notices, isLoading } = useQuery({
    queryKey: ['shift-notices', filters],
    queryFn: () => fetchShiftNotices(filters),
  });

  const acknowledgeMutation = useMutation({
    mutationFn: (id: number) => acknowledgeShiftNotice(id),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['shift-notices'] }),
  });

  return (
    <AppLayout>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-semibold text-slate-900">{t('shift_notices.title')}</h1>
      </div>

      <div className="mb-4 flex gap-3">
        <select
          className="rounded border border-slate-300 px-3 py-2 text-sm"
          value={filters.status ?? ''}
          onChange={(e) =>
            setFilters((f) => ({ ...f, status: (e.target.value || undefined) as ShiftNoticeStatus | undefined }))
          }
        >
          <option value="">{t('shift_notices.filter.all')}</option>
          <option value="submitted">{t('shift_notices.status.submitted')}</option>
          <option value="acknowledged">{t('shift_notices.status.acknowledged')}</option>
        </select>

        <select
          className="rounded border border-slate-300 px-3 py-2 text-sm"
          value={filters.type ?? ''}
          onChange={(e) =>
            setFilters((f) => ({ ...f, type: (e.target.value || undefined) as ShiftNoticeFilters['type'] }))
          }
        >
          <option value="">{t('shift_notices.filter.all_types')}</option>
          <option value="sick">{t('shift_notices.type.sick')}</option>
          <option value="late">{t('shift_notices.type.late')}</option>
        </select>
      </div>

      <Card className="p-0">
        {isLoading ? (
          <p className="p-6 text-sm text-slate-500">{t('common.loading')}</p>
        ) : notices && notices.length > 0 ? (
          <table className="w-full text-sm">
            <thead className="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
              <tr>
                <th className="px-6 py-3">{t('shift_notices.employee')}</th>
                <th className="px-6 py-3">{t('shift_notices.type_label')}</th>
                <th className="px-6 py-3">{t('shift_notices.shift')}</th>
                <th className="px-6 py-3">{t('shift_notices.note')}</th>
                <th className="px-6 py-3">{t('common.active')}</th>
                <th className="px-6 py-3" />
              </tr>
            </thead>
            <tbody>
              {notices.map((notice) => (
                <tr key={notice.id} className="border-b border-slate-100">
                  <td className="px-6 py-3 font-medium text-slate-900">
                    {notice.employee ? `${notice.employee.first_name} ${notice.employee.last_name}` : '—'}
                  </td>
                  <td className="px-6 py-3 text-slate-600">
                    {notice.type === 'sick'
                      ? t('shift_notices.type.sick')
                      : t('shift_notices.type.late_with_minutes', { minutes: notice.delay_minutes })}
                  </td>
                  <td className="px-6 py-3 text-slate-600">
                    {notice.shift_assignment ? (
                      <Link to="/schedule" className="underline hover:text-x-brown">
                        {dayjs(notice.shift_assignment.work_date).format('D MMM')} · {notice.shift_assignment.line?.name}
                      </Link>
                    ) : (
                      '—'
                    )}
                  </td>
                  <td className="px-6 py-3 text-slate-600">{notice.note ?? '—'}</td>
                  <td className="px-6 py-3">
                    <Badge tone={statusTone[notice.status]}>{t(`shift_notices.status.${notice.status}`)}</Badge>
                  </td>
                  <td className="px-6 py-3 text-right">
                    {notice.status === 'submitted' && (
                      <Button
                        variant="secondary"
                        className="px-3 py-1.5 text-xs"
                        disabled={acknowledgeMutation.isPending}
                        onClick={() => acknowledgeMutation.mutate(notice.id)}
                      >
                        {t('shift_notices.acknowledge')}
                      </Button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <p className="p-6 text-sm text-slate-500">{t('shift_notices.empty')}</p>
        )}
      </Card>
    </AppLayout>
  );
}
