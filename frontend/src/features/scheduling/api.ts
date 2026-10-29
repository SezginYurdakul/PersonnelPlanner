import { apiClient } from '../../api/client';
import type {
  AlternativeCandidatesQuery,
  AssignmentMutationResult,
  Candidate,
  CreateAssignmentPayload,
  CreateSchedulePayload,
  GenerateSuggestionPayload,
  MoveAssignmentPayload,
  Schedule,
  SuggestionRun,
  UpdateAssignmentPayload,
  UpdateSchedulePayload,
} from '../../types/scheduling';

interface ApiEnvelope<T> {
  data: T;
}

export async function fetchSchedules(): Promise<Schedule[]> {
  const { data } = await apiClient.get<ApiEnvelope<Schedule[]>>('/schedules');
  return data.data;
}

export async function fetchSchedule(id: number): Promise<Schedule> {
  const { data } = await apiClient.get<ApiEnvelope<Schedule>>(`/schedules/${id}`);
  return data.data;
}

export async function createSchedule(payload: CreateSchedulePayload): Promise<Schedule> {
  const { data } = await apiClient.post<ApiEnvelope<Schedule>>('/schedules', payload);
  return data.data;
}

export async function updateSchedule(id: number, payload: UpdateSchedulePayload): Promise<Schedule> {
  const { data } = await apiClient.put<ApiEnvelope<Schedule>>(`/schedules/${id}`, payload);
  return data.data;
}

export async function deleteSchedule(id: number): Promise<void> {
  await apiClient.delete(`/schedules/${id}`);
}

export async function startSuggestionRun(payload: GenerateSuggestionPayload): Promise<SuggestionRun> {
  const { data } = await apiClient.post<{ run: SuggestionRun }>('/schedules/suggest', payload);
  return data.run;
}

export async function fetchSuggestionRun(runId: string): Promise<SuggestionRun> {
  const { data } = await apiClient.get<{ run: SuggestionRun }>(`/schedules/suggest/${runId}`);
  return data.run;
}

export async function submitScheduleForReview(id: number): Promise<Schedule> {
  const { data } = await apiClient.post<ApiEnvelope<Schedule>>(`/schedules/${id}/submit`);
  return data.data;
}

export async function approveSchedule(id: number): Promise<Schedule> {
  const { data } = await apiClient.post<ApiEnvelope<Schedule>>(`/schedules/${id}/approve`);
  return data.data;
}

export async function rejectSchedule(id: number, note: string): Promise<Schedule> {
  const { data } = await apiClient.post<ApiEnvelope<Schedule>>(`/schedules/${id}/reject`, { note });
  return data.data;
}

export async function unlockSchedule(id: number, note?: string): Promise<Schedule> {
  const { data } = await apiClient.post<ApiEnvelope<Schedule>>(`/schedules/${id}/unlock`, { note });
  return data.data;
}

export async function createAssignment(payload: CreateAssignmentPayload): Promise<AssignmentMutationResult> {
  const { data } = await apiClient.post<AssignmentMutationResult>('/shift-assignments', payload);
  return data;
}

export async function updateAssignment(
  id: number,
  payload: UpdateAssignmentPayload,
): Promise<AssignmentMutationResult> {
  const { data } = await apiClient.put<AssignmentMutationResult>(`/shift-assignments/${id}`, payload);
  return data;
}

export async function moveAssignment(
  id: number,
  payload: MoveAssignmentPayload,
): Promise<AssignmentMutationResult> {
  const { data } = await apiClient.patch<AssignmentMutationResult>(`/shift-assignments/${id}/move`, payload);
  return data;
}

export async function deleteAssignment(id: number): Promise<void> {
  await apiClient.delete(`/shift-assignments/${id}`);
}

export async function fetchAlternativeCandidates(
  scheduleId: number,
  query: AlternativeCandidatesQuery,
): Promise<Candidate[]> {
  const { data } = await apiClient.get<ApiEnvelope<Candidate[]>>(
    `/schedules/${scheduleId}/alternative-candidates`,
    { params: query },
  );
  return data.data;
}
