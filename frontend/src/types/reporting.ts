export interface HeadcountRow {
  work_date: string;
  line_id: number;
  line_name: string;
  shift_pattern_id: number;
  shift_pattern_name: string;
  headcount: number;
}

export interface CostBreakdownRow {
  employee_type: 'vast' | 'uitzendkracht';
  agency_name: string | null;
  total_hours: number;
  total_cost: number;
}

export interface Report {
  period: {
    start_date: string;
    end_date: string;
  };
  headcount: HeadcountRow[];
  cost_breakdown: CostBreakdownRow[];
}

export interface ReportFilters {
  start_date: string;
  end_date: string;
}
