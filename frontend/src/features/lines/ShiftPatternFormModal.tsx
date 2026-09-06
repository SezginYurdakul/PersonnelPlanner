import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useTranslation } from 'react-i18next';
import { Button } from '../../components/ui/Button';
import type { ShiftPattern } from '../../types/lines';

const schema = z.object({
  name: z.string().min(1),
  start_time: z.string().regex(/^\d{2}:\d{2}$/),
  end_time: z.string().regex(/^\d{2}:\d{2}$/),
});

type FormValues = z.infer<typeof schema>;

export function ShiftPatternFormModal({
  shiftPattern,
  onSubmit,
  onClose,
}: {
  shiftPattern: ShiftPattern | null;
  onSubmit: (values: FormValues) => Promise<void>;
  onClose: () => void;
}) {
  const { t } = useTranslation();
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: shiftPattern?.name ?? '',
      start_time: shiftPattern?.start_time.slice(0, 5) ?? '',
      end_time: shiftPattern?.end_time.slice(0, 5) ?? '',
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
          {shiftPattern ? t('shift_patterns.edit') : t('shift_patterns.new')}
        </h2>

        <label className="mb-1 block text-sm font-medium text-slate-700">{t('shift_patterns.name')}</label>
        <input className="mb-3 w-full rounded border border-slate-300 px-3 py-2 text-sm" {...register('name')} />
        {errors.name && <p className="mb-2 text-sm text-red-600">{errors.name.message}</p>}

        <div className="mb-4 grid grid-cols-2 gap-3">
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">
              {t('shift_patterns.start_time')}
            </label>
            <input
              type="time"
              className="w-full rounded border border-slate-300 px-3 py-2 text-sm"
              {...register('start_time')}
            />
            {errors.start_time && <p className="mt-1 text-sm text-red-600">{errors.start_time.message}</p>}
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">
              {t('shift_patterns.end_time')}
            </label>
            <input
              type="time"
              className="w-full rounded border border-slate-300 px-3 py-2 text-sm"
              {...register('end_time')}
            />
            {errors.end_time && <p className="mt-1 text-sm text-red-600">{errors.end_time.message}</p>}
          </div>
        </div>

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
