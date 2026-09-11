import type { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../../features/auth/AuthContext';

interface ProtectedRouteProps {
  children: ReactNode;
  /**
   * Restricts this route to accounts holding the `admin` role. A `user`-only account
   * (no `admin` role) is redirected to its own `/me` surface instead of rendering the
   * admin page - the admin panel and its API calls are never reachable by a plain
   * employee account (ProjectPlan.md §8b.4).
   */
  adminOnly?: boolean;
}

export function ProtectedRoute({ children, adminOnly = false }: ProtectedRouteProps) {
  const { user, isLoading } = useAuth();
  const { t } = useTranslation();

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center text-slate-500">
        {t('common.loading')}
      </div>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  if (adminOnly && !user.roles.includes('admin')) {
    return <Navigate to="/me/schedule" replace />;
  }

  return <>{children}</>;
}
