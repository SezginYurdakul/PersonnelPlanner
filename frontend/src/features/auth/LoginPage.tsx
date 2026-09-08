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
    <div className="flex min-h-screen items-center justify-center bg-x-brown">
      <form
        onSubmit={handleSubmit(onSubmit)}
        className="w-full max-w-sm rounded-2xl border border-x-brown-mid bg-white p-8 shadow-2xl"
      >
        <div className="mb-6 flex items-center space-x-3">
          <div className="flex h-11 w-11 items-center justify-center rounded-xl border border-amber-200/30 bg-gradient-to-br from-x-gold to-x-gold-dark text-xl font-extrabold text-x-brown shadow-lg shadow-x-gold/20">
            <i className="fa-solid fa-wheat-awn" />
          </div>
          <div>
            <span className="block text-sm font-extrabold tracking-wider text-x-brown">
              BAKKERIJ X
            </span>
            <span className="block text-[10px] font-semibold uppercase tracking-widest text-amber-700/80">
              Personeelsplanning
            </span>
          </div>
        </div>

        <h1 className="mb-6 text-xl font-extrabold text-x-brown">{t('auth.login.title')}</h1>

        <label className="mb-1 block text-sm font-medium text-slate-700" htmlFor="email">
          {t('auth.login.email')}
        </label>
        <input
          id="email"
          type="email"
          className="mb-3 w-full rounded-xl border border-x-border bg-x-cream px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-x-gold"
          {...register('email')}
        />
        {errors.email && (
          <p className="mb-2 text-sm text-rose-600">{errors.email.message}</p>
        )}

        <label className="mb-1 block text-sm font-medium text-slate-700" htmlFor="password">
          {t('auth.login.password')}
        </label>
        <input
          id="password"
          type="password"
          className="mb-4 w-full rounded-xl border border-x-border bg-x-cream px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-x-gold"
          {...register('password')}
        />
        {errors.password && (
          <p className="mb-2 text-sm text-rose-600">{errors.password.message}</p>
        )}

        {serverError && <p className="mb-4 text-sm text-rose-600">{serverError}</p>}

        <button
          type="submit"
          disabled={isSubmitting}
          className="w-full rounded-xl bg-x-gold px-4 py-2.5 text-sm font-bold text-x-brown shadow-md shadow-x-gold/30 transition-colors hover:bg-x-gold-dark disabled:opacity-50"
        >
          {t('auth.login.submit')}
        </button>
      </form>
    </div>
  );
}
