import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useTranslation } from 'react-i18next';
import { useQuery } from '@tanstack/react-query';
import { Button } from '../../components/ui/Button';
import { fetchLines, fetchRoles } from './api';
import type { SchedulingRole } from '../../types/lines';

const schema = z
  .object({
    name: z.string().min(1),
    line_id: z.coerce.number().optional(),
    role_kind: z.enum(['station', 'secondary_task']),
    requires_coverage: z.boolean().optional(),
    attachment_type: z.enum(['station', 'line', 'none']).optional(),
    attached_station_role_id: z.coerce.number().optional(),
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
      requires_coverage: role?.requires_coverage ?? true,
      attachment_type: role?.attachment_type ?? undefined,
      attached_station_role_id: role?.attached_station?.id,
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
    // The backend rejects requires_coverage for secondary tasks and
    // attachment_type/attached_station_role_id for stations (prohibited_if rules) - only
    // send the fields that apply to the selected role_kind.
    const payload =
      values.role_kind === 'station'
        ? { ...values, attachment_type: undefined, attached_station_role_id: undefined }
        : { ...values, requires_coverage: undefined };

    await onSubmit(payload);
    onClose();
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

        {roleKind === 'station' && (
          <label className="mb-3 flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" {...register('requires_coverage')} />
            {t('roles.requires_coverage')}
          </label>
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
            </select>
            {errors.attachment_type && (
              <p className="mb-2 text-sm text-red-600">{errors.attachment_type.message}</p>
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
