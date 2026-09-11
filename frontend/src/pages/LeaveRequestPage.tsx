import { useMemo, useState } from 'react';
import dayjs from 'dayjs';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { EmployeeAppLayout } from '../components/layout/EmployeeAppLayout';
import { Button } from '../components/ui/Button';
import { Card } from '../components/ui/Card';
import { createMyLeaveRequest, fetchMyLeaveRequests } from '../features/leave/api';
import { fetchMyCompanySettings } from '../features/company-settings/api';

const schema = z.object({
  start_date: z.string().min(1),
  end_date: z.string().min(1),
  reason: z.string().optional(),
});

type FormValues = z.infer<typeof schema>;

export function LeaveRequestPage() {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [submitError, setSubmitError] = useState<string | null>(null);

  const { data: settings } = useQuery({
    queryKey: ['my-company-settings'],
    queryFn: fetchMyCompanySettings,
  });

  const { data: myRequests } = useQuery({
    queryKey: ['my-leave-requests'],
    queryFn: fetchMyLeaveRequests,
  });

  const {
    register,
    handleSubmit,
    watch,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { start_date: '', end_date: '', reason: '' },
  });

  const startDate = watch('start_date');

  const noticeWarning = useMemo(() => {
    if (!settings || !startDate) return null;
    const daysUntil = dayjs(startDate).diff(dayjs().startOf('day'), 'day');
    if (daysUntil < settings.annual_leave_min_notice_days) {
      return t('leave_request.notice_warning', { days: settings.annual_leave_min_notice_days });
    }
    return null;
  }, [settings, startDate, t]);

  const createMutation = useMutation({
    mutationFn: createMyLeaveRequest,
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['my-leave-requests'] });
      reset({ start_date: '', end_date: '', reason: '' });
      setSubmitError(null);
    },
    onError: (error: unknown) => {
      const message =
        (error as { response?: { data?: { message?: string } } })?.response?.data?.message ??
        t('leave_request.submit_error');
      setSubmitError(message);
    },
  });

  async function submit(values: FormValues) {
    setSubmitError(null);
    await createMutation.mutateAsync(values);
  }

  return (
    <EmployeeAppLayout>
      <h1 className="mb-4 text-base font-extrabold text-x-brown">{t('leave_request.title')}</h1>

      <Card className="mb-6">
        <form onSubmit={handleSubmit(submit)}>
          <label className="mb-1 block text-sm font-semibold text-slate-700">{t('leave_request.start_date')}</label>
          <input
            type="date"
            className="mb-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
            {...register('start_date')}
          />
          {errors.start_date && <p className="mb-2 text-sm text-red-600">{errors.start_date.message}</p>}

          <label className="mb-1 mt-3 block text-sm font-semibold text-slate-700">{t('leave_request.end_date')}</label>
          <input
            type="date"
            className="mb-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
            {...register('end_date')}
          />
          {errors.end_date && <p className="mb-2 text-sm text-red-600">{errors.end_date.message}</p>}

          {noticeWarning && <p className="mt-2 text-sm font-medium text-amber-700">{noticeWarning}</p>}

          <label className="mb-1 mt-3 block text-sm font-semibold text-slate-700">{t('leave_request.reason')}</label>
          <textarea
            rows={3}
            className="mb-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
            {...register('reason')}
          />

          {submitError && <p className="mt-2 text-sm text-red-600">{submitError}</p>}

          <Button type="submit" disabled={isSubmitting} className="mt-4 w-full">
            {t('leave_request.submit')}
          </Button>
        </form>
      </Card>

      <h2 className="mb-3 text-sm font-bold text-slate-600">{t('leave_request.my_requests')}</h2>
      <div className="space-y-2">
        {(myRequests ?? []).length === 0 && (
          <p className="text-sm text-slate-400">{t('leave_request.no_requests')}</p>
        )}
        {(myRequests ?? []).map((request) => (
          <Card key={request.id} className="p-3.5">
            <div className="flex items-center justify-between">
              <span className="text-sm font-semibold text-x-brown">
                {dayjs(request.start_date).format('D MMM')} – {dayjs(request.end_date).format('D MMM YYYY')}
              </span>
              <span
                className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase ${
                  request.status === 'approved'
                    ? 'bg-emerald-100 text-emerald-700'
                    : request.status === 'rejected'
                      ? 'bg-rose-100 text-rose-700'
                      : 'bg-amber-100 text-amber-700'
                }`}
              >
                {t(`leave_request.status.${request.status}`)}
              </span>
            </div>
          </Card>
        ))}
      </div>
    </EmployeeAppLayout>
  );
}
