import { apiClient } from '../../api/client';
import type {
  Agency,
  AgencyFormValues,
  Employee,
  EmployeeFormValues,
  EmploymentTerm,
} from '../../types/staff';
import type { AuthUser } from '../../types/auth';

interface ApiEnvelope<T> {
  data: T;
}

interface PaginatedEnvelope<T> {
  data: T[];
}

// Agencies

export async function fetchAgencies(): Promise<Agency[]> {
  const { data } = await apiClient.get<ApiEnvelope<Agency[]>>('/agencies');
  return data.data;
}

export async function createAgency(payload: AgencyFormValues): Promise<Agency> {
  const { data } = await apiClient.post<ApiEnvelope<Agency>>('/agencies', payload);
  return data.data;
}

export async function updateAgency(id: number, payload: AgencyFormValues): Promise<Agency> {
  const { data } = await apiClient.put<ApiEnvelope<Agency>>(`/agencies/${id}`, payload);
  return data.data;
}

export async function deleteAgency(id: number): Promise<void> {
  await apiClient.delete(`/agencies/${id}`);
}

// Employees

export interface EmployeeFilters {
  employee_type?: string;
  agency_id?: number;
  is_active?: boolean;
  has_account?: boolean;
}

export async function fetchEmployees(filters: EmployeeFilters = {}): Promise<Employee[]> {
  const { data } = await apiClient.get<PaginatedEnvelope<Employee>>('/employees', {
    params: filters,
  });
  return data.data;
}

export async function fetchEmployee(id: number): Promise<Employee> {
  const { data } = await apiClient.get<ApiEnvelope<Employee>>(`/employees/${id}`);
  return data.data;
}

export async function createEmployee(payload: EmployeeFormValues): Promise<Employee> {
  const { data } = await apiClient.post<ApiEnvelope<Employee>>('/employees', payload);
  return data.data;
}

export async function updateEmployee(id: number, payload: EmployeeFormValues): Promise<Employee> {
  const { data } = await apiClient.put<ApiEnvelope<Employee>>(`/employees/${id}`, payload);
  return data.data;
}

export async function deactivateEmployee(id: number): Promise<Employee> {
  const { data } = await apiClient.delete<ApiEnvelope<Employee>>(`/employees/${id}`);
  return data.data;
}

export async function linkEmployeeUser(employeeId: number, userId: number): Promise<Employee> {
  const { data } = await apiClient.put<ApiEnvelope<Employee>>(`/employees/${employeeId}/user`, {
    user_id: userId,
  });
  return data.data;
}

export async function unlinkEmployeeUser(employeeId: number): Promise<Employee> {
  const { data } = await apiClient.delete<ApiEnvelope<Employee>>(`/employees/${employeeId}/user`);
  return data.data;
}

export async function updateEmploymentTerm(
  employeeId: number,
  payload: { max_weekly_hours: number; effective_from: string },
): Promise<EmploymentTerm> {
  const { data } = await apiClient.put<ApiEnvelope<EmploymentTerm>>(
    `/employees/${employeeId}/employment-terms`,
    payload,
  );
  return data.data;
}

export async function searchUnlinkedUsers(search: string): Promise<AuthUser[]> {
  const { data } = await apiClient.get<ApiEnvelope<AuthUser[]>>('/users', {
    params: { unlinked: true, search },
  });
  return data.data;
}
