import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useTranslation } from 'react-i18next';
import { Button } from '../../components/ui/Button';
import type { Agency } from '../../types/staff';

const schema = z.object({
  name: z.string().min(1),
  code: z.string().min(1),
  contact_email: z.string().email().optional().or(z.literal('')),
  contact_phone: z.string().optional(),
});

type FormValues = z.infer<typeof schema>;

export function AgencyFormModal({
  agency,
  onSubmit,
  onClose,
}: {
  agency: Agency | null;
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
      name: agency?.name ?? '',
      code: agency?.code ?? '',
      contact_email: agency?.contact_email ?? '',
      contact_phone: agency?.contact_phone ?? '',
    },
  });

  async function submit(values: FormValues) {
    await onSubmit(values);
    onClose();
  }

  return (
    <div className="fixed inset-0 z-10 flex items-center justify-center bg-black/30 p-4">
      <form
        onSubmit={handleSubmit(submit)}
        className="w-full max-w-md rounded-lg bg-white p-6 shadow-xl"
      >
        <h2 className="mb-4 text-lg font-semibold text-slate-900">
          {agency ? t('agencies.edit') : t('agencies.new')}
        </h2>

        <label className="mb-1 block text-sm font-medium text-slate-700">
          {t('agencies.name')}
        </label>
        <input className="mb-3 w-full rounded border border-slate-300 px-3 py-2 text-sm" {...register('name')} />
        {errors.name && <p className="mb-2 text-sm text-red-600">{errors.name.message}</p>}

        <label className="mb-1 block text-sm font-medium text-slate-700">
          {t('agencies.code')}
        </label>
        <input className="mb-3 w-full rounded border border-slate-300 px-3 py-2 text-sm" {...register('code')} />
        {errors.code && <p className="mb-2 text-sm text-red-600">{errors.code.message}</p>}

        <label className="mb-1 block text-sm font-medium text-slate-700">
          {t('agencies.contact_email')}
        </label>
        <input className="mb-3 w-full rounded border border-slate-300 px-3 py-2 text-sm" {...register('contact_email')} />
        {errors.contact_email && (
          <p className="mb-2 text-sm text-red-600">{errors.contact_email.message}</p>
        )}

        <label className="mb-1 block text-sm font-medium text-slate-700">
          {t('agencies.contact_phone')}
        </label>
        <input className="mb-4 w-full rounded border border-slate-300 px-3 py-2 text-sm" {...register('contact_phone')} />

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
