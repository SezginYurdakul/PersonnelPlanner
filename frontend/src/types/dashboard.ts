export interface DashboardScheduleSummary {
  id: number;
  label: string | null;
  status: 'draft' | 'proposed' | 'approved';
}

export interface DashboardLeaveEntry {
  employee_name: string;
  start_date: string;
  end_date: string;
}

export interface DashboardShiftNoticeEntry {
  employee_name: string;
  type: 'sick' | 'late';
  work_date: string | null;
}

export interface PlanningCalendarSchedule {
  id: number;
  label: string | null;
  status: 'draft' | 'proposed' | 'approved';
}

export interface PlanningCalendarWeek {
  week_start_date: string;
  iso_week: number;
  schedules: PlanningCalendarSchedule[];
}

export interface DashboardSummary {
  active_employee_count: number;
  vast_employee_count: number;
  uitzendkracht_employee_count: number;
  unlinked_account_count: number;
  pending_activation_count: number;
  pending_leave_request_count: number;
  pending_shift_notice_count: number;
  unfilled_mandatory_station_count: number;
  current_week_schedules: DashboardScheduleSummary[];
  on_leave_this_week: DashboardLeaveEntry[];
  pending_shift_notices: DashboardShiftNoticeEntry[];
}
