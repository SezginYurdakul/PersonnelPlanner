import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useTranslation } from 'react-i18next';
import { useQuery } from '@tanstack/react-query';
import { Button } from '../../components/ui/Button';
import { fetchAgencies } from './api';
import type { Employee } from '../../types/staff';

const schema = z
  .object({
    first_name: z.string().min(1),
    last_name: z.string().min(1),
    phone: z.string().optional(),
    email: z.string().email().optional().or(z.literal('')),
    employee_type: z.enum(['vast', 'uitzendkracht']),
    agency_id: z.coerce.number().optional(),
    pay_type: z.enum(['hourly', 'monthly']),
    hourly_rate: z.coerce.number().optional(),
    monthly_salary: z.coerce.number().optional(),
    contracted_hours_per_week: z.coerce.number().optional(),
  })
  .refine((v) => v.employee_type !== 'uitzendkracht' || v.agency_id, {
    message: 'Agency is required for agency staff',
    path: ['agency_id'],
  })
  .refine((v) => v.pay_type !== 'hourly' || v.hourly_rate !== undefined, {
    message: 'Hourly rate is required',
    path: ['hourly_rate'],
  })
  .refine((v) => v.pay_type !== 'monthly' || v.monthly_salary !== undefined, {
    message: 'Monthly salary is required',
    path: ['monthly_salary'],
  });

type FormInput = z.input<typeof schema>;
type FormValues = z.output<typeof schema>;

export function EmployeeFormModal({
  employee,
  onSubmit,
  onClose,
}: {
  employee: Employee | null;
  onSubmit: (values: FormValues) => Promise<void>;
  onClose: () => void;
}) {
  const { t } = useTranslation();
  const { data: agencies } = useQuery({ queryKey: ['agencies'], queryFn: fetchAgencies });

  const {
    register,
    handleSubmit,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<FormInput, unknown, FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      first_name: employee?.first_name ?? '',
      last_name: employee?.last_name ?? '',
      phone: employee?.phone ?? '',
      email: employee?.email ?? '',
      employee_type: employee?.employee_type ?? 'vast',
      agency_id: employee?.agency?.id,
      pay_type: employee?.pay_type ?? 'hourly',
      hourly_rate: employee?.hourly_rate ? Number(employee.hourly_rate) : undefined,
      monthly_salary: employee?.monthly_salary ? Number(employee.monthly_salary) : undefined,
      contracted_hours_per_week: employee?.contracted_hours_per_week
        ? Number(employee.contracted_hours_per_week)
        : undefined,
    },
  });

  const employeeType = watch('employee_type');
  const payType = watch('pay_type');

  async function submit(values: FormValues) {
    await onSubmit(values);
    onClose();
  }

  return (
    <div className="fixed inset-0 z-10 flex items-center justify-center bg-black/30 p-4">
      <form
        onSubmit={handleSubmit(submit)}
        className="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl"
      >
        <h2 className="mb-4 text-lg font-semibold text-slate-900">
          {employee ? t('staff.edit') : t('staff.new')}
        </h2>

        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">{t('staff.first_name')}</label>
            <input className="w-full rounded border border-slate-300 px-3 py-2 text-sm" {...register('first_name')} />
            {errors.first_name && <p className="mt-1 text-sm text-red-600">{errors.first_name.message}</p>}
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">{t('staff.last_name')}</label>
            <input className="w-full rounded border border-slate-300 px-3 py-2 text-sm" {...register('last_name')} />
            {errors.last_name && <p className="mt-1 text-sm text-red-600">{errors.last_name.message}</p>}
          </div>
        </div>

        <div className="mt-3 grid grid-cols-2 gap-3">
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">{t('staff.phone')}</label>
            <input className="w-full rounded border border-slate-300 px-3 py-2 text-sm" {...register('phone')} />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">{t('staff.email')}</label>
            <input className="w-full rounded border border-slate-300 px-3 py-2 text-sm" {...register('email')} />
          </div>
        </div>

        <div className="mt-3">
          <label className="mb-1 block text-sm font-medium text-slate-700">{t('staff.employee_type')}</label>
          <select className="w-full rounded border border-slate-300 px-3 py-2 text-sm" {...register('employee_type')}>
            <option value="vast">{t('staff.employee_type.vast')}</option>
            <option value="uitzendkracht">{t('staff.employee_type.uitzendkracht')}</option>
          </select>
        </div>

        {employeeType === 'uitzendkracht' && (
          <div className="mt-3">
            <label className="mb-1 block text-sm font-medium text-slate-700">{t('staff.agency')}</label>
            <select className="w-full rounded border border-slate-300 px-3 py-2 text-sm" {...register('agency_id')}>
              <option value="">—</option>
              {agencies?.map((agency) => (
                <option key={agency.id} value={agency.id}>
                  {agency.name}
                </option>
              ))}
            </select>
            {errors.agency_id && <p className="mt-1 text-sm text-red-600">{errors.agency_id.message}</p>}
          </div>
        )}

        {employeeType === 'vast' && (
          <div className="mt-3">
            <label className="mb-1 block text-sm font-medium text-slate-700">{t('staff.pay_type')}</label>
            <select className="w-full rounded border border-slate-300 px-3 py-2 text-sm" {...register('pay_type')}>
              <option value="hourly">{t('staff.pay_type.hourly')}</option>
              <option value="monthly">{t('staff.pay_type.monthly')}</option>
            </select>
          </div>
        )}

        {payType === 'hourly' && (
          <div className="mt-3">
            <label className="mb-1 block text-sm font-medium text-slate-700">{t('staff.hourly_rate')}</label>
            <input
              type="number"
              step="0.01"
              className="w-full rounded border border-slate-300 px-3 py-2 text-sm"
              {...register('hourly_rate')}
            />
            {errors.hourly_rate && <p className="mt-1 text-sm text-red-600">{errors.hourly_rate.message}</p>}
          </div>
        )}

        {payType === 'monthly' && (
          <div className="mt-3 grid grid-cols-2 gap-3">
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">{t('staff.monthly_salary')}</label>
              <input
                type="number"
                step="0.01"
                className="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                {...register('monthly_salary')}
              />
              {errors.monthly_salary && <p className="mt-1 text-sm text-red-600">{errors.monthly_salary.message}</p>}
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700">
                {t('staff.contracted_hours_per_week')}
              </label>
              <input
                type="number"
                step="0.5"
                className="w-full rounded border border-slate-300 px-3 py-2 text-sm"
                {...register('contracted_hours_per_week')}
              />
            </div>
          </div>
        )}

        <div className="mt-6 flex justify-end gap-2">
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
