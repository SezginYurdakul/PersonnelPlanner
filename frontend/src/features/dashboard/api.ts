import { apiClient } from '../../api/client';
import type { DashboardSummary, PlanningCalendarWeek } from '../../types/dashboard';

interface ApiEnvelope<T> {
  data: T;
}

export async function fetchDashboardSummary(): Promise<DashboardSummary> {
  const { data } = await apiClient.get<ApiEnvelope<DashboardSummary>>('/dashboard/summary');
  return data.data;
}

export async function fetchPlanningCalendar(weeksBefore = 4, weeksAfter = 4): Promise<PlanningCalendarWeek[]> {
  const { data } = await apiClient.get<ApiEnvelope<PlanningCalendarWeek[]>>('/dashboard/planning-calendar', {
    params: { weeks_before: weeksBefore, weeks_after: weeksAfter },
  });
  return data.data;
}
