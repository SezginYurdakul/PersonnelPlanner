import type { Employee } from './staff';

export type LeaveStatus = 'pending' | 'approved' | 'rejected';

export interface LeaveType {
  id: number;
  name: string;
  requires_approval: boolean;
}

export interface LeaveRequest {
  id: number;
  employee: Employee;
  leave_type: LeaveType;
  start_date: string;
  end_date: string;
  status: LeaveStatus;
  reason: string | null;
  approved_by: number | null;
}

export interface LeaveRequestFormValues {
  employee_id: number;
  leave_type_id: number;
  start_date: string;
  end_date: string;
  reason?: string;
}
