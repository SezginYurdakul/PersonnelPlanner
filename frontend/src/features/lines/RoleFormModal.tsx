import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useTranslation } from 'react-i18next';
import { useQuery } from '@tanstack/react-query';
import { isAxiosError } from 'axios';
import { Button } from '../../components/ui/Button';
import { fetchLines, fetchRoles } from './api';
import type { SchedulingRole } from '../../types/lines';

const optionalId = z.preprocess((v) => (v === '' ? undefined : v), z.coerce.number().optional());
const optionalAttachmentType = z.preprocess(
  (v) => (v === '' ? undefined : v),
  z.enum(['station', 'line', 'none', 'shift_lead', 'flexible']).optional(),
);

const schema = z
  .object({
    name: z.string().min(1),
    line_id: optionalId,
    role_kind: z.enum(['station', 'secondary_task']),
    requires_coverage: z.boolean().optional(),
    attachment_type: optionalAttachmentType,
    attached_station_role_id: optionalId,
    allows_second_person: z.boolean().optional(),
    min_headcount: z.coerce.number().int().min(1).max(20).optional(),
    fairness_weight: z.coerce.number().min(0.1).max(9.99).optional(),
  })
  .refine((v) => v.role_kind !== 'secondary_task' || v.attachment_type, {
    message: 'Attachment is required for secondary tasks',
    path: ['attachment_type'],
  })
  .refine((v) => v.attachment_type !== 'station' || v.attached_station_role_id, {
    message: 'Station is required',
    path: ['attached_station_role_id'],
  });

type FormInput = z.input<typeof schema>;
type FormValues = z.output<typeof schema>;

