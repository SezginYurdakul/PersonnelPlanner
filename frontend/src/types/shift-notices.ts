export type ShiftNoticeType = 'sick' | 'late';

export type ShiftNoticeStatus = 'submitted' | 'acknowledged';

export interface ShiftNoticeEligibility {
  can_submit: boolean;
  phone_number?: string | null;
}

export interface ShiftNotice {
  id: number;
  employee: {
    id: number;
    first_name: string;
    last_name: string;
  } | null;
  shift_assignment: {
    id: number;
    work_date: string;
    line: { id: number; name: string } | null;
    shift_pattern: { id: number; name: string } | null;
  } | null;
  type: ShiftNoticeType;
  delay_minutes: number | null;
  note: string | null;
  status: ShiftNoticeStatus;
  acknowledged_by: number | null;
  acknowledged_at: string | null;
}

export interface ShiftNoticeFilters {
  status?: ShiftNoticeStatus;
  type?: ShiftNoticeType;
  employee_id?: number;
}

export interface StoreShiftNoticeFormValues {
  shift_assignment_id: number;
  type: ShiftNoticeType;
  delay_minutes?: number;
  note?: string;
}
