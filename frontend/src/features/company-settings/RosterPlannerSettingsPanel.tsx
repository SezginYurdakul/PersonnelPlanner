import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Button } from '../../components/ui/Button';
import { Card } from '../../components/ui/Card';
import { updatePlannerSettings } from './api';
import type { CompanySettings, PlannerSettingsFormValues } from '../../types/company-settings';

const weight = z.coerce.number().int().min(0).max(10);

const schema = z.object({
  planner_weekend_policy: z.enum(['fair', 'single_staffed']),
  planner_night_weight: weight,
  planner_sunday_weight: weight,
  planner_saturday_weight: weight,
  planner_rotation_break_weight: weight,
  planner_rotation_enabled: z.boolean(),
  planner_max_leads_per_shift: z.coerce.number().int().min(1).max(5),
  planner_horizon_weeks: z.coerce.number().int().min(1).max(12),
});

type FormInput = z.input<typeof schema>;
type FormValues = z.output<typeof schema>;

function valuesFrom(settings: CompanySettings): PlannerSettingsFormValues {
  return {
    planner_weekend_policy: settings.planner_weekend_policy,
    planner_night_weight: settings.planner_night_weight,
    planner_sunday_weight: settings.planner_sunday_weight,
    planner_saturday_weight: settings.planner_saturday_weight,
    planner_rotation_break_weight: settings.planner_rotation_break_weight,
    planner_rotation_enabled: settings.planner_rotation_enabled,
    planner_max_leads_per_shift: settings.planner_max_leads_per_shift,
    planner_horizon_weeks: settings.planner_horizon_weeks,
  };
}

const WEIGHTS: { name: keyof PlannerSettingsFormValues; label: string }[] = [
  { name: 'planner_night_weight', label: 'Nights' },
  { name: 'planner_sunday_weight', label: 'Sundays' },
  { name: 'planner_saturday_weight', label: 'Saturdays' },
  { name: 'planner_rotation_break_weight', label: 'Rotation breaks' },
];

export function RosterPlannerSettingsPanel({ settings }: { settings: CompanySettings }) {
  const queryClient = useQueryClient();

  const {
    register,
    handleSubmit,
    reset,
    formState: { isSubmitting },
  } = useForm<FormInput, unknown, FormValues>({
    resolver: zodResolver(schema),
    defaultValues: valuesFrom(settings),
  });

  useEffect(() => {
    reset(valuesFrom(settings));
  }, [settings, reset]);

  const mutation = useMutation({
    mutationFn: (values: FormValues) => updatePlannerSettings(values),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['company-settings'] }),
  });

  return (
    <Card className="max-w-lg">
      <h2 className="mb-1 text-base font-extrabold text-x-brown">Roster Planner</h2>
      <p className="mb-4 text-xs text-slate-500">
        Super admin only. How the roster planner - the solver that plans shift leads and station operators when you
        generate a schedule - weighs its goals. Covering every shift it can, one shift type per person per week and
        filling contract hours always come first; these settings shape everything after that.
      </p>

      <form onSubmit={handleSubmit((values) => mutation.mutate(values))}>
        <p className="mb-1 text-xs font-bold uppercase tracking-wide text-slate-500">Fairness weights</p>
        <p className="mb-2 text-xs text-slate-500">
          How much an uneven share counts, per shift of difference between the person with the most and the one with
          the least (rotation breaks: per break). 0 ignores it. With Nights 1 and Sundays 3, one Sunday of difference
          counts like three nights. Leads are compared with leads, operators with operators, in proportion to their
          contract hours.
        </p>
        <div className="mb-4 grid grid-cols-4 gap-3">
          {WEIGHTS.map(({ name, label }) => (
            <label key={name} className="text-xs font-semibold text-slate-600">
              {label}
              <input
                type="number"
                step="1"
                min={0}
                max={10}
                className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
                {...register(name)}
              />
            </label>
          ))}
        </div>

        <div className="mb-4 border-t border-slate-200 pt-4">
          <label className="text-xs font-semibold text-slate-600">
            Weekend policy
            <select
              className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
              {...register('planner_weekend_policy')}
            >
              <option value="fair">Fair - everyone gets an even share of weekends</option>
              <option value="single_staffed">Single-staffed - as few people on weekend shifts as possible</option>
            </select>
          </label>
          <p className="mt-1 text-xs text-slate-500">
            Fair evens out Sundays and Saturdays first, even if some weekend shifts then get a second person.
            Single-staffed keeps weekend shifts at their minimum first; whoever availability forces into weekends (for
            example someone who never works Mondays) then ends up with more of them.
          </p>
        </div>

        <div className="mb-4 border-t border-slate-200 pt-4">
          <label className="flex items-start gap-2 text-xs font-semibold text-slate-600">
            <input type="checkbox" className="mt-0.5" {...register('planner_rotation_enabled')} />
            <span>
              Weekly rotation
              <span className="mt-0.5 block font-normal text-slate-500">
                Next week&apos;s shift type follows this week&apos;s: day, then afternoon, then night, then day again.
                Someone who can only work one type is never rotated.
              </span>
            </span>
          </label>
        </div>

        <div className="mb-4 grid grid-cols-2 gap-3 border-t border-slate-200 pt-4">
          <label className="text-xs font-semibold text-slate-600">
            Most leads per shift
            <input
              type="number"
              step="1"
              min={1}
              max={5}
              className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
              {...register('planner_max_leads_per_shift')}
            />
          </label>
          <label className="text-xs font-semibold text-slate-600">
            Weeks to look ahead
            <input
              type="number"
              step="1"
              min={1}
              max={12}
              className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
              {...register('planner_horizon_weeks')}
            />
          </label>
        </div>
        <p className="mb-4 text-xs text-slate-500">
          Each generate plans this many weeks and keeps only the first, so this week&apos;s choices stay fair for the
          weeks after it. More weeks plan further ahead but make generating slower (8 weeks takes about two minutes).
        </p>

        <Button type="submit" disabled={isSubmitting || mutation.isPending}>
          Save
        </Button>
        {mutation.isError && <p className="mt-2 text-xs text-red-600">Could not save the planner settings.</p>}
      </form>
    </Card>
  );
}
