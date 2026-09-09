import { apiClient } from '../../api/client';
import type { CompanySettings, CompanySettingsFormValues } from '../../types/company-settings';

interface ApiEnvelope<T> {
  data: T;
}

export async function fetchCompanySettings(): Promise<CompanySettings> {
  const { data } = await apiClient.get<ApiEnvelope<CompanySettings>>('/company-settings');
  return data.data;
}

export async function updateCompanySettings(payload: CompanySettingsFormValues): Promise<CompanySettings> {
  const { data } = await apiClient.put<ApiEnvelope<CompanySettings>>('/company-settings', payload);
  return data.data;
}

export async function fetchMyCompanySettings(): Promise<CompanySettings> {
  const { data } = await apiClient.get<ApiEnvelope<CompanySettings>>('/me/company-settings');
  return data.data;
}
