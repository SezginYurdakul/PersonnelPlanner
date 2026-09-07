import type { Line, SchedulingRole, ShiftPattern } from './lines';

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
  start_time: string;
  end_time: string;
  crosses_midnight: boolean;
}

export interface AssignmentRole {
  id: number;
  name: string;
  role_kind: SchedulingRole['role_kind'];
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
  shift_pattern_ids: number[];
  role_ids: number[] | null;
}

export interface Schedule {
  id: number;
  week_start_date: string;
  status: ScheduleStatus;
  created_by: number;
  approved_by: number | null;
  approved_at: string | null;
  generated_scope: GeneratedScope | null;
  assignments: ShiftAssignment[];
}

export interface Candidate {
  employee: AssignmentEmployee;
  score: number | null;
  qualified: boolean;
  rule_compliant: boolean;
  exclusion_reason: string | null;
}

export interface UnfilledSlot {
  line: AssignmentLine;
  shift_pattern: AssignmentShiftPattern | null;
  work_date: string;
  role: AssignmentRole;
  blocking: boolean;
  alternatives: Candidate[];
}

export interface RuleResult {
  rule_key: string;
  status: 'pass' | 'violation';
  message: string | null;
}

export interface SuggestionResult {
  schedule: Schedule;
  unfilled_slots: UnfilledSlot[];
}

export interface ApprovalBlockedError {
  message: string;
  blocking_slots: UnfilledSlot[];
}

export interface GenerateSuggestionPayload {
  week_start_date: string;
  line_ids: number[];
  shift_pattern_ids: number[];
  role_ids?: number[] | null;
  ranking_mode: RankingMode;
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
}

export { type Line, type SchedulingRole, type ShiftPattern };