export function RoleFormModal({
  role,
  onSubmit,
  onClose,
}: {
  role: SchedulingRole | null;
  onSubmit: (values: FormValues) => Promise<void>;
  onClose: () => void;
}) {
  const { t } = useTranslation();
  const { data: lines } = useQuery({ queryKey: ['lines'], queryFn: fetchLines });
  const [submitError, setSubmitError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<FormInput, unknown, FormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: role?.name ?? '',
      line_id: role?.line?.id,
      role_kind: role?.role_kind ?? 'station',
      requires_coverage: role?.requires_coverage ?? (role?.role_kind ?? 'station') === 'station',
      attachment_type: role?.attachment_type ?? undefined,
      attached_station_role_id: role?.attached_station?.id,
      allows_second_person: role?.allows_second_person ?? false,
      min_headcount: role?.min_headcount ?? 1,
      fairness_weight: role?.fairness_weight ?? 1,
    },
  });

  const roleKind = watch('role_kind');
  const attachmentType = watch('attachment_type');
  const selectedLineId = watch('line_id') as number | undefined;

  const { data: stationsOnLine } = useQuery({
    queryKey: ['roles', { line_id: selectedLineId, role_kind: 'station' }],
    queryFn: () => fetchRoles({ line_id: selectedLineId, role_kind: 'station' }),
    enabled: attachmentType === 'station',
  });

  async function submit(values: FormValues) {
    // The backend rejects attachment_type/attached_station_role_id for stations
    // (prohibited_if rule) - only send those fields for secondary tasks. requires_coverage
    // is meaningful for BOTH kinds (a mandatory secondary task, e.g. a fault-response duty,
    // blocks approval the same way an unfilled mandatory station does), so it's sent either way.
    const payload =
      values.role_kind === 'station'
        ? { ...values, attachment_type: undefined, attached_station_role_id: undefined }
        : { ...values, allows_second_person: undefined, min_headcount: undefined };

    setSubmitError(null);
    try {
      await onSubmit(payload);
      onClose();
    } catch (error) {
      const message =
        isAxiosError<{ errors?: Record<string, string[]> }>(error) && error.response?.data.errors
          ? Object.values(error.response.data.errors).flat().join('\n')
          : t('roles.save_failed');
      setSubmitError(message);
    }
  }

  return (
    <div className="fixed inset-0 z-10 flex items-center justify-center bg-black/30 p-4">
      <form onSubmit={handleSubmit(submit)} className="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
        <h2 className="mb-4 text-lg font-semibold text-slate-900">
          {role ? t('roles.edit') : t('roles.new')}
        </h2>

        <label className="mb-1 block text-sm font-medium text-slate-700">{t('roles.name')}</label>
        <input className="mb-3 w-full rounded border border-slate-300 px-3 py-2 text-sm" {...register('name')} />
        {errors.name && <p className="mb-2 text-sm text-red-600">{errors.name.message}</p>}

        <label className="mb-1 block text-sm font-medium text-slate-700">{t('roles.line')}</label>
        <select className="mb-3 w-full rounded border border-slate-300 px-3 py-2 text-sm" {...register('line_id')}>
          <option value="">{t('roles.line_independent')}</option>
          {lines?.map((line) => (
            <option key={line.id} value={line.id}>
              {line.name}
            </option>
          ))}
        </select>

        <label className="mb-1 block text-sm font-medium text-slate-700">{t('roles.role_kind')}</label>
        <select className="mb-3 w-full rounded border border-slate-300 px-3 py-2 text-sm" {...register('role_kind')}>
          <option value="station">{t('roles.role_kind.station')}</option>
          <option value="secondary_task">{t('roles.role_kind.secondary_task')}</option>
        </select>

        <label className="mb-3 flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" {...register('requires_coverage')} />
          {t('roles.requires_coverage')}
        </label>
        <p className="-mt-2 mb-3 text-xs text-slate-400">
          {roleKind === 'station' ? t('roles.requires_coverage_hint_station') : t('roles.requires_coverage_hint_secondary')}
        </p>

        <label className="mb-1 block text-sm font-medium text-slate-700">{t('roles.fairness_weight')}</label>
        <input
          type="number"
          step="0.1"
          min={0.1}
          max={9.99}
          className="mb-1 w-24 rounded border border-slate-300 px-3 py-2 text-sm"
          {...register('fairness_weight')}
        />
        <p className="-mt-1 mb-3 text-xs text-slate-400">{t('roles.fairness_weight_hint')}</p>
        {errors.fairness_weight && <p className="mb-2 text-sm text-red-600">{errors.fairness_weight.message}</p>}

        {roleKind === 'station' && (
          <>
            <label className="mb-1 block text-sm font-medium text-slate-700">{t('roles.min_headcount')}</label>
            <input
              type="number"
              min={1}
              max={20}
              className="mb-1 w-24 rounded border border-slate-300 px-3 py-2 text-sm"
              {...register('min_headcount')}
            />
            <p className="-mt-1 mb-3 text-xs text-slate-400">{t('roles.min_headcount_hint')}</p>
            {errors.min_headcount && (
              <p className="mb-2 text-sm text-red-600">{errors.min_headcount.message}</p>
            )}

            <label className="mb-3 flex items-center gap-2 text-sm text-slate-700">
              <input type="checkbox" {...register('allows_second_person')} />
              {t('roles.allows_second_person')}
            </label>
            <p className="-mt-2 mb-3 text-xs text-slate-400">{t('roles.allows_second_person_hint')}</p>
          </>
        )}

        {roleKind === 'secondary_task' && (
          <>
            <label className="mb-1 block text-sm font-medium text-slate-700">
              {t('roles.attachment_type')}
            </label>
            <select
              className="mb-3 w-full rounded border border-slate-300 px-3 py-2 text-sm"
              {...register('attachment_type')}
            >
              <option value="">—</option>
              <option value="station">{t('roles.attachment_type.station')}</option>
              <option value="line">{t('roles.attachment_type.line')}</option>
              <option value="none">{t('roles.attachment_type.none')}</option>
              <option value="shift_lead">{t('roles.attachment_type.shift_lead')}</option>
              <option value="flexible">{t('roles.attachment_type.flexible')}</option>
            </select>
            {errors.attachment_type && (
              <p className="mb-2 text-sm text-red-600">{errors.attachment_type.message}</p>
            )}
            {attachmentType === 'shift_lead' && (
              <p className="-mt-2 mb-3 text-xs text-slate-400">{t('roles.attachment_type.shift_lead_hint')}</p>
            )}
            {attachmentType === 'flexible' && (
              <p className="-mt-2 mb-3 text-xs text-slate-400">{t('roles.attachment_type.flexible_hint')}</p>
            )}

            {attachmentType === 'station' && (
              <>
                <label className="mb-1 block text-sm font-medium text-slate-700">
                  {t('roles.attached_station')}
                </label>
                <select
                  className="mb-3 w-full rounded border border-slate-300 px-3 py-2 text-sm"
                  {...register('attached_station_role_id')}
                >
                  <option value="">—</option>
                  {stationsOnLine?.map((station) => (
                    <option key={station.id} value={station.id}>
                      {station.name}
                    </option>
                  ))}
                </select>
                {errors.attached_station_role_id && (
                  <p className="mb-2 text-sm text-red-600">{errors.attached_station_role_id.message}</p>
                )}
              </>
            )}
          </>
        )}

        {submitError && <p className="mb-2 whitespace-pre-line text-sm text-red-600">{submitError}</p>}

        <div className="mt-3 flex justify-end gap-2">
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
