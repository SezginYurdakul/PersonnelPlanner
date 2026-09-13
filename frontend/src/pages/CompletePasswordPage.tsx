import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router-dom';
import { InstallPrompt } from '../components/InstallPrompt';
import { completeInvitation } from '../features/auth/api';
import { SUPPORTED_LOCALES } from '../lib/i18n';

const schema = z
  .object({
    password: z.string().min(8),
    password_confirmation: z.string().min(8),
    locale: z.enum(SUPPORTED_LOCALES),
  })
  .refine((values) => values.password === values.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
  });

type FormValues = z.infer<typeof schema>;

const LOCALE_LABELS: Record<(typeof SUPPORTED_LOCALES)[number], string> = {
  en: 'English',
  tr: 'Türkçe',
  nl: 'Nederlands',
  es: 'Español',
  ro: 'Română',
  uk: 'Українська',
};

export function CompletePasswordPage() {
  const { t } = useTranslation();
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') ?? '';
  const [succeeded, setSucceeded] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
  });

  const mutation = useMutation({
    mutationFn: (values: FormValues) => completeInvitation({ token, ...values }),
    onSuccess: () => setSucceeded(true),
  });

  async function submit(values: FormValues) {
    await mutation.mutateAsync(values);
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-x-brown px-4">
      <div className="w-full max-w-sm rounded-2xl border border-x-brown-mid bg-white p-8 shadow-2xl">
        <div className="mb-6 flex items-center space-x-3">
          <div className="flex h-11 w-11 items-center justify-center rounded-xl border border-amber-200/30 bg-gradient-to-br from-x-gold to-x-gold-dark text-xl font-extrabold text-x-brown shadow-lg shadow-x-gold/20">
            <i className="fa-solid fa-wheat-awn" />
          </div>
          <h1 className="text-lg font-extrabold text-x-brown">{t('complete_invitation.title')}</h1>
        </div>

        {succeeded ? (
          <>
            <p className="text-sm text-slate-600">{t('complete_invitation.success')}</p>
            <InstallPrompt />
          </>
        ) : !token ? (
          <p className="text-sm text-red-600">{t('complete_invitation.invalid_token')}</p>
        ) : (
          <form onSubmit={handleSubmit(submit)}>
            <label className="mb-1 block text-sm font-semibold text-slate-700">
              {t('complete_invitation.password')}
            </label>
            <input
              type="password"
              className="mb-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
              {...register('password')}
            />
            {errors.password && <p className="mb-2 text-sm text-red-600">{errors.password.message}</p>}

            <label className="mb-1 mt-3 block text-sm font-semibold text-slate-700">
              {t('complete_invitation.password_confirmation')}
            </label>
            <input
              type="password"
              className="mb-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
              {...register('password_confirmation')}
            />
            {errors.password_confirmation && (
              <p className="mb-2 text-sm text-red-600">{errors.password_confirmation.message}</p>
            )}

            <label className="mb-1 mt-3 block text-sm font-semibold text-slate-700">
              {t('complete_invitation.language')}
            </label>
            <select
              className="mb-1 w-full rounded border border-slate-300 px-3 py-2 text-sm"
              defaultValue=""
              {...register('locale')}
            >
              <option value="" disabled>
                {t('complete_invitation.language_placeholder')}
              </option>
              {SUPPORTED_LOCALES.map((locale) => (
                <option key={locale} value={locale}>
                  {LOCALE_LABELS[locale]}
                </option>
              ))}
            </select>
            {errors.locale && <p className="mb-2 text-sm text-red-600">{t('complete_invitation.language_placeholder')}</p>}

            {mutation.isError && (
              <p className="mt-2 text-sm text-red-600">{t('complete_invitation.invalid_token')}</p>
            )}

            <button
              type="submit"
              disabled={isSubmitting}
              className="mt-4 w-full rounded-xl bg-x-gold px-4 py-2.5 text-sm font-bold text-x-brown shadow-md shadow-x-gold/30 transition-colors hover:bg-x-gold-dark disabled:opacity-50"
            >
              {t('complete_invitation.submit')}
            </button>
          </form>
        )}
      </div>
    </div>
  );
}
