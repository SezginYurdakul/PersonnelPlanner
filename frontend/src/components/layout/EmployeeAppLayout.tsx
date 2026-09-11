import type { ReactNode } from 'react';
import { NavLink } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../../features/auth/AuthContext';

const tabItems = [
  { to: '/me/schedule', labelKey: 'employee_nav.schedule', icon: 'fa-calendar-week' },
  { to: '/me/leave-request', labelKey: 'employee_nav.leave_request', icon: 'fa-umbrella-beach' },
  { to: '/me/shift-notice', labelKey: 'employee_nav.shift_notice', icon: 'fa-bell' },
];

/**
 * The employee-facing shell (ProjectPlan.md §8f) - a bottom tab bar, not AppLayout's admin
 * sidebar. Kept as an entirely separate component (not a variant of AppLayout) since the
 * nav surface, information density, and target device (mobile-first PWA vs. desktop admin
 * panel) differ completely.
 */
export function EmployeeAppLayout({ children }: { children: ReactNode }) {
  const { user, logout } = useAuth();
  const { t } = useTranslation();

  return (
    <div className="flex min-h-screen flex-col bg-x-cream">
      <header className="flex h-16 items-center justify-between border-b border-x-brown-mid bg-x-brown-dark px-4 text-white">
        <div className="flex items-center space-x-2.5">
          <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-x-gold to-x-gold-dark text-sm font-extrabold text-x-brown">
            <i className="fa-solid fa-wheat-awn" />
          </div>
          <span className="text-sm font-extrabold tracking-wide">{user?.employee?.first_name ?? user?.name}</span>
        </div>
        <button
          type="button"
          onClick={() => void logout()}
          title={t('auth.logout')}
          className="rounded-lg p-2 text-slate-300 hover:bg-x-brown-mid hover:text-white"
        >
          <i className="fa-solid fa-right-from-bracket text-base" />
        </button>
      </header>

      <main className="flex-1 overflow-y-auto p-4 pb-24">{children}</main>

      <nav className="fixed inset-x-0 bottom-0 z-20 flex border-t border-x-border bg-white shadow-[0_-1px_6px_rgba(0,0,0,0.06)]">
        {tabItems.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            className={({ isActive }) =>
              `flex flex-1 flex-col items-center gap-1 py-3 text-xs font-medium ${
                isActive ? 'text-x-gold-dark' : 'text-slate-400'
              }`
            }
          >
            <i className={`fa-solid ${item.icon} text-lg`} />
            {t(item.labelKey)}
          </NavLink>
        ))}
      </nav>
    </div>
  );
}
