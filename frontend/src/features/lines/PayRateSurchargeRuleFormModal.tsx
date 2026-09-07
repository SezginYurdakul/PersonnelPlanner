import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useTranslation } from 'react-i18next';
import { Button } from '../../components/ui/Button';
import { TimeInputHint } from '../../components/ui/TimeInputHint';
import type { PayRateSurchargeRule } from '../../types/lines';

const schema = z.object({
  name: z.string().min(1),
  days_of_week: z.array(z.number()).min(1),
  start_time: z.string().regex(/^\d{2}:\d{2}$/),
  end_time: z.string().regex(/^\d{2}:\d{2}$/),
  surcharge_percentage: z.coerce.number().gt(0),
});

type FormInput = z.input<typeof schema>;
type FormValues = z.output<typeof schema>;

const DAYS = [1, 2, 3, 4, 5, 6, 7];

export function PayRateSurchargeRuleFormModal({
  rule,
  onSubmit,
  onClose,
}: {
  rule: PayRateSurchargeRule | null;
  onSubmit: (values: FormValues) => Promise<void>;
  onClose: () => void;
}) {
  const { t } = useTranslation();
  const {
    register,
    handleSubmit,
    watch,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<FormInput, unknown, FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: rule?.name ?? '',
      days_of_week: rule?.days_of_week ?? [],
      start_time: rule?.start_time.slice(0, 5) ?? '',
      end_time: rule?.end_time.slice(0, 5) ?? '',
      surcharge_percentage: rule ? Number(rule.surcharge_percentage) : undefined,
    },
  });

  const selectedDays = (watch('days_of_week') as number[] | undefined) ?? [];
  const startTime = watch('start_time') as string | undefined;
  const endTime = watch('end_time') as string | undefined;

  function toggleDay(day: number) {
    const next = selectedDays.includes(day)
      ? selectedDays.filter((d) => d !== day)
      : [...selectedDays, day];
    setValue('days_of_week', next, { shouldValidate: true });
  }

  async function submit(values: FormValues) {
    await onSubmit(values);
    onClose();
  }

  return (
    <div className="fixed inset-0 z-10 flex items-center justify-center bg-black/30 p-4">
      <form onSubmit={handleSubmit(submit)} className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
        <h2 className="mb-4 text-lg font-semibold text-slate-900">
          {rule ? t('pay_rate_rules.edit') : t('pay_rate_rules.new')}
        </h2>

        <label className="mb-1 block text-sm font-medium text-slate-700">{t('pay_rate_rules.name')}</label>
        <input className="mb-3 w-full rounded border border-slate-300 px-3 py-2 text-sm" {...register('name')} />
        {errors.name && <p className="mb-2 text-sm text-red-600">{errors.name.message}</p>}

        <label className="mb-1 block text-sm font-medium text-slate-700">
          {t('pay_rate_rules.days_of_week')}
        </label>
        <div className="mb-3 flex flex-wrap gap-2">
          {DAYS.map((day) => (
            <button
              key={day}
              type="button"
              onClick={() => toggleDay(day)}
              className={`rounded px-2 py-1 text-xs font-medium ${
                selectedDays.includes(day)
                  ? 'bg-slate-900 text-white'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
              }`}
            >
              {t(`pay_rate_rules.day.${day}`)}
            </button>
          ))}
        </div>
        {errors.days_of_week && (
          <p className="mb-2 text-sm text-red-600">{t('pay_rate_rules.days_of_week')} is required</p>
        )}

        <div className="mb-3 grid grid-cols-2 gap-3">
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">
              {t('pay_rate_rules.start_time')}
            </label>
            <input
              type="time"
              className="w-full rounded border border-slate-300 px-3 py-2 text-sm"
              {...register('start_time')}
            />
            <TimeInputHint time={startTime} />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">
              {t('pay_rate_rules.end_time')}
            </label>
            <input
              type="time"
              className="w-full rounded border border-slate-300 px-3 py-2 text-sm"
              {...register('end_time')}
            />
            <TimeInputHint time={endTime} />
          </div>
        </div>

        <label className="mb-1 block text-sm font-medium text-slate-700">
          {t('pay_rate_rules.surcharge_percentage')}
        </label>
        <input
          type="number"
          step="0.01"
          className="mb-4 w-full rounded border border-slate-300 px-3 py-2 text-sm"
          {...register('surcharge_percentage')}
        />
        {errors.surcharge_percentage && (
          <p className="mb-2 text-sm text-red-600">{errors.surcharge_percentage.message}</p>
        )}

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
