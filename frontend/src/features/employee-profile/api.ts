import { apiClient } from '../../api/client';
import type { EmployeeProfile } from '../../types/employee-profile';

export async function fetchMyProfile(): Promise<EmployeeProfile> {
  const { data } = await apiClient.get<EmployeeProfile>('/me/profile');
  return data;
}
