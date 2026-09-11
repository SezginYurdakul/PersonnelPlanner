import { apiClient } from '../../api/client';
import type { ShiftNotice, ShiftNoticeEligibility, StoreShiftNoticeFormValues } from '../../types/shift-notices';

interface ApiEnvelope<T> {
  data: T;
}

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
