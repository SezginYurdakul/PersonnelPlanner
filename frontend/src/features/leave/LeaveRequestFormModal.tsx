import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useTranslation } from 'react-i18next';
import { useQuery } from '@tanstack/react-query';
import { Button } from '../../components/ui/Button';
import { fetchEmployees } from '../staff/api';
import { fetchLeaveTypes } from './api';
import type { LeaveRequest } from '../../types/leave';

const schema = z.object({
  employee_id: z.coerce.number().min(1),
  leave_type_id: z.coerce.number().min(1),
  start_date: z.string().min(1),
  end_date: z.string().min(1),
  reason: z.string().optional(),
});

type FormInput = z.input<typeof schema>;
type FormValues = z.output<typeof schema>;

export function LeaveRequestFormModal({
  leaveRequest,
  onSubmit,
  onClose,
}: {
  leaveRequest: LeaveRequest | null;
  onSubmit: (values: FormValues) => Promise<void>;
  onClose: () => void;
}) {
  const { t } = useTranslation();
  const { data: employees } = useQuery({ queryKey: ['employees', {}], queryFn: () => fetchEmployees() });
  const { data: leaveTypes } = useQuery({ queryKey: ['leave-types'], queryFn: fetchLeaveTypes });

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormInput, unknown, FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      employee_id: leaveRequest?.employee.id,
      leave_type_id: leaveRequest?.leave_type.id,
      start_date: leaveRequest?.start_date ?? '',
      end_date: leaveRequest?.end_date ?? '',
      reason: leaveRequest?.reason ?? '',
    },
  });

  async function submit(values: FormValues) {
    await onSubmit(values);
    onClose();
  }

  return (
    <div className="fixed inset-0 z-10 flex items-center justify-center bg-black/30 p-4">
      <form onSubmit={handleSubmit(submit)} className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
        <h2 className="mb-4 text-lg font-semibold text-slate-900">
          {leaveRequest ? t('leave.edit') : t('leave.new')}
        </h2>

        <label className="mb-1 block text-sm font-medium text-slate-700">{t('leave.employee')}</label>
        <select className="mb-3 w-full rounded border border-slate-300 px-3 py-2 text-sm" {...register('employee_id')}>
          <option value="">—</option>
          {employees?.map((employee) => (
            <option key={employee.id} value={employee.id}>
              {employee.first_name} {employee.last_name}
            </option>
          ))}
        </select>
        {errors.employee_id && <p className="mb-2 text-sm text-red-600">{errors.employee_id.message}</p>}

        <label className="mb-1 block text-sm font-medium text-slate-700">{t('leave.leave_type')}</label>
        <select
          className="mb-3 w-full rounded border border-slate-300 px-3 py-2 text-sm"
          {...register('leave_type_id')}
        >
          <option value="">—</option>
          {leaveTypes?.map((leaveType) => (
            <option key={leaveType.id} value={leaveType.id}>
              {leaveType.name}
            </option>
          ))}
        </select>
        {errors.leave_type_id && <p className="mb-2 text-sm text-red-600">{errors.leave_type_id.message}</p>}

        <div className="mb-3 grid grid-cols-2 gap-3">
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">{t('leave.start_date')}</label>
            <input
              type="date"
              className="w-full rounded border border-slate-300 px-3 py-2 text-sm"
              {...register('start_date')}
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">{t('leave.end_date')}</label>
            <input
              type="date"
              className="w-full rounded border border-slate-300 px-3 py-2 text-sm"
              {...register('end_date')}
            />
            {errors.end_date && <p className="mt-1 text-sm text-red-600">{errors.end_date.message}</p>}
          </div>
        </div>

        <label className="mb-1 block text-sm font-medium text-slate-700">{t('leave.reason')}</label>
        <textarea
          className="mb-4 w-full rounded border border-slate-300 px-3 py-2 text-sm"
          rows={3}
          {...register('reason')}
        />

        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {t('common.save')}
          </Button>
        </div>
      </form>
    </div>
  );
}
