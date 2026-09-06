import { apiClient } from '../../api/client';
import type { LeaveRequest, LeaveRequestFormValues, LeaveType } from '../../types/leave';

interface ApiEnvelope<T> {
  data: T;
}

export async function fetchLeaveTypes(): Promise<LeaveType[]> {
  const { data } = await apiClient.get<ApiEnvelope<LeaveType[]>>('/leave-types');
  return data.data;
}

export interface LeaveRequestFilters {
  status?: string;
  leave_type_id?: number;
  employee_id?: number;
}

export async function fetchLeaveRequests(filters: LeaveRequestFilters = {}): Promise<LeaveRequest[]> {
  const { data } = await apiClient.get<ApiEnvelope<LeaveRequest[]>>('/leave-requests', { params: filters });
  return data.data;
}

export async function createLeaveRequest(payload: LeaveRequestFormValues): Promise<LeaveRequest> {
  const { data } = await apiClient.post<ApiEnvelope<LeaveRequest>>('/leave-requests', payload);
  return data.data;
}

export async function updateLeaveRequest(id: number, payload: LeaveRequestFormValues): Promise<LeaveRequest> {
  const { data } = await apiClient.put<ApiEnvelope<LeaveRequest>>(`/leave-requests/${id}`, payload);
  return data.data;
}

export async function deleteLeaveRequest(id: number): Promise<void> {
  await apiClient.delete(`/leave-requests/${id}`);
}

export async function approveLeaveRequest(id: number): Promise<LeaveRequest> {
  const { data } = await apiClient.post<ApiEnvelope<LeaveRequest>>(`/leave-requests/${id}/approve`);
  return data.data;
}

export async function rejectLeaveRequest(id: number): Promise<LeaveRequest> {
  const { data } = await apiClient.post<ApiEnvelope<LeaveRequest>>(`/leave-requests/${id}/reject`);
  return data.data;
}
