import { apiClient } from '../../api/client';
import type { EmployeeSchedule } from '../../types/employee-schedule';

/**
 * @param weeks  How many consecutive weeks (1-6) to return starting at weekStartDate, in one
 *               call - the month-calendar view needs a whole grid's worth at once. Omit for
 *               the original single-week behavior.
 */
export async function fetchMySchedule(weekStartDate: string, weeks?: number): Promise<EmployeeSchedule> {
  const { data } = await apiClient.get<EmployeeSchedule>('/me/schedule', {
    params: { week_start_date: weekStartDate, weeks },
  });
  return data;
}
