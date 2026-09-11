import { apiClient } from '../../api/client';
import type { EmployeeSchedule } from '../../types/employee-schedule';

export async function fetchMySchedule(weekStartDate: string): Promise<EmployeeSchedule> {
  const { data } = await apiClient.get<EmployeeSchedule>('/me/schedule', {
    params: { week_start_date: weekStartDate },
  });
  return data;
}
