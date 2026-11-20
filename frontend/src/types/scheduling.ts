import type { Line, SchedulingRole, ShiftPattern, SlotType } from './lines';

export type ScheduleStatus = 'draft' | 'proposed' | 'approved';
export type AssignmentStatus = 'proposed' | 'confirmed';
export type AssignmentSource = 'auto_suggested' | 'manual';
export type RankingMode = 'fair' | 'cost';

export interface AssignmentEmployee {
  id: number;
  first_name: string;
  last_name: string;
}

export interface AssignmentLine {
  id: number;
  name: string;
  code: string;
}

export interface AssignmentShiftPattern {
  id: number;
  name: string;
  slot_type: SlotType | null;
  start_time: string;
  end_time: string;
  crosses_midnight: boolean;
}

export interface AssignmentRole {
  id: number;
  name: string;
  role_kind: SchedulingRole['role_kind'];
  attachment_type: SchedulingRole['attachment_type'];
  // The ROLE's own line_id (null for Shift Lead/Vardiya Sefi or a company-wide secondary
  // task) - not this assignment's line_id, which is always a real, concrete line borrowed
  // purely to resolve hours. Used to group these under their own cross-line row.
  line_id: number | null;
}

export interface ShiftAssignment {
  id: number;
  schedule_id: number;
  employee: AssignmentEmployee;
  line: AssignmentLine;
  shift_pattern: AssignmentShiftPattern | null;
  work_date: string;
  role: AssignmentRole | null;
  starts_at: string | null;
  ends_at: string | null;
  status: AssignmentStatus;
  source: AssignmentSource;
  notes: string | null;
}

export interface GeneratedScope {
  line_ids: number[];
  slot_types: SlotType[];
  role_ids: number[] | null;
  line_slot_exclusions: LineSlotExclusion[] | null;
  // Missing on schedules generated before stations and tasks could be left out per shift.
  role_slot_exclusions?: RoleSlotExclusion[] | null;
}

export interface Schedule {
  id: number;
  week_start_date: string;
  label: string | null;
  note: string | null;
  status: ScheduleStatus;
  created_by: number;
  approved_by: number | null;
  approved_at: string | null;
  generated_scope: GeneratedScope | null;
  was_approved_before: boolean;
  version: number;
  rejection_note: string | null;
  unfilled_slots: UnfilledSlot[];
  assignments: ShiftAssignment[];
}

export interface Candidate {
  employee: AssignmentEmployee;
  score: number | null;
  qualified: boolean;
  rule_compliant: boolean;
  exclusion_reason: string | null;
  violates_employee_constraint: boolean;
  // Only from the alternative-candidates lookup: hours in this plan this week, and the
  // weekly hours of their contract.
  week_hours: number | null;
  contract_hours: { min: number | null; max: number } | null;
}

export interface UnfilledSlot {
  line: AssignmentLine | null;
  shift_pattern: AssignmentShiftPattern | null;
  work_date: string;
  role: AssignmentRole;
  blocking: boolean;
  missing_count: number;
  alternatives: Candidate[];
  reason: string | null;
}

export interface RuleResult {
  rule_key: string;
  status: 'pass' | 'violation';
  message: string | null;
}

export type SuggestionRunStatus = 'queued' | 'running' | 'completed' | 'failed';

// Generate runs on the queue (the planner takes minutes); the page polls the run until it
// completes. `schedule.unfilled_slots` is computed live server-side on every render - no
// separate top-level field to keep in sync.
export interface SuggestionRun {
  id: string;
  status: SuggestionRunStatus;
  message: string | null;
  schedule: Schedule | null;
}

export interface LeaveConflict {
  assignment_id: number;
  employee: AssignmentEmployee;
  role: { id: number; name: string } | null;
  line: { id: number; name: string } | null;
  work_date: string;
  reason: 'approved_leave' | 'sick_notice';
}

// A station or task left out on one shift; a null slot_type is the whole day (flexible hours).
export interface RoleSlotExclusion {
  role_id: number;
  day_of_week: number;
  slot_type: SlotType | null;
}

export interface LineSlotExclusion {
  line_id: number;
  day_of_week: number;
  slot_type: SlotType;
}

export interface GenerateSuggestionPayload {
  week_start_date: string;
  schedule_id?: number;
  line_ids: number[];
  slot_types: SlotType[];
  role_ids?: number[] | null;
  ranking_mode: RankingMode;
  line_slot_exclusions?: LineSlotExclusion[];
  role_slot_exclusions?: RoleSlotExclusion[];
}

export interface CreateSchedulePayload {
  week_start_date: string;
  label?: string;
  note?: string;
}

export interface UpdateSchedulePayload {
  label?: string;
  note?: string;
}

export interface CreateAssignmentPayload {
  schedule_id: number;
  employee_id: number;
  line_id: number;
  shift_pattern_id?: number | null;
  work_date: string;
  role_id?: number | null;
  starts_at?: string | null;
  ends_at?: string | null;
  notes?: string | null;
  confirm_override?: boolean;
}

export type UpdateAssignmentPayload = Omit<CreateAssignmentPayload, 'schedule_id'>;

export interface MoveAssignmentPayload {
  line_id: number;
  shift_pattern_id?: number | null;
  work_date: string;
  confirm_override?: boolean;
}

export interface AssignmentMutationResult {
  assignment: ShiftAssignment;
  rule_results: RuleResult[];
}

export interface AlternativeCandidatesQuery {
  line_id: number;
  shift_pattern_id?: number | null;
  work_date: string;
  role_id: number;
  ranking_mode: RankingMode;
  // Only meaningful for a flexible/day-specific-hours role (shift_pattern_id null) - the
  // real hours to rank candidates against, instead of the backend's arbitrary fallback
  // window (see AlternativeCandidateService::shiftTimeRange()).
  starts_at?: string | null;
  ends_at?: string | null;
}

export { type Line, type SchedulingRole, type ShiftPattern };
