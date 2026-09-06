import { apiClient } from '../../api/client';
import type { DashboardSummary } from '../../types/dashboard';

interface ApiEnvelope<T> {
  data: T;
}

export async function fetchDashboardSummary(): Promise<DashboardSummary> {
  const { data } = await apiClient.get<ApiEnvelope<DashboardSummary>>('/dashboard/summary');
  return data.data;
}
