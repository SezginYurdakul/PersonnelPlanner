import dayjs from 'dayjs';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { EmployeeAppLayout } from '../components/layout/EmployeeAppLayout';
import { Button } from '../components/ui/Button';
import { Card } from '../components/ui/Card';
import { useAuth } from '../features/auth/AuthContext';
import { fetchMyCompanySettings } from '../features/company-settings/api';
import { fetchMySchedule } from '../features/employee-schedule/api';
import { DayStatus, isOnLeave, shiftNoticeFor } from '../features/employee-schedule/DayStatus';
import { fetchMyProfile } from '../features/employee-profile/api';
import { fetchMyLeaveRequests } from '../features/leave/api';
import { acknowledgeSchedule, fetchMyPendingAcknowledgments } from '../features/schedule-acknowledgments/api';
import { NotificationPermissionPrompt } from '../features/notifications/NotificationPermissionPrompt';
import { getWeekStart } from '../lib/week';

/**
 * The employee self-service landing page (ProjectPlan.md §8f) - a single glance at "what do
 * I need to know/do today" before drilling into the full month calendar, leave form, or
 * shift-notice form via the tab bar below. Deliberately a dashboard of summaries and
 * shortcuts, never a second place to perform the same actions those dedicated tabs already
 * own in full.
 */
