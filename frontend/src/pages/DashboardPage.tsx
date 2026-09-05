import { useTranslation } from 'react-i18next';
import { useAuth } from '../features/auth/AuthContext';

export function DashboardPage() {
  const { t } = useTranslation();
  const { user, logout } = useAuth();

  return (
    <div className="min-h-screen bg-slate-50 p-8">
      <div className="mx-auto max-w-3xl rounded-lg bg-white p-6 shadow">
        <div className="mb-4 flex items-center justify-between">
          <h1 className="text-lg font-semibold text-slate-900">{t('app.name')}</h1>
          <button
            type="button"
            onClick={() => void logout()}
            className="text-sm font-medium text-slate-600 hover:text-slate-900"
          >
            {t('auth.logout')}
          </button>
        </div>
        <p className="text-sm text-slate-600">
          {user?.name} &middot; {user?.roles.join(', ')}
        </p>
      </div>
    </div>
  );
}
