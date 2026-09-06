export interface Line {
  id: number;
  name: string;
  code: string;
  is_active: boolean;
}

export type RoleKind = 'station' | 'secondary_task';
export type AttachmentType = 'station' | 'line' | 'none';

export interface SchedulingRole {
  id: number;
  name: string;
  line: Line | null;
  role_kind: RoleKind;
  requires_coverage: boolean;
  attachment_type: AttachmentType | null;
  attached_station: SchedulingRole | null;
  is_active: boolean;
}

export interface ShiftPattern {
  id: number;
  name: string;
  start_time: string;
  end_time: string;
  crosses_midnight: boolean;
  is_active: boolean;
}

export interface LineFormValues {
  name: string;
  code: string;
  is_active?: boolean;
}

export interface SchedulingRoleFormValues {
  name: string;
  line_id?: number | null;
  role_kind: RoleKind;
  requires_coverage?: boolean;
  attachment_type?: AttachmentType | null;
  attached_station_role_id?: number | null;
  is_active?: boolean;
}

export interface ShiftPatternFormValues {
  name: string;
  start_time: string;
  end_time: string;
  is_active?: boolean;
}

export interface PayRateSurchargeRule {
  id: number;
  name: string;
  days_of_week: number[];
  start_time: string;
  end_time: string;
  crosses_midnight: boolean;
  surcharge_percentage: string;
  is_active: boolean;
}

export interface PayRateSurchargeRuleFormValues {
  name: string;
  days_of_week: number[];
  start_time: string;
  end_time: string;
  surcharge_percentage: number;
  is_active?: boolean;
}
