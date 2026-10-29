import { apiClient } from '../../api/client';
import type {
  Agency,
  AgencyFormValues,
  Employee,
  EmployeeFormValues,
  EmploymentTerm,
  UnavailableSlot,
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

export async function deleteEmployeePermanently(id: number): Promise<void> {
  await apiClient.delete(`/employees/${id}/permanent`);
}

export async function activateEmployee(employee: Employee): Promise<Employee> {
  return updateEmployee(employee.id, {
    first_name: employee.first_name,
    last_name: employee.last_name,
    phone: employee.phone ?? undefined,
    email: employee.email ?? undefined,
    employee_type: employee.employee_type,
    agency_id: employee.agency?.id ?? null,
    pay_type: employee.pay_type,
    hourly_rate: employee.hourly_rate !== null ? Number(employee.hourly_rate) : null,
    monthly_salary: employee.monthly_salary !== null ? Number(employee.monthly_salary) : null,
    contracted_hours_per_week:
      employee.contracted_hours_per_week !== null ? Number(employee.contracted_hours_per_week) : null,
    default_line_id: employee.default_line_id,
    is_active: true,
  });
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
  payload: { max_weekly_hours: number; min_weekly_hours: number | null; effective_from: string },
): Promise<EmploymentTerm> {
  const { data } = await apiClient.put<ApiEnvelope<EmploymentTerm>>(
    `/employees/${employeeId}/employment-terms`,
    payload,
  );
  return data.data;
}

export async function updateEmployeeUnavailability(
  employeeId: number,
  unavailableSlots: UnavailableSlot[],
): Promise<Employee> {
  const { data } = await apiClient.put<ApiEnvelope<Employee>>(`/employees/${employeeId}/unavailability`, {
    unavailable_slots: unavailableSlots,
  });
  return data.data;
}

export async function updateEmployeeRoleExclusions(employeeId: number, schedulingRoleIds: number[]): Promise<Employee> {
  const { data } = await apiClient.put<ApiEnvelope<Employee>>(`/employees/${employeeId}/role-exclusions`, {
    scheduling_role_ids: schedulingRoleIds,
  });
  return data.data;
}

export async function updateEmployeeNotes(employeeId: number, notes: string | null): Promise<Employee> {
  const { data } = await apiClient.put<ApiEnvelope<Employee>>(`/employees/${employeeId}/notes`, { notes });
  return data.data;
}

export async function searchUnlinkedUsers(search: string): Promise<AuthUser[]> {
  const { data } = await apiClient.get<ApiEnvelope<AuthUser[]>>('/users', {
    params: { unlinked: true, search },
  });
  return data.data;
}
