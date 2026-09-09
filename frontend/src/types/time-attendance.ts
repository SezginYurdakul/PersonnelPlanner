export type ImportShape = 'simple' | 'detailed';
export type TimeClockSource = 'import_simple' | 'import_detailed' | 'manual';

export interface ImportPreview {
  import_token: string;
  shape: ImportShape;
  headers: string[];
}

export interface SimpleColumnMapping {
  employee_identifier: string;
  work_date: string;
  clock_in: string;
  clock_out: string;
  break_minutes: string;
}

export interface DetailedColumnMapping {
  employee_identifier: string;
  work_date: string;
  event_time: string;
  event_type: string;
}

export interface CommitImportPayload {
  import_token: string;
  shape: ImportShape;
  column_mapping: SimpleColumnMapping | DetailedColumnMapping;
}

export interface ImportRowError {
  row: number;
  error_code: string;
  message: string;
}

export interface ImportSummary {
  imported_count: number;
  error_count: number;
  errors: ImportRowError[];
}

export interface TimeClockBreak {
  id: number;
  break_start: string;
  break_end: string;
}

export interface TimeClockEntryEmployee {
  id: number;
  first_name: string;
  last_name: string;
}

export interface TimeClockEntry {
  id: number;
  employee: TimeClockEntryEmployee;
  shift_assignment_id: number | null;
  work_date: string;
  clock_in: string;
  clock_out: string;
  break_minutes: number;
  worked_minutes: number;
  source: TimeClockSource;
  breaks: TimeClockBreak[];
}

export interface TimeClockEntryFilters {
  employee_id?: number;
  date_from?: string;
  date_to?: string;
}

export interface TimeClockEntryFormValues {
  employee_id: number;
  work_date: string;
  clock_in: string;
  clock_out: string;
  break_minutes: number;
  shift_assignment_id?: number | null;
}
