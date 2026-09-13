import { apiClient, ensureCsrfCookie } from '../../api/client';
import type { AuthUser, LoginPayload } from '../../types/auth';

interface ApiEnvelope<T> {
  data: T;
}

export async function login(payload: LoginPayload): Promise<AuthUser> {
  await ensureCsrfCookie();
  const { data } = await apiClient.post<ApiEnvelope<AuthUser>>('/auth/login', payload);
  return data.data;
}

export async function logout(): Promise<void> {
  await apiClient.post('/auth/logout');
}

export async function fetchCurrentUser(): Promise<AuthUser> {
  const { data } = await apiClient.get<ApiEnvelope<AuthUser>>('/auth/me');
  return data.data;
}

export interface InviteUserPayload {
  name: string;
  email: string;
  employee_id?: number;
}

export async function inviteUser(payload: InviteUserPayload): Promise<AuthUser> {
  const { data } = await apiClient.post<ApiEnvelope<AuthUser>>('/users/invite', payload);
  return data.data;
}

export async function activateUser(userId: number): Promise<AuthUser> {
  const { data } = await apiClient.post<ApiEnvelope<AuthUser>>(`/users/${userId}/activate`);
  return data.data;
}

export async function fetchPendingActivationUsers(): Promise<AuthUser[]> {
  const { data } = await apiClient.get<ApiEnvelope<AuthUser[]>>('/users', {
    params: { pending_activation: true },
  });
  return data.data;
}

export interface CompleteInvitationPayload {
  token: string;
  password: string;
  password_confirmation: string;
  locale: string;
}

export async function completeInvitation(payload: CompleteInvitationPayload): Promise<void> {
  await ensureCsrfCookie();
  await apiClient.post('/auth/complete-invitation', payload);
}
