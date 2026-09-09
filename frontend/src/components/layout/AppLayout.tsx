import { useState, type ReactNode } from 'react';
import { NavLink } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useAuth } from '../../features/auth/AuthContext';

const navItems = [
  { to: '/', labelKey: 'nav.dashboard', icon: 'fa-chart-pie' },
  { to: '/schedule', labelKey: 'nav.schedule', icon: 'fa-calendar-week' },
  { to: '/staff', labelKey: 'nav.staff', icon: 'fa-user-group' },
  { to: '/agencies', labelKey: 'nav.agencies', icon: 'fa-handshake' },
  { to: '/lines', labelKey: 'nav.lines', icon: 'fa-industry' },
  { to: '/roles', labelKey: 'nav.work_stations_tasks', icon: 'fa-network-wired' },
  { to: '/shift-patterns', labelKey: 'nav.shift_patterns', icon: 'fa-calendar-days' },
  { to: '/pay-rate-surcharge-rules', labelKey: 'nav.pay_rate_rules', icon: 'fa-file-invoice-dollar' },
  { to: '/leave-requests', labelKey: 'nav.leave_requests', icon: 'fa-calendar-check' },
  { to: '/time-attendance', labelKey: 'nav.time_attendance', icon: 'fa-clock' },
];

function BrandHeader() {
  return (
    <div className="relative flex h-20 items-center overflow-hidden border-b border-x-brown-mid bg-x-brown-dark px-6">
      <div className="absolute -bottom-4 -right-4 h-20 w-20 rounded-full bg-x-gold/10 blur-xl" />
      <div className="z-10 flex items-center space-x-3.5">
        <div className="flex h-10 w-10 items-center justify-center rounded-xl border border-amber-200/30 bg-gradient-to-br from-x-gold to-x-gold-dark text-xl font-extrabold text-x-brown shadow-lg shadow-x-gold/20">
          <i className="fa-solid fa-wheat-awn" />
        </div>
        <div>
          <span className="block text-base font-extrabold tracking-wider text-white">BAKKERIJ X</span>
          <span className="block text-[10px] font-semibold uppercase tracking-widest text-amber-300/80">
            Personeelsplanning
          </span>
        </div>
      </div>
    </div>
  );
}

function NavLinks({ onNavigate }: { onNavigate?: () => void }) {
  const { t } = useTranslation();

  return (
    <nav className="flex-1 space-y-1.5 overflow-y-auto px-4 py-6">
      {navItems.map((item) => (
        <NavLink
          key={item.to}
          to={item.to}
          end={item.to === '/'}
          onClick={onNavigate}
          className={({ isActive }) =>
            `group flex items-center rounded-xl px-4 py-3 text-sm font-medium transition-all ${
              isActive
                ? 'bg-x-gold font-semibold text-slate-900 shadow-md shadow-x-gold/20'
                : 'text-slate-300 hover:bg-x-brown-mid hover:text-white'
            }`
          }
        >
          {({ isActive }) => (
            <>
              <i
                className={`fa-solid ${item.icon} mr-3 w-5 ${
                  isActive ? 'text-x-brown' : 'text-amber-400/70 group-hover:text-amber-400'
                }`}
              />
              {t(item.labelKey)}
            </>
          )}
        </NavLink>
      ))}
    </nav>
  );
}

function UserFooter() {
  const { user, logout } = useAuth();
  const { t } = useTranslation();
  const initials = user?.name
    ?.split(' ')
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();

  return (
    <div className="border-t border-x-brown-mid bg-x-brown-dark p-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-full border border-amber-500/30 bg-x-brown-mid font-bold text-amber-300">
            {initials}
          </div>
          <div>
            <p className="text-sm font-semibold text-white">{user?.name}</p>
            <p className="text-xs text-amber-200/70">{user?.email}</p>
          </div>
        </div>
        <button
          type="button"
          onClick={() => void logout()}
          title={t('auth.logout')}
          className="rounded-lg p-2 text-slate-400 transition-colors hover:bg-x-brown-mid hover:text-rose-400"
        >
          <i className="fa-solid fa-right-from-bracket text-base" />
        </button>
      </div>
    </div>
  );
}

export function AppLayout({ children }: { children: ReactNode }) {
  const [isMobileOpen, setIsMobileOpen] = useState(false);

  return (
    <div className="flex h-screen bg-x-cream">
      {/* Desktop sidebar */}
      <aside className="fixed inset-y-0 hidden w-72 flex-col border-r border-x-brown-dark bg-x-brown text-white lg:flex">
        <BrandHeader />
        <NavLinks />
        <UserFooter />
      </aside>

      {/* Mobile sidebar drawer */}
      {isMobileOpen && (
        <div className="fixed inset-0 z-50 bg-x-brown/80 backdrop-blur-sm lg:hidden">
          <div className="fixed inset-y-0 left-0 flex w-72 flex-col border-r border-x-brown-mid bg-x-brown text-white shadow-2xl">
            <div className="flex h-20 items-center justify-between border-b border-x-brown-mid bg-x-brown-dark px-6">
              <div className="flex items-center space-x-3">
                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-x-gold text-lg font-bold text-x-brown">
                  <i className="fa-solid fa-wheat-awn" />
                </div>
                <span className="text-base font-extrabold text-white">BAKKERIJ X</span>
              </div>
              <button
                type="button"
                onClick={() => setIsMobileOpen(false)}
                className="p-2 text-amber-200 hover:text-white"
              >
                <i className="fa-solid fa-xmark text-xl" />
              </button>
            </div>
            <NavLinks onNavigate={() => setIsMobileOpen(false)} />
            <UserFooter />
          </div>
        </div>
      )}

      {/* Main content */}
      <div className="flex min-w-0 flex-1 flex-col lg:pl-72">
        <header className="sticky top-0 z-20 flex h-16 items-center border-b border-x-border bg-white px-4 shadow-sm sm:px-6 lg:hidden">
          <button
            type="button"
            onClick={() => setIsMobileOpen(true)}
            className="rounded-lg p-2 text-x-brown hover:bg-x-warmbg"
          >
            <i className="fa-solid fa-bars text-xl" />
          </button>
          <span className="ml-3 text-sm font-extrabold text-x-brown">BAKKERIJ X</span>
        </header>
        <main className="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">{children}</main>
      </div>
    </div>
  );
}
