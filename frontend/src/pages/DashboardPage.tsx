import { useTranslation } from 'react-i18next';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import dayjs from 'dayjs';
import { AppLayout } from '../components/layout/AppLayout';
import { Card } from '../components/ui/Card';
import { PlanningCalendar } from '../features/dashboard/PlanningCalendar';
import { fetchDashboardSummary } from '../features/dashboard/api';
import { fetchLines } from '../features/lines/api';

interface KpiCardProps {
  label: string;
  value: number | string;
  hint: string;
  icon: string;
  badge?: { tone: 'success' | 'warning' | 'info'; text: string };
}

const badgeToneClasses: Record<'success' | 'warning' | 'info', string> = {
  success: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
  warning: 'bg-amber-100 text-amber-900 border border-amber-300',
  info: 'bg-blue-50 text-blue-800 border border-blue-200',
};

function KpiCard({ label, value, hint, icon, badge }: KpiCardProps) {
  return (
    <Card className="relative overflow-hidden">
      <div className="absolute -bottom-6 -right-6 h-24 w-24 rounded-full bg-amber-100/50 blur-xl" />
      <div className="flex items-center justify-between">
        <span className="text-xs font-bold uppercase tracking-wider text-slate-500">{label}</span>
        <div className="flex h-12 w-12 items-center justify-center rounded-xl border border-amber-200/50 bg-amber-50 text-xl font-bold text-x-brown">
          <i className={`fa-solid ${icon}`} />
        </div>
      </div>
      <div className="mt-4 flex items-baseline justify-between">
        <span className="text-3xl font-black tabular-nums text-x-brown">{value}</span>
        {badge && (
          <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold ${badgeToneClasses[badge.tone]}`}>
            {badge.text}
          </span>
        )}
      </div>
      <p className="mt-2 text-xs text-slate-500">{hint}</p>
    </Card>
  );
}

export function DashboardPage() {
  const { t } = useTranslation();

  const { data: summary } = useQuery({
    queryKey: ['dashboard-summary'],
    queryFn: fetchDashboardSummary,
  });

  const { data: lines } = useQuery({ queryKey: ['lines'], queryFn: fetchLines });

  const inactiveLines = lines?.filter((line) => !line.is_active) ?? [];
  const hasApprovedThisWeek = summary?.current_week_schedules.some((s) => s.status === 'approved') ?? false;

  const pendingItems = [
    ...(summary?.pending_leave_request_count
      ? [
          {
            key: 'leave',
            to: '/leave-requests',
            icon: 'fa-calendar-day',
            label: t('dashboard.pending.leave_requests', { count: summary.pending_leave_request_count }),
          },
        ]
      : []),
    ...(summary?.pending_shift_notice_count
      ? [
          {
            key: 'notices',
            to: '/shift-notices',
            icon: 'fa-bell',
            label: t('dashboard.pending.shift_notices', { count: summary.pending_shift_notice_count }),
          },
        ]
      : []),
    ...(summary?.pending_activation_count
      ? [
          {
            key: 'activation',
            to: '/staff',
            icon: 'fa-user-clock',
            label: t('dashboard.pending.activations', { count: summary.pending_activation_count }),
          },
        ]
      : []),
  ];

  return (
    <AppLayout>
      <div className="mb-6 flex items-center space-x-2">
        <span className="h-2.5 w-2.5 animate-pulse rounded-full bg-emerald-500" />
        <span className="text-xs font-bold uppercase tracking-wider text-amber-700">
          {t('dashboard.subtitle')}
        </span>
      </div>
      <h1 className="mb-6 text-xl font-extrabold tracking-tight text-x-brown">
        {t('dashboard.title')}
      </h1>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <Card>
            <PlanningCalendar />

            {summary && summary.unfilled_mandatory_station_count > 0 && (
              <p className="mt-4 border-t border-x-border pt-3 text-sm font-semibold text-rose-600">
                <i className="fa-solid fa-triangle-exclamation mr-1.5" />
                {t('dashboard.this_week.unfilled_stations', { count: summary.unfilled_mandatory_station_count })}
              </p>
            )}
            {summary && hasApprovedThisWeek && summary.unfilled_mandatory_station_count === 0 && (
              <p className="mt-4 border-t border-x-border pt-3 text-sm font-semibold text-emerald-600">
                <i className="fa-solid fa-circle-check mr-1.5" />
                {t('dashboard.this_week.approved')}
              </p>
            )}
          </Card>
        </div>

        <Card>
          <h2 className="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">
            {t('dashboard.pending.title')}
          </h2>
          {pendingItems.length === 0 ? (
            <p className="text-sm text-slate-400">{t('dashboard.pending.empty')}</p>
          ) : (
            <ul className="space-y-2">
              {pendingItems.map((item) => (
                <li key={item.key}>
                  <Link
                    to={item.to}
                    className="flex items-center gap-2.5 rounded-xl border border-x-border bg-x-warmbg px-3 py-2.5 text-sm font-semibold text-x-brown hover:border-x-gold"
                  >
                    <i className={`fa-solid ${item.icon} w-4 text-amber-600`} />
                    {item.label}
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </div>

      <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <KpiCard
          label={t('dashboard.kpi.active_staff')}
          value={summary?.active_employee_count ?? '—'}
          hint={
            summary
              ? t('dashboard.kpi.active_staff_breakdown', {
                  vast: summary.vast_employee_count,
                  uitzendkracht: summary.uitzendkracht_employee_count,
                })
              : ''
          }
          icon="fa-users"
          badge={{ tone: 'success', text: t('common.active') }}
        />
        <KpiCard
          label={t('dashboard.kpi.no_account')}
          value={summary?.unlinked_account_count ?? '—'}
          hint={t('dashboard.kpi.no_account_hint')}
          icon="fa-user-slash"
          badge={
            summary && summary.unlinked_account_count > 0
              ? { tone: 'warning', text: t('leave.status.pending') }
              : undefined
          }
        />
        <KpiCard
          label={t('dashboard.kpi.lines')}
          value={lines?.length ?? '—'}
          hint={t('dashboard.kpi.lines_hint')}
          icon="fa-industry"
          badge={{ tone: 'info', text: t('common.active') }}
        />
      </div>

      {summary && summary.on_leave_this_week.length > 0 && (
        <div className="mt-6">
          <Card>
            <h2 className="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">
              {t('dashboard.on_leave.title')}
            </h2>
            <ul className="space-y-2">
              {summary.on_leave_this_week.map((entry, index) => (
                <li key={index} className="flex items-center justify-between text-sm">
                  <span className="font-semibold text-x-brown">{entry.employee_name}</span>
                  <span className="text-slate-500">
                    {dayjs(entry.start_date).format('D MMM')} – {dayjs(entry.end_date).format('D MMM')}
                  </span>
                </li>
              ))}
            </ul>
          </Card>
        </div>
      )}

      {inactiveLines.length === 0 && lines && lines.length > 0 ? null : (
        <div className="mt-6">
          <Card>
            <h2 className="text-base font-extrabold text-x-brown">{t('dashboard.setup.title')}</h2>
            <p className="mb-4 text-xs text-slate-500">{t('dashboard.setup.subtitle')}</p>

            <div className="space-y-3">
              {lines?.map((line) => (
                <div
                  key={line.id}
                  className="flex items-center justify-between rounded-xl border border-x-border bg-x-cream p-4"
                >
                  <div className="flex items-center space-x-3">
                    <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-x-brown text-xs font-bold text-amber-300">
                      {line.code}
                    </span>
                    <h4 className="text-xs font-extrabold text-x-brown">{line.name}</h4>
                  </div>
                  <span
                    className={`rounded-lg border px-2.5 py-1 text-xs font-bold ${
                      line.is_active
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                        : 'border-slate-200 bg-slate-50 text-slate-500'
                    }`}
                  >
                    {line.is_active ? t('common.active') : t('common.inactive')}
                  </span>
                </div>
              ))}
              {lines?.length === 0 && <p className="text-sm text-slate-500">{t('lines.empty')}</p>}
            </div>
          </Card>
        </div>
      )}
    </AppLayout>
  );
}
