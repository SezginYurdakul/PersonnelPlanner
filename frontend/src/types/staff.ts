export type EmployeeType = 'vast' | 'uitzendkracht';
export type PayType = 'hourly' | 'monthly';

export interface Agency {
  id: number;
  name: string;
  code: string;
  contact_email: string | null;
  contact_phone: string | null;
  is_active: boolean;
}

export interface EmploymentTerm {
  id: number;
  max_weekly_hours: number;
  effective_from: string;
  effective_to: string | null;
}

export interface Employee {
  id: number;
  first_name: string;
  last_name: string;
  phone: string | null;
  email: string | null;
  employee_type: EmployeeType;
  agency: Agency | null;
  pay_type: PayType;
  hourly_rate: string | null;
  monthly_salary: string | null;
  contracted_hours_per_week: string | null;
  default_line_id: number | null;
  employment_terms: EmploymentTerm | null;
  has_account: boolean;
  user_id: number | null;
  is_active: boolean;
}

export interface AgencyFormValues {
  name: string;
  code: string;
  contact_email?: string;
  contact_phone?: string;
  is_active?: boolean;
}

export interface EmployeeFormValues {
  first_name: string;
  last_name: string;
  phone?: string;
  email?: string;
  employee_type: EmployeeType;
  agency_id?: number | null;
  pay_type: PayType;
  hourly_rate?: number | null;
  monthly_salary?: number | null;
  contracted_hours_per_week?: number | null;
  default_line_id?: number | null;
  is_active?: boolean;
}
