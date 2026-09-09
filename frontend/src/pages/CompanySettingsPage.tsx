import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AppLayout } from '../components/layout/AppLayout';
import { Button } from '../components/ui/Button';
import { Card } from '../components/ui/Card';
import { fetchCompanySettings, updateCompanySettings } from '../features/company-settings/api';

const schema = z.object({
  annual_leave_min_notice_days: z.coerce.number().int().min(0),
  shift_notice_min_notice_hours: z.coerce.number().int().min(0),
  emergency_contact_phone: z.string().optional(),
});

type FormInput = z.input<typeof schema>;
type FormValues = z.output<typeof schema>;

export function CompanySettingsPage() {
  const queryClient = useQueryClient();
  const { data: settings, isLoading } = useQuery({
    queryKey: ['company-settings'],
    queryFn: fetchCompanySettings,
  });

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<FormInput, unknown, FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      annual_leave_min_notice_days: 14,
      shift_notice_min_notice_hours: 2,
      emergency_contact_phone: '',
    },
  });

  useEffect(() => {
    if (settings) {
      reset({
        annual_leave_min_notice_days: settings.annual_leave_min_notice_days,
        shift_notice_min_notice_hours: settings.shift_notice_min_notice_hours,
        emergency_contact_phone: settings.emergency_contact_phone ?? '',
      });
    }
  }, [settings, reset]);

  const updateMutation = useMutation({
    mutationFn: (values: FormValues) => updateCompanySettings(values),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['company-settings'] }),
  });

  async function submit(values: FormValues) {
    await updateMutation.mutateAsync(values);
  }

  return (
    <AppLayout>
      <h1 className="mb-6 text-xl font-extrabold tracking-tight text-x-brown">Company Settings</h1>

      {isLoading ? (
        <p className="text-sm text-slate-500">Loading…</p>
      ) : (
        <Card className="max-w-lg">
          <form onSubmit={handleSubmit(submit)}>
            <label className="mb-1 block text-sm font-semibold text-slate-700">
              Annual leave minimum notice (days)
            </label>
            <input
              type="number"
              min={0}
              className="mb-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
              {...register('annual_leave_min_notice_days')}
            />
            <p className="mb-3 text-xs text-slate-400">
              An employee must submit an annual leave request at least this many days before the start date.
            </p>
            {errors.annual_leave_min_notice_days && (
              <p className="mb-2 text-sm text-red-600">{errors.annual_leave_min_notice_days.message}</p>
            )}

            <label className="mb-1 block text-sm font-semibold text-slate-700">
              Shift notice minimum notice (hours)
            </label>
            <input
              type="number"
              min={0}
              className="mb-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
              {...register('shift_notice_min_notice_hours')}
            />
            <p className="mb-3 text-xs text-slate-400">
              An employee can only submit a sick or late-arrival notice while their shift is at least this many
              hours away. Closer than that, they see the emergency contact phone number instead.
            </p>
            {errors.shift_notice_min_notice_hours && (
              <p className="mb-2 text-sm text-red-600">{errors.shift_notice_min_notice_hours.message}</p>
            )}

            <label className="mb-1 block text-sm font-semibold text-slate-700">Emergency contact phone</label>
            <input
              type="text"
              placeholder="+31 6 12345678"
              className="mb-4 w-full rounded border border-slate-300 px-3 py-2 text-sm"
              {...register('emergency_contact_phone')}
            />

            <Button type="submit" disabled={isSubmitting}>
              Save
            </Button>
          </form>
        </Card>
      )}
    </AppLayout>
  );
}
