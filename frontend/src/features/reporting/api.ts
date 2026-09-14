import { apiClient } from '../../api/client';
import type { Report, ReportFilters } from '../../types/reporting';

interface ApiEnvelope<T> {
  data: T;
}

export async function fetchReport(filters: ReportFilters): Promise<Report> {
  const { data } = await apiClient.get<ApiEnvelope<Report>>('/reports', { params: filters });
  return data.data;
}

async function downloadExport(path: string, filters: ReportFilters, fallbackFilename: string): Promise<void> {
  const response = await apiClient.get(path, {
    params: filters,
    responseType: 'blob',
  });

  const contentDisposition = response.headers['content-disposition'] as string | undefined;
  const filenameMatch = contentDisposition?.match(/filename="?([^"]+)"?/);
  const filename = filenameMatch?.[1] ?? fallbackFilename;

  const url = window.URL.createObjectURL(response.data as Blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
}

export async function downloadReportPdf(filters: ReportFilters): Promise<void> {
  await downloadExport('/reports/export/pdf', filters, 'workforce-report.pdf');
}

export async function downloadReportExcel(filters: ReportFilters): Promise<void> {
  await downloadExport('/reports/export/excel', filters, 'workforce-report.xlsx');
}
