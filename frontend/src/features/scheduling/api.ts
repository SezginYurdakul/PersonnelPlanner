import { apiClient } from '../../api/client';
import type {
  AlternativeCandidatesQuery,
  AssignmentMutationResult,
  Candidate,
  CreateAssignmentPayload,
  GenerateSuggestionPayload,
  MoveAssignmentPayload,
  Schedule,
  SuggestionResult,
  UpdateAssignmentPayload,
} from '../../types/scheduling';

interface ApiEnvelope<T> {
  data: T;
}

export async function fetchSchedules(): Promise<Schedule[]> {
  const { data } = await apiClient.get<ApiEnvelope<Schedule[]>>('/schedules');
  return data.data;
}

export async function fetchScheduleByWeek(weekStartDate: string): Promise<Schedule | null> {
  const schedules = await fetchSchedules();
  return schedules.find((s) => s.week_start_date === weekStartDate) ?? null;
}

export async function fetchSchedule(id: number): Promise<Schedule> {
  const { data } = await apiClient.get<ApiEnvelope<Schedule>>(`/schedules/${id}`);
  return data.data;
}

export async function generateSuggestion(payload: GenerateSuggestionPayload): Promise<SuggestionResult> {
  const { data } = await apiClient.post<SuggestionResult>('/schedules/suggest', payload);
  return data;
}

export async function approveSchedule(id: number): Promise<Schedule> {
  const { data } = await apiClient.post<ApiEnvelope<Schedule>>(`/schedules/${id}/approve`);
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
