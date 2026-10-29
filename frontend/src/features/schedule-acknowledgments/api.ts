import { apiClient } from '../../api/client';
import type { PendingAcknowledgment, ScheduleAcknowledgmentStatus } from '../../types/schedule-acknowledgment';

interface ApiEnvelope<T> {
  data: T;
}

export async function fetchMyPendingAcknowledgments(): Promise<PendingAcknowledgment[]> {
  const { data } = await apiClient.get<ApiEnvelope<PendingAcknowledgment[]>>('/pending-acknowledgments');
  return data.data;
}

export async function acknowledgeSchedule(scheduleId: number): Promise<void> {
  await apiClient.post(`/schedules/${scheduleId}/acknowledge`);
}

export async function fetchScheduleAcknowledgments(scheduleId: number): Promise<ScheduleAcknowledgmentStatus[]> {
  const { data } = await apiClient.get<ApiEnvelope<ScheduleAcknowledgmentStatus[]>>(
    `/schedules/${scheduleId}/acknowledgments`,
  );
  return data.data;
}
