export type WeekStartDay = 'monday' | 'sunday';
export type PlannerWeekendPolicy = 'fair' | 'single_staffed';

export interface CompanySettings {
  annual_leave_min_notice_days: number;
  shift_notice_min_notice_hours: number;
  emergency_contact_phone: string | null;
  week_start_day: WeekStartDay;
  fairness_night_weight: number;
  fairness_afternoon_weight: number;
  fairness_day_weight: number;
  fairness_sunday_weight: number;
  fairness_saturday_weight: number;
  fairness_weekday_weight: number;
  role_rotation_enabled: boolean;
  planner_weekend_policy: PlannerWeekendPolicy;
  planner_night_weight: number;
  planner_sunday_weight: number;
  planner_saturday_weight: number;
  planner_rotation_break_weight: number;
  planner_rotation_enabled: boolean;
  planner_max_leads_per_shift: number;
  planner_horizon_weeks: number;
}

export interface CompanySettingsFormValues {
  annual_leave_min_notice_days: number;
  shift_notice_min_notice_hours: number;
  emergency_contact_phone?: string;
  week_start_day: WeekStartDay;
}

export interface FairnessWeightsFormValues {
  fairness_night_weight: number;
  fairness_afternoon_weight: number;
  fairness_day_weight: number;
  fairness_sunday_weight: number;
  fairness_saturday_weight: number;
  fairness_weekday_weight: number;
  role_rotation_enabled: boolean;
}

export interface PlannerSettingsFormValues {
  planner_weekend_policy: PlannerWeekendPolicy;
  planner_night_weight: number;
  planner_sunday_weight: number;
  planner_saturday_weight: number;
  planner_rotation_break_weight: number;
  planner_rotation_enabled: boolean;
  planner_max_leads_per_shift: number;
  planner_horizon_weeks: number;
}
