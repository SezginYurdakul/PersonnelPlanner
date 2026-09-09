import { apiClient } from '../../api/client';
import type {
  CommitImportPayload,
  ImportPreview,
  ImportShape,
  ImportSummary,
  TimeClockEntry,
  TimeClockEntryFilters,
  TimeClockEntryFormValues,
} from '../../types/time-attendance';

interface ApiEnvelope<T> {
  data: T;
}

export async function previewImport(shape: ImportShape, file: File): Promise<ImportPreview> {
  const formData = new FormData();
  formData.append('shape', shape);
  formData.append('file', file);

  const { data } = await apiClient.post<ImportPreview>('/time-clock-imports/preview', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data;
}

export async function commitImport(payload: CommitImportPayload): Promise<ImportSummary> {
  const { data } = await apiClient.post<ApiEnvelope<ImportSummary>>('/time-clock-imports/commit', payload);
  return data.data;
}

export async function fetchTimeClockEntries(filters: TimeClockEntryFilters = {}): Promise<TimeClockEntry[]> {
  const { data } = await apiClient.get<ApiEnvelope<TimeClockEntry[]>>('/time-clock-entries', { params: filters });
  return data.data;
}

export async function createTimeClockEntry(payload: TimeClockEntryFormValues): Promise<TimeClockEntry> {
  const { data } = await apiClient.post<ApiEnvelope<TimeClockEntry>>('/time-clock-entries', payload);
  return data.data;
}

export async function updateTimeClockEntry(
  id: number,
  payload: TimeClockEntryFormValues,
): Promise<TimeClockEntry> {
  const { data } = await apiClient.put<ApiEnvelope<TimeClockEntry>>(`/time-clock-entries/${id}`, payload);
  return data.data;
}

export async function deleteTimeClockEntry(id: number): Promise<void> {
  await apiClient.delete(`/time-clock-entries/${id}`);
}
