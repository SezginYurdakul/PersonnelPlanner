export interface PendingAcknowledgment {
  schedule_id: number;
  week_start_date: string;
  version: number;
}

export interface ScheduleAcknowledgmentStatus {
  employee: { id: number; first_name: string; last_name: string };
  acknowledged: boolean;
  acknowledged_at: string | null;
}
