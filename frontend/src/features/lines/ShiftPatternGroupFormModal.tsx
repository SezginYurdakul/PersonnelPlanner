import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useTranslation } from 'react-i18next';
import { Button } from '../../components/ui/Button';
import { TimeSelect } from '../../components/ui/TimeSelect';
import type { ShiftPatternGroup, ShiftPatternGroupFormValues, SlotType } from '../../types/lines';

const timeSchema = z.string().regex(/^\d{2}:\d{2}$/);

const schema = z.object({
  name: z.string().min(1),
  slots: z.object({
    day: z.object({ start_time: timeSchema, end_time: timeSchema }),
    afternoon: z.object({ start_time: timeSchema, end_time: timeSchema }),
    night: z.object({ start_time: timeSchema, end_time: timeSchema }),
  }),
});

type FormValues = z.infer<typeof schema>;

const SLOT_TYPES: SlotType[] = ['day', 'afternoon', 'night'];

function defaultSlotTimes(group: ShiftPatternGroup | null, slotType: SlotType) {
  const pattern = group?.patterns.find((p) => p.slot_type === slotType);
  return {
    start_time: pattern?.start_time.slice(0, 5) ?? '',
    end_time: pattern?.end_time.slice(0, 5) ?? '',
  };
}

export function ShiftPatternGroupFormModal({
  group,
  onSubmit,
  onClose,
}: {
  group: ShiftPatternGroup | null;
  onSubmit: (values: ShiftPatternGroupFormValues) => Promise<void>;
  onClose: () => void;
}) {
  const { t } = useTranslation();
  const {
    register,
    handleSubmit,
    control,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: group?.name ?? '',
      slots: {
        day: defaultSlotTimes(group, 'day'),
        afternoon: defaultSlotTimes(group, 'afternoon'),
        night: defaultSlotTimes(group, 'night'),
      },
    },
  });

  async function submit(values: FormValues) {
    const payload: ShiftPatternGroupFormValues = {
      name: values.name,
      slots: {
        day: { ...values.slots.day, crosses_midnight: values.slots.day.end_time <= values.slots.day.start_time },
        afternoon: {
          ...values.slots.afternoon,
          crosses_midnight: values.slots.afternoon.end_time <= values.slots.afternoon.start_time,
        },
        night: {
          ...values.slots.night,
          crosses_midnight: values.slots.night.end_time <= values.slots.night.start_time,
        },
      },
    };
    await onSubmit(payload);
    onClose();
  }

  return (
    <div className="fixed inset-0 z-10 flex items-center justify-center bg-black/30 p-4">
      <form onSubmit={handleSubmit(submit)} className="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
        <h2 className="mb-4 text-lg font-semibold text-slate-900">
          {group ? t('shift_pattern_groups.edit') : t('shift_pattern_groups.new')}
        </h2>

        <label className="mb-1 block text-sm font-medium text-slate-700">{t('shift_pattern_groups.name')}</label>
        <input
          placeholder={t('shift_pattern_groups.name_placeholder')}
          className="mb-4 w-full rounded border border-slate-300 px-3 py-2 text-sm"
          {...register('name')}
        />
        {errors.name && <p className="mb-2 text-sm text-red-600">{errors.name.message}</p>}

        <div className="space-y-4">
          {SLOT_TYPES.map((slotType) => (
            <div key={slotType} className="rounded-lg border border-slate-200 p-3">
              <p className="mb-2 text-sm font-semibold text-x-brown">{t(`shift_pattern_groups.slot.${slotType}`)}</p>
              <div className="flex items-center gap-3">
                <Controller
                  control={control}
                  name={`slots.${slotType}.start_time`}
                  render={({ field }) => <TimeSelect value={field.value} onChange={field.onChange} />}
                />
                <span className="text-sm text-slate-400">–</span>
                <Controller
                  control={control}
                  name={`slots.${slotType}.end_time`}
                  render={({ field }) => <TimeSelect value={field.value} onChange={field.onChange} />}
                />
              </div>
              {(errors.slots?.[slotType]?.start_time || errors.slots?.[slotType]?.end_time) && (
                <p className="mt-1 text-sm text-red-600">{t('shift_pattern_groups.invalid_time')}</p>
              )}
            </div>
          ))}
        </div>

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
