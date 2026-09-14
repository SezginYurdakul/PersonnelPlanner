import { useMemo, useState } from 'react';
import dayjs from 'dayjs';
import { useMutation, useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { AppLayout } from '../components/layout/AppLayout';
import { Card } from '../components/ui/Card';
import { Button } from '../components/ui/Button';
import { downloadReportExcel, downloadReportPdf, fetchReport } from '../features/reporting/api';
import type { ReportFilters } from '../types/reporting';

type PresetRange = 'week' | 'month';

function currentWeekRange(): ReportFilters {
  const today = dayjs();
  const isoDay = today.day() === 0 ? 7 : today.day();
  const start = today.subtract(isoDay - 1, 'day');
  return {
    start_date: start.format('YYYY-MM-DD'),
    end_date: start.add(6, 'day').format('YYYY-MM-DD'),
  };
}

function currentMonthRange(): ReportFilters {
  const today = dayjs();
  return {
    start_date: today.startOf('month').format('YYYY-MM-DD'),
    end_date: today.endOf('month').format('YYYY-MM-DD'),
  };
}

export function ReportsPage() {
  const { t } = useTranslation();
  const [preset, setPreset] = useState<PresetRange>('week');
  const [filters, setFilters] = useState<ReportFilters>(currentWeekRange);

  const { data: report, isLoading } = useQuery({
    queryKey: ['report', filters],
    queryFn: () => fetchReport(filters),
  });

  const pdfMutation = useMutation({ mutationFn: () => downloadReportPdf(filters) });
  const excelMutation = useMutation({ mutationFn: () => downloadReportExcel(filters) });

  function applyPreset(next: PresetRange) {
    setPreset(next);
    setFilters(next === 'week' ? currentWeekRange() : currentMonthRange());
  }

  const lineShiftKeys = useMemo(() => {
    if (!report) return [];
    const seen = new Map<string, { lineName: string; shiftPatternName: string }>();
    for (const row of report.headcount) {
      const key = `${row.line_id}-${row.shift_pattern_id}`;
      if (!seen.has(key)) {
        seen.set(key, { lineName: row.line_name, shiftPatternName: row.shift_pattern_name });
      }
    }
    return [...seen.entries()].sort(([, a], [, b]) => a.lineName.localeCompare(b.lineName));
  }, [report]);

  const days = useMemo(() => {
    if (!report) return [];
    const seen = new Set<string>();
    for (const row of report.headcount) seen.add(row.work_date);
    return [...seen].sort();
  }, [report]);

  function headcountAt(lineShiftKey: string, day: string): number {
    const [lineId, shiftPatternId] = lineShiftKey.split('-').map(Number);
    return (
      report?.headcount.find(
        (row) => row.line_id === lineId && row.shift_pattern_id === shiftPatternId && row.work_date === day,
      )?.headcount ?? 0
    );
  }

  return (
    <AppLayout>
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-xl font-extrabold tracking-tight text-x-brown">{t('reports.title')}</h1>
        <div className="flex gap-2">
          <Button variant="secondary" disabled={pdfMutation.isPending} onClick={() => pdfMutation.mutate()}>
            {t('reports.export_pdf')}
          </Button>
          <Button variant="secondary" disabled={excelMutation.isPending} onClick={() => excelMutation.mutate()}>
            {t('reports.export_excel')}
          </Button>
        </div>
      </div>

      <div className="mb-6 flex flex-wrap items-center gap-3">
        <div className="flex rounded-xl border border-x-border bg-white p-1">
          <button
            type="button"
            onClick={() => applyPreset('week')}
            className={`rounded-lg px-3 py-1.5 text-sm font-semibold ${
              preset === 'week' ? 'bg-x-gold text-x-brown' : 'text-slate-500'
            }`}
          >
            {t('reports.this_week')}
          </button>
          <button
            type="button"
            onClick={() => applyPreset('month')}
            className={`rounded-lg px-3 py-1.5 text-sm font-semibold ${
              preset === 'month' ? 'bg-x-gold text-x-brown' : 'text-slate-500'
            }`}
          >
            {t('reports.this_month')}
          </button>
        </div>

        <input
          type="date"
          value={filters.start_date}
          onChange={(e) => setFilters((f) => ({ ...f, start_date: e.target.value }))}
          className="rounded-lg border border-x-border px-3 py-1.5 text-sm"
        />
        <span className="text-sm text-slate-400">{t('reports.to')}</span>
        <input
          type="date"
          value={filters.end_date}
          onChange={(e) => setFilters((f) => ({ ...f, end_date: e.target.value }))}
          className="rounded-lg border border-x-border px-3 py-1.5 text-sm"
        />
      </div>

      {isLoading ? (
        <p className="text-sm text-slate-500">{t('common.loading')}</p>
      ) : (
        <>
          <Card className="mb-6 overflow-x-auto p-0">
            <h2 className="border-b border-slate-100 px-6 py-3 text-sm font-semibold text-slate-700">
              {t('reports.headcount_title')}
            </h2>
            {lineShiftKeys.length === 0 ? (
              <p className="p-6 text-sm text-slate-400">{t('reports.no_data')}</p>
            ) : (
              <table className="w-full text-sm">
                <thead className="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                  <tr>
                    <th className="px-6 py-3">{t('reports.line_shift')}</th>
                    {days.map((day) => (
                      <th key={day} className="px-4 py-3 text-center">
                        {dayjs(day).format('D MMM')}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {lineShiftKeys.map(([key, { lineName, shiftPatternName }]) => (
                    <tr key={key} className="border-b border-slate-100">
                      <td className="px-6 py-3 font-medium text-slate-900">
                        {lineName} &middot; {shiftPatternName}
                      </td>
                      {days.map((day) => (
                        <td key={day} className="px-4 py-3 text-center text-slate-600">
                          {headcountAt(key, day) || '—'}
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </Card>

          <Card className="p-0">
            <h2 className="border-b border-slate-100 px-6 py-3 text-sm font-semibold text-slate-700">
              {t('reports.cost_breakdown_title')}
            </h2>
            {report && report.cost_breakdown.length === 0 ? (
              <p className="p-6 text-sm text-slate-400">{t('reports.no_data')}</p>
            ) : (
              <table className="w-full text-sm">
                <thead className="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
                  <tr>
                    <th className="px-6 py-3">{t('reports.type')}</th>
                    <th className="px-6 py-3">{t('reports.agency')}</th>
                    <th className="px-6 py-3 text-right">{t('reports.total_hours')}</th>
                    <th className="px-6 py-3 text-right">{t('reports.total_cost')}</th>
                  </tr>
                </thead>
                <tbody>
                  {report?.cost_breakdown.map((row, index) => (
                    <tr key={index} className="border-b border-slate-100">
                      <td className="px-6 py-3 text-slate-900">
                        {row.employee_type === 'vast' ? t('staff.employee_type.vast') : t('staff.employee_type.uitzendkracht')}
                      </td>
                      <td className="px-6 py-3 text-slate-600">{row.agency_name ?? '—'}</td>
                      <td className="px-6 py-3 text-right font-medium text-slate-900">
                        {row.total_hours.toFixed(2)}
                      </td>
                      <td className="px-6 py-3 text-right font-medium text-slate-900">
                        {row.total_cost.toFixed(2)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </Card>
        </>
      )}
    </AppLayout>
  );
}
