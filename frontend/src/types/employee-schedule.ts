import type { VisibilityScope } from './auth';

export interface EmployeeScheduleAssignment {
  id: number;
  work_date: string;
  employee: {
    id: number;
    first_name: string | null;
    last_name: string | null;
  };
  line: { id: number; name: string } | null;
  role: { id: number; name: string } | null;
  starts_at: string | null;
  ends_at: string | null;
  crosses_midnight: boolean;
}

export interface EmployeeScheduleLeaveDay {
  start_date: string | null;
  end_date: string | null;
}

export interface EmployeeScheduleShiftNotice {
  work_date: string | null;
  type: 'sick' | 'late';
  delay_minutes: number | null;
  status: 'submitted' | 'acknowledged';
}

export interface EmployeeSchedule {
  week_start_date: string;
  visibility_scope: VisibilityScope;
  assignments: EmployeeScheduleAssignment[];
  leave_days: EmployeeScheduleLeaveDay[];
  shift_notices: EmployeeScheduleShiftNotice[];
}
