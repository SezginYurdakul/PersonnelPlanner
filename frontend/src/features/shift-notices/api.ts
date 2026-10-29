import { apiClient } from '../../api/client';
import type {
  ShiftNotice,
  ShiftNoticeEligibility,
  ShiftNoticeFilters,
  StoreShiftNoticeFormValues,
} from '../../types/shift-notices';

interface ApiEnvelope<T> {
  data: T;
}

// Admin

export async function fetchShiftNotices(filters: ShiftNoticeFilters = {}): Promise<ShiftNotice[]> {
  const { data } = await apiClient.get<ApiEnvelope<ShiftNotice[]>>('/shift-notices', { params: filters });
  return data.data;
}

export async function acknowledgeShiftNotice(id: number): Promise<ShiftNotice> {
  const { data } = await apiClient.post<ApiEnvelope<ShiftNotice>>(`/shift-notices/${id}/acknowledge`);
  return data.data;
}

// Self-service

export async function fetchShiftNoticeEligibility(shiftAssignmentId: number): Promise<ShiftNoticeEligibility> {
  const { data } = await apiClient.get<ShiftNoticeEligibility>('/me/shift-notices/eligibility', {
    params: { shift_assignment_id: shiftAssignmentId },
  });
  return data;
}

export async function createShiftNotice(payload: StoreShiftNoticeFormValues): Promise<ShiftNotice> {
  const { data } = await apiClient.post<ApiEnvelope<ShiftNotice>>('/me/shift-notices', payload);
  return data.data;
}
