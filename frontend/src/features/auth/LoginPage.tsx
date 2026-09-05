import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useTranslation } from 'react-i18next';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from './AuthContext';

const loginSchema = z.object({
  email: z.string().email(),
  password: z.string().min(1),
});

type LoginFormValues = z.infer<typeof loginSchema>;

export function LoginPage() {
  const { t } = useTranslation();
  const { login } = useAuth();
  const navigate = useNavigate();
  const [serverError, setServerError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
  });

  async function onSubmit(values: LoginFormValues) {
    setServerError(null);
    try {
      await login(values);
      navigate('/', { replace: true });
    } catch {
      setServerError(t('auth.login.error'));
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-slate-50">
      <form
        onSubmit={handleSubmit(onSubmit)}
        className="w-full max-w-sm rounded-lg bg-white p-8 shadow"
      >
        <h1 className="mb-6 text-xl font-semibold text-slate-900">
          {t('auth.login.title')}
        </h1>

        <label className="mb-1 block text-sm font-medium text-slate-700" htmlFor="email">
          {t('auth.login.email')}
        </label>
        <input
          id="email"
          type="email"
          className="mb-3 w-full rounded border border-slate-300 px-3 py-2 text-sm"
          {...register('email')}
        />
        {errors.email && (
          <p className="mb-2 text-sm text-red-600">{errors.email.message}</p>
        )}

        <label className="mb-1 block text-sm font-medium text-slate-700" htmlFor="password">
          {t('auth.login.password')}
        </label>
        <input
          id="password"
          type="password"
          className="mb-4 w-full rounded border border-slate-300 px-3 py-2 text-sm"
          {...register('password')}
        />
        {errors.password && (
          <p className="mb-2 text-sm text-red-600">{errors.password.message}</p>
        )}

        {serverError && <p className="mb-4 text-sm text-red-600">{serverError}</p>}

        <button
          type="submit"
          disabled={isSubmitting}
          className="w-full rounded bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
        >
          {t('auth.login.submit')}
        </button>
      </form>
    </div>
  );
}
