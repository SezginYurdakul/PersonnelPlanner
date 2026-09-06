import { apiClient } from '../../api/client';
import type {
  Line,
  LineFormValues,
  PayRateSurchargeRule,
  PayRateSurchargeRuleFormValues,
  SchedulingRole,
  SchedulingRoleFormValues,
  ShiftPattern,
  ShiftPatternFormValues,
} from '../../types/lines';

interface ApiEnvelope<T> {
  data: T;
}

// Lines

export async function fetchLines(): Promise<Line[]> {
  const { data } = await apiClient.get<ApiEnvelope<Line[]>>('/lines');
  return data.data;
}

export async function createLine(payload: LineFormValues): Promise<Line> {
  const { data } = await apiClient.post<ApiEnvelope<Line>>('/lines', payload);
  return data.data;
}

export async function updateLine(id: number, payload: LineFormValues): Promise<Line> {
  const { data } = await apiClient.put<ApiEnvelope<Line>>(`/lines/${id}`, payload);
  return data.data;
}

export async function deactivateLine(id: number): Promise<Line> {
  const { data } = await apiClient.delete<ApiEnvelope<Line>>(`/lines/${id}`);
  return data.data;
}

// Scheduling roles

export interface RoleFilters {
  line_id?: number;
  role_kind?: string;
  is_active?: boolean;
}

export async function fetchRoles(filters: RoleFilters = {}): Promise<SchedulingRole[]> {
  const { data } = await apiClient.get<ApiEnvelope<SchedulingRole[]>>('/roles', { params: filters });
  return data.data;
}

export async function createRole(payload: SchedulingRoleFormValues): Promise<SchedulingRole> {
  const { data } = await apiClient.post<ApiEnvelope<SchedulingRole>>('/roles', payload);
  return data.data;
}

export async function updateRole(id: number, payload: SchedulingRoleFormValues): Promise<SchedulingRole> {
  const { data } = await apiClient.put<ApiEnvelope<SchedulingRole>>(`/roles/${id}`, payload);
  return data.data;
}

export async function deactivateRole(id: number): Promise<SchedulingRole> {
  const { data } = await apiClient.delete<ApiEnvelope<SchedulingRole>>(`/roles/${id}`);
  return data.data;
}

export async function syncEmployeeQualifications(
  employeeId: number,
  roleIds: number[],
): Promise<SchedulingRole[]> {
  const { data } = await apiClient.put<ApiEnvelope<SchedulingRole[]>>(
    `/employees/${employeeId}/qualified-roles`,
    { role_ids: roleIds },
  );
  return data.data;
}

// Shift patterns

export async function fetchShiftPatterns(): Promise<ShiftPattern[]> {
  const { data } = await apiClient.get<ApiEnvelope<ShiftPattern[]>>('/shift-patterns');
  return data.data;
}

export async function createShiftPattern(payload: ShiftPatternFormValues): Promise<ShiftPattern> {
  const { data } = await apiClient.post<ApiEnvelope<ShiftPattern>>('/shift-patterns', payload);
  return data.data;
}

export async function updateShiftPattern(id: number, payload: ShiftPatternFormValues): Promise<ShiftPattern> {
  const { data } = await apiClient.put<ApiEnvelope<ShiftPattern>>(`/shift-patterns/${id}`, payload);
  return data.data;
}

export async function deactivateShiftPattern(id: number): Promise<ShiftPattern> {
  const { data } = await apiClient.delete<ApiEnvelope<ShiftPattern>>(`/shift-patterns/${id}`);
  return data.data;
}

// Pay rate surcharge rules

export async function fetchPayRateSurchargeRules(): Promise<PayRateSurchargeRule[]> {
  const { data } = await apiClient.get<ApiEnvelope<PayRateSurchargeRule[]>>('/pay-rate-surcharge-rules');
  return data.data;
}

export async function createPayRateSurchargeRule(
  payload: PayRateSurchargeRuleFormValues,
): Promise<PayRateSurchargeRule> {
  const { data } = await apiClient.post<ApiEnvelope<PayRateSurchargeRule>>('/pay-rate-surcharge-rules', payload);
  return data.data;
}

export async function updatePayRateSurchargeRule(
  id: number,
  payload: PayRateSurchargeRuleFormValues,
): Promise<PayRateSurchargeRule> {
  const { data } = await apiClient.put<ApiEnvelope<PayRateSurchargeRule>>(
    `/pay-rate-surcharge-rules/${id}`,
    payload,
  );
  return data.data;
}

export async function deactivatePayRateSurchargeRule(id: number): Promise<PayRateSurchargeRule> {
  const { data } = await apiClient.delete<ApiEnvelope<PayRateSurchargeRule>>(`/pay-rate-surcharge-rules/${id}`);
  return data.data;
}
