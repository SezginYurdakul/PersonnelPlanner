export type ShiftNoticeType = 'sick' | 'late';

export type ShiftNoticeStatus = 'submitted' | 'acknowledged';

export interface ShiftNoticeEligibility {
  can_submit: boolean;
  phone_number?: string | null;
}

export interface ShiftNotice {
  id: number;
  type: ShiftNoticeType;
  delay_minutes: number | null;
  note: string | null;
  status: ShiftNoticeStatus;
}

export interface StoreShiftNoticeFormValues {
  shift_assignment_id: number;
  type: ShiftNoticeType;
  delay_minutes?: number;
  note?: string;
}
