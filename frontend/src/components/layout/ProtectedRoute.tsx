import type { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../../features/auth/AuthContext';

interface ProtectedRouteProps {
  children: ReactNode;
  /**
   * Restricts this route to accounts holding the `admin` or `super_admin` role. A
   * `user`-only account (neither role) is redirected to its own `/me` surface instead of
   * rendering the admin page - the admin panel and its API calls are never reachable by a
   * plain employee account (ProjectPlan.md §8b.4). `super_admin` is a superset of `admin`
   * (full admin access, plus the sole authority to approve/reject/unlock a schedule).
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

  if (adminOnly && !user.roles.includes('admin') && !user.roles.includes('super_admin')) {
    return <Navigate to="/me/dashboard" replace />;
  }

  return <>{children}</>;
}
