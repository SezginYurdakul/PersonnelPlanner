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
