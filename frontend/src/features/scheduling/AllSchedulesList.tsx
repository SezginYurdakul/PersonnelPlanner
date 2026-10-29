import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Card } from '../../components/ui/Card';
import { ConfirmDialog } from '../../components/ui/ConfirmDialog';
import type { Schedule, ScheduleStatus } from '../../types/scheduling';
import { deleteSchedule, fetchSchedules } from './api';

const statusTone: Record<ScheduleStatus, 'neutral' | 'info' | 'success'> = {
  draft: 'neutral',
  proposed: 'info',
  approved: 'success',
};

export function AllSchedulesList({ onSelect }: { onSelect: (scheduleId: number) => void }) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [statusFilter, setStatusFilter] = useState<ScheduleStatus | ''>('');
  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');
  const [pendingDelete, setPendingDelete] = useState<Schedule | null>(null);

  const { data: schedules, isLoading } = useQuery({
    queryKey: ['schedules'],
    queryFn: fetchSchedules,
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteSchedule(id),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['schedules'] }),
  });

  const filtered = useMemo(() => {
    return (schedules ?? []).filter((schedule) => {
      if (statusFilter && schedule.status !== statusFilter) return false;
      if (fromDate && schedule.week_start_date < fromDate) return false;
      if (toDate && schedule.week_start_date > toDate) return false;
      return true;
    });
  }, [schedules, statusFilter, fromDate, toDate]);

  return (
    <div className="mt-8">
      <h2 className="mb-3 text-lg font-extrabold text-x-brown">{t('scheduling.all_plans_title')}</h2>

      <div className="mb-4 flex flex-wrap items-end gap-3">
        <div>
          <label className="mb-1 block text-xs font-semibold text-slate-600">{t('scheduling.filter_status')}</label>
          <select
            className="rounded border border-slate-300 px-3 py-2 text-sm"
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value as ScheduleStatus | '')}
          >
            <option value="">{t('scheduling.filter_status_all')}</option>
            <option value="draft">{t('scheduling.status.draft')}</option>
            <option value="proposed">{t('scheduling.status.proposed')}</option>
            <option value="approved">{t('scheduling.status.approved')}</option>
          </select>
        </div>
        <div>
          <label className="mb-1 block text-xs font-semibold text-slate-600">{t('scheduling.filter_from')}</label>
          <input
            type="date"
            className="rounded border border-slate-300 px-3 py-2 text-sm"
            value={fromDate}
            onChange={(e) => setFromDate(e.target.value)}
          />
        </div>
        <div>
          <label className="mb-1 block text-xs font-semibold text-slate-600">{t('scheduling.filter_to')}</label>
          <input
            type="date"
            className="rounded border border-slate-300 px-3 py-2 text-sm"
            value={toDate}
            onChange={(e) => setToDate(e.target.value)}
          />
        </div>
        {(statusFilter || fromDate || toDate) && (
          <button
            type="button"
            className="text-sm font-medium text-slate-500 hover:text-slate-700"
            onClick={() => {
              setStatusFilter('');
              setFromDate('');
              setToDate('');
            }}
          >
            {t('scheduling.filter_clear')}
          </button>
        )}
      </div>

      {isLoading ? (
        <p className="text-sm text-slate-500">{t('common.loading')}</p>
      ) : filtered.length > 0 ? (
        <div className="space-y-3">
          {filtered.map((schedule: Schedule) => (
            <Card key={schedule.id} className="flex items-center justify-between gap-4">
              <button type="button" className="flex-1 text-left" onClick={() => onSelect(schedule.id)}>
                <div className="flex items-center gap-2">
                  <span className="text-xs font-semibold text-slate-500">{schedule.week_start_date}</span>
                  <h3 className="text-sm font-extrabold text-x-brown">{schedule.label || t('scheduling.untitled_draft')}</h3>
                  <Badge tone={statusTone[schedule.status]}>{t(`scheduling.status.${schedule.status}`)}</Badge>
                </div>
                {schedule.note && <p className="mt-1 text-xs text-slate-500">{schedule.note}</p>}
              </button>
              <div className="flex shrink-0 items-center gap-2">
                <Button variant="secondary" onClick={() => onSelect(schedule.id)}>
                  {t('scheduling.open')}
                </Button>
                {schedule.status === 'draft' && (
                  <Button variant="danger" onClick={() => setPendingDelete(schedule)}>
                    {t('scheduling.delete')}
                  </Button>
                )}
              </div>
            </Card>
          ))}
        </div>
      ) : (
        <Card>
          <p className="text-sm text-slate-500">{t('scheduling.no_plans_match_filter')}</p>
        </Card>
      )}

      {pendingDelete && (
        <ConfirmDialog
          title={t('scheduling.delete_confirm_title')}
          message={t('scheduling.delete_confirm_message', { label: pendingDelete.label || t('scheduling.untitled_draft') })}
          confirmLabel={t('scheduling.delete')}
          danger
          onCancel={() => setPendingDelete(null)}
          onConfirm={() => {
            deleteMutation.mutate(pendingDelete.id);
            setPendingDelete(null);
          }}
        />
      )}
    </div>
  );
}
