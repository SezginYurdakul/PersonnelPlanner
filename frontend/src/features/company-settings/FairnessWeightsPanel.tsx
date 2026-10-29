import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Button } from '../../components/ui/Button';
import { Card } from '../../components/ui/Card';
import { updateFairnessWeights } from './api';
import type { CompanySettings } from '../../types/company-settings';

const schema = z.object({
  fairness_night_weight: z.coerce.number().min(0).max(99.99),
  fairness_afternoon_weight: z.coerce.number().min(0).max(99.99),
  fairness_day_weight: z.coerce.number().min(0).max(99.99),
  fairness_sunday_weight: z.coerce.number().min(0).max(99.99),
  fairness_saturday_weight: z.coerce.number().min(0).max(99.99),
  fairness_weekday_weight: z.coerce.number().min(0).max(99.99),
  role_rotation_enabled: z.boolean(),
});

type FormInput = z.input<typeof schema>;
type FormValues = z.output<typeof schema>;

export function FairnessWeightsPanel({ settings }: { settings: CompanySettings }) {
  const queryClient = useQueryClient();

  const {
    register,
    handleSubmit,
    reset,
    formState: { isSubmitting },
  } = useForm<FormInput, unknown, FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      fairness_night_weight: settings.fairness_night_weight,
      fairness_afternoon_weight: settings.fairness_afternoon_weight,
      fairness_day_weight: settings.fairness_day_weight,
      fairness_sunday_weight: settings.fairness_sunday_weight,
      fairness_saturday_weight: settings.fairness_saturday_weight,
      fairness_weekday_weight: settings.fairness_weekday_weight,
      role_rotation_enabled: settings.role_rotation_enabled,
    },
  });

  useEffect(() => {
    reset({
      fairness_night_weight: settings.fairness_night_weight,
      fairness_afternoon_weight: settings.fairness_afternoon_weight,
      fairness_day_weight: settings.fairness_day_weight,
      fairness_sunday_weight: settings.fairness_sunday_weight,
      fairness_saturday_weight: settings.fairness_saturday_weight,
      fairness_weekday_weight: settings.fairness_weekday_weight,
      role_rotation_enabled: settings.role_rotation_enabled,
    });
  }, [settings, reset]);

  const mutation = useMutation({
    mutationFn: (values: FormValues) => updateFairnessWeights(values),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['company-settings'] }),
  });

  return (
    <Card className="max-w-lg">
      <h2 className="mb-1 text-base font-extrabold text-x-brown">Fairness Scoring Weights</h2>
      <p className="mb-4 text-xs text-slate-500">
        Super admin only. Multiplies an assignment&apos;s hours when computing each employee&apos;s recent workload
        (the last 8 approved weeks) - a shift-type weight and a day-type weight are multiplied together, so a Sunday
        night shift is the heaviest combination. 1.00 means no extra weight. This workload orders the &quot;who else
        could take this&quot; list for an open slot and decides who gets a secondary task; the roster planner that
        generates the week has its own settings above.
      </p>

      <form onSubmit={handleSubmit((values) => mutation.mutate(values))}>
        <p className="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Shift type</p>
        <div className="mb-4 grid grid-cols-3 gap-3">
          <label className="text-xs font-semibold text-slate-600">
            Day
            <input
              type="number"
              step="0.01"
              min={0}
              className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
              {...register('fairness_day_weight')}
            />
          </label>
          <label className="text-xs font-semibold text-slate-600">
            Afternoon
            <input
              type="number"
              step="0.01"
              min={0}
              className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
              {...register('fairness_afternoon_weight')}
            />
          </label>
          <label className="text-xs font-semibold text-slate-600">
            Night
            <input
              type="number"
              step="0.01"
              min={0}
              className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
              {...register('fairness_night_weight')}
            />
          </label>
        </div>

        <p className="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Day type</p>
        <div className="mb-4 grid grid-cols-3 gap-3">
          <label className="text-xs font-semibold text-slate-600">
            Weekday
            <input
              type="number"
              step="0.01"
              min={0}
              className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
              {...register('fairness_weekday_weight')}
            />
          </label>
          <label className="text-xs font-semibold text-slate-600">
            Saturday
            <input
              type="number"
              step="0.01"
              min={0}
              className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
              {...register('fairness_saturday_weight')}
            />
          </label>
          <label className="text-xs font-semibold text-slate-600">
            Sunday
            <input
              type="number"
              step="0.01"
              min={0}
              className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
              {...register('fairness_sunday_weight')}
            />
          </label>
        </div>

        <div className="mb-4 border-t border-slate-200 pt-4">
          <label className="flex items-start gap-2 text-xs font-semibold text-slate-600">
            <input type="checkbox" className="mt-0.5" {...register('role_rotation_enabled')} />
            <span>
              Role rotation
              <span className="mt-0.5 block font-normal text-slate-500">
                When on, a secondary task (silo watch and the like) goes to whoever working that shift has done it
                least recently, before the workload above decides. Turn off if this factory wants one person to
                keep the same task.
              </span>
            </span>
          </label>
        </div>

        <Button type="submit" disabled={isSubmitting}>
          Save
        </Button>
      </form>
    </Card>
  );
}
