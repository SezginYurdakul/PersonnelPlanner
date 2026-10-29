export interface EmployeeProfile {
  first_name: string;
  last_name: string;
  email: string;
  is_shift_lead: boolean;
  default_line: { id: number; name: string } | null;
  employment_term: {
    min_weekly_hours: number;
    max_weekly_hours: number;
    effective_from: string | null;
  } | null;
}
