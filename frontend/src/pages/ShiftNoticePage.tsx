import { useMemo, useState } from 'react';
import dayjs from 'dayjs';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { EmployeeAppLayout } from '../components/layout/EmployeeAppLayout';
import { Button } from '../components/ui/Button';
import { Card } from '../components/ui/Card';
import { fetchMySchedule } from '../features/employee-schedule/api';
import { createShiftNotice, fetchShiftNoticeEligibility } from '../features/shift-notices/api';
import type { ShiftNoticeType } from '../types/shift-notices';

function currentMonday(): string {
  const today = dayjs();
  const isoDay = today.day() === 0 ? 7 : today.day();
  return today.subtract(isoDay - 1, 'day').format('YYYY-MM-DD');
}

const schema = z.object({
  type: z.enum(['sick', 'late']),
  delay_minutes: z.coerce.number().int().min(1).optional(),
  note: z.string().optional(),
});

type FormInput = z.input<typeof schema>;
type FormValues = z.output<typeof schema>;

export function ShiftNoticePage() {
  const { t } = useTranslation();
  const [selectedAssignmentId, setSelectedAssignmentId] = useState<number | null>(null);
  const [submitted, setSubmitted] = useState(false);

  const { data: schedule, isLoading: isLoadingSchedule } = useQuery({
    queryKey: ['my-schedule', currentMonday()],
    queryFn: () => fetchMySchedule(currentMonday()),
  });

  const upcomingAssignments = useMemo(() => {
    const today = dayjs().format('YYYY-MM-DD');
    return (schedule?.assignments ?? []).filter((a) => a.work_date >= today);
  }, [schedule]);

  const { data: eligibility, isLoading: isLoadingEligibility } = useQuery({
    queryKey: ['shift-notice-eligibility', selectedAssignmentId],
    queryFn: () => fetchShiftNoticeEligibility(selectedAssignmentId!),
    enabled: selectedAssignmentId !== null,
  });

  const {
    register,
    handleSubmit,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<FormInput, unknown, FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { type: 'sick' },
  });

  const noticeType = watch('type');

  const createMutation = useMutation({
    mutationFn: createShiftNotice,
    onSuccess: () => setSubmitted(true),
  });

  async function submit(values: FormValues) {
    if (selectedAssignmentId === null) return;
    await createMutation.mutateAsync({
      shift_assignment_id: selectedAssignmentId,
      type: values.type as ShiftNoticeType,
      delay_minutes: values.type === 'late' ? values.delay_minutes : undefined,
      note: values.note,
    });
  }

  if (submitted) {
    return (
      <EmployeeAppLayout>
        <Card className="text-center">
          <i className="fa-solid fa-circle-check mb-3 text-3xl text-emerald-600" />
          <p className="text-sm font-semibold text-x-brown">{t('shift_notice.submitted')}</p>
        </Card>
      </EmployeeAppLayout>
    );
  }

  return (
    <EmployeeAppLayout>
      <h1 className="mb-4 text-base font-extrabold text-x-brown">{t('shift_notice.title')}</h1>

      {isLoadingSchedule ? (
        <p className="text-sm text-slate-500">{t('common.loading')}</p>
      ) : selectedAssignmentId === null ? (
        <div className="space-y-2">
          <p className="mb-2 text-sm text-slate-500">{t('shift_notice.select_shift')}</p>
          {upcomingAssignments.length === 0 && (
            <p className="text-sm text-slate-400">{t('shift_notice.no_upcoming_shifts')}</p>
          )}
          {upcomingAssignments.map((assignment) => (
            <button
              key={assignment.id}
              type="button"
              onClick={() => setSelectedAssignmentId(assignment.id)}
              className="flex w-full items-center justify-between rounded-xl border border-x-border bg-white p-3.5 text-left text-sm shadow-sm hover:border-x-gold"
            >
              <span className="font-semibold text-x-brown">{dayjs(assignment.work_date).format('ddd D MMM')}</span>
              <span className="text-slate-500">
                {assignment.starts_at?.slice(0, 5)}–{assignment.ends_at?.slice(0, 5)}
              </span>
            </button>
          ))}
        </div>
      ) : isLoadingEligibility ? (
        <p className="text-sm text-slate-500">{t('common.loading')}</p>
      ) : eligibility && !eligibility.can_submit ? (
        <Card className="text-center">
          <i className="fa-solid fa-phone mb-3 text-2xl text-amber-600" />
          <p className="mb-2 text-sm font-semibold text-x-brown">{t('shift_notice.too_late_title')}</p>
          <p className="text-lg font-bold text-x-brown">{eligibility.phone_number ?? '—'}</p>
          <button
            type="button"
            onClick={() => setSelectedAssignmentId(null)}
            className="mt-4 text-sm font-medium text-slate-500 underline"
          >
            {t('common.cancel')}
          </button>
        </Card>
      ) : (
        <Card>
          <form onSubmit={handleSubmit(submit)}>
            <label className="mb-1 block text-sm font-semibold text-slate-700">{t('shift_notice.type')}</label>
            <select className="mb-3 w-full rounded border border-slate-300 px-3 py-2 text-sm" {...register('type')}>
              <option value="sick">{t('shift_notice.type_sick')}</option>
              <option value="late">{t('shift_notice.type_late')}</option>
            </select>

            {noticeType === 'late' && (
              <>
                <label className="mb-1 block text-sm font-semibold text-slate-700">
                  {t('shift_notice.delay_minutes')}
                </label>
                <input
                  type="number"
                  min={1}
                  className="mb-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
                  {...register('delay_minutes')}
                />
                {errors.delay_minutes && (
                  <p className="mb-2 text-sm text-red-600">{errors.delay_minutes.message}</p>
                )}
              </>
            )}

            <label className="mb-1 mt-3 block text-sm font-semibold text-slate-700">{t('shift_notice.note')}</label>
            <textarea
              rows={3}
              className="mb-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
              {...register('note')}
            />

            <Button type="submit" disabled={isSubmitting} className="mt-4 w-full">
              {t('shift_notice.submit')}
            </Button>
            <button
              type="button"
              onClick={() => setSelectedAssignmentId(null)}
              className="mt-2 w-full text-sm font-medium text-slate-500"
            >
              {t('common.cancel')}
            </button>
          </form>
        </Card>
      )}
    </EmployeeAppLayout>
  );
}