export function EmployeeDashboardPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { user } = useAuth();

  const { data: companySettings } = useQuery({
    queryKey: ['my-company-settings'],
    queryFn: fetchMyCompanySettings,
  });

  const { data: pendingAcknowledgments } = useQuery({
    queryKey: ['my-pending-acknowledgments'],
    queryFn: fetchMyPendingAcknowledgments,
  });

  const acknowledgeMutation = useMutation({
    mutationFn: acknowledgeSchedule,
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['my-pending-acknowledgments'] }),
  });

  const today = dayjs().format('YYYY-MM-DD');
  const todayWeekStart = companySettings ? getWeekStart(companySettings.week_start_day) : null;

  const { data: schedule } = useQuery({
    queryKey: ['my-schedule', todayWeekStart, 1],
    queryFn: () => fetchMySchedule(todayWeekStart as string),
    enabled: todayWeekStart !== null,
  });

  const { data: profile } = useQuery({
    queryKey: ['my-profile'],
    queryFn: fetchMyProfile,
  });

  const { data: leaveRequests } = useQuery({
    queryKey: ['my-leave-requests'],
    queryFn: fetchMyLeaveRequests,
  });

  const pendingLeaveRequests = (leaveRequests ?? []).filter((request) => request.status === 'pending');
  const upcomingApprovedLeave = (leaveRequests ?? [])
    .filter((request) => request.status === 'approved' && request.end_date >= today)
    .sort((a, b) => a.start_date.localeCompare(b.start_date));

  const todayAssignments = schedule?.assignments?.filter((a) => a.work_date === today) ?? [];
  const todayOnLeave = schedule ? isOnLeave(schedule.leave_days, today) : false;
  const todayNotice = schedule ? shiftNoticeFor(schedule.shift_notices, today) : undefined;

  return (
    <EmployeeAppLayout>
      <NotificationPermissionPrompt />

      <h1 className="mb-1 text-lg font-extrabold text-x-brown">
        {t('employee_dashboard.greeting', { name: user?.employee?.first_name ?? user?.name ?? '' })}
      </h1>
      <p className="mb-4 text-sm text-slate-500">{dayjs().format('dddd D MMMM YYYY')}</p>

      {(pendingAcknowledgments ?? []).map((pending) => (
        <Card key={pending.schedule_id} className="mb-4 border-amber-300 bg-amber-50">
          <h2 className="mb-1 text-sm font-extrabold text-amber-900">
            <i className="fa-solid fa-triangle-exclamation mr-1.5" />
            {t('employee_dashboard.schedule_corrected_title')}
          </h2>
          <p className="mb-3 text-sm text-amber-800">
            {t('employee_dashboard.schedule_corrected_body', {
              week: dayjs(pending.week_start_date).format('D MMM'),
              version: pending.version,
            })}
          </p>
          <Button
            className="w-full"
            disabled={acknowledgeMutation.isPending}
            onClick={() => acknowledgeMutation.mutate(pending.schedule_id)}
          >
            {t('employee_dashboard.acknowledge')}
          </Button>
        </Card>
      ))}

      <Card className="mb-4">
        <h2 className="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">
          {t('employee_dashboard.today')}
        </h2>
        <DayStatus assignments={todayAssignments} onLeave={todayOnLeave} notice={todayNotice} />
      </Card>

      <Card className="mb-4">
        <h2 className="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">
          {t('employee_dashboard.my_info')}
        </h2>
        {profile ? (
          <dl className="space-y-1.5 text-sm">
            <div className="flex justify-between">
              <dt className="text-slate-500">{t('employee_dashboard.name')}</dt>
              <dd className="font-semibold text-x-brown">
                {profile.first_name} {profile.last_name}
              </dd>
            </div>
            <div className="flex justify-between">
              <dt className="text-slate-500">{t('employee_dashboard.email')}</dt>
              <dd className="font-semibold text-x-brown">{profile.email}</dd>
            </div>
            {profile.default_line && (
              <div className="flex justify-between">
                <dt className="text-slate-500">{t('employee_dashboard.default_line')}</dt>
                <dd className="font-semibold text-x-brown">{profile.default_line.name}</dd>
              </div>
            )}
            {profile.employment_term && (
              <div className="flex justify-between">
                <dt className="text-slate-500">{t('employee_dashboard.contracted_hours')}</dt>
                <dd className="font-semibold text-x-brown">
                  {profile.employment_term.min_weekly_hours}–{profile.employment_term.max_weekly_hours}h / wk
                </dd>
              </div>
            )}
            {profile.is_shift_lead && (
              <div className="flex justify-between">
                <dt className="text-slate-500">{t('employee_dashboard.role')}</dt>
                <dd className="font-semibold text-x-brown">{t('employee_dashboard.shift_lead')}</dd>
              </div>
            )}
          </dl>
        ) : (
          <p className="text-sm text-slate-400">{t('common.loading')}</p>
        )}
      </Card>

      <Card className="mb-4">
        <div className="mb-2 flex items-center justify-between">
          <h2 className="text-xs font-bold uppercase tracking-wide text-slate-500">
            {t('employee_dashboard.leave')}
          </h2>
          {pendingLeaveRequests.length > 0 && (
            <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">
              {t('employee_dashboard.pending_count', { count: pendingLeaveRequests.length })}
            </span>
          )}
        </div>
        {upcomingApprovedLeave.length > 0 ? (
          <p className="mb-3 text-sm text-slate-600">
            <i className="fa-solid fa-umbrella-beach mr-1.5 text-emerald-600" />
            {t('employee_dashboard.next_leave', {
              start: dayjs(upcomingApprovedLeave[0].start_date).format('D MMM'),
              end: dayjs(upcomingApprovedLeave[0].end_date).format('D MMM'),
            })}
          </p>
        ) : (
          <p className="mb-3 text-sm text-slate-400">{t('employee_dashboard.no_upcoming_leave')}</p>
        )}
        <Button variant="secondary" className="w-full" onClick={() => navigate('/me/leave-request')}>
          <i className="fa-solid fa-umbrella-beach mr-1.5" />
          {t('employee_dashboard.request_leave')}
        </Button>
      </Card>

      <Card>
        <h2 className="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">
          {t('employee_dashboard.shift_notice')}
        </h2>
        <p className="mb-3 text-sm text-slate-400">{t('employee_dashboard.shift_notice_hint')}</p>
        <Button variant="secondary" className="w-full" onClick={() => navigate('/me/shift-notice')}>
          <i className="fa-solid fa-bell mr-1.5" />
          {t('employee_dashboard.report_sick_or_late')}
        </Button>
      </Card>
    </EmployeeAppLayout>
  );
}
