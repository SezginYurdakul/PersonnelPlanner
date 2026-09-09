export interface CompanySettings {
  annual_leave_min_notice_days: number;
  shift_notice_min_notice_hours: number;
  emergency_contact_phone: string | null;
}

export interface CompanySettingsFormValues {
  annual_leave_min_notice_days: number;
  shift_notice_min_notice_hours: number;
  emergency_contact_phone?: string;
}
