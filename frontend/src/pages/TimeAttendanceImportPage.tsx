import { useState } from 'react';
import { AppLayout } from '../components/layout/AppLayout';
import { ImportSummaryView } from '../features/time-attendance/ImportSummaryView';
import { ImportWizard } from '../features/time-attendance/ImportWizard';
import { TimeClockEntryTable } from '../features/time-attendance/TimeClockEntryTable';
import type { ImportSummary } from '../types/time-attendance';

export function TimeAttendanceImportPage() {
  const [summary, setSummary] = useState<ImportSummary | null>(null);

  return (
    <AppLayout>
      <h1 className="mb-6 text-xl font-extrabold tracking-tight text-x-brown">Time &amp; Attendance</h1>

      <div className="space-y-6">
        <ImportWizard onImported={setSummary} />
        {summary && <ImportSummaryView summary={summary} />}
        <TimeClockEntryTable />
      </div>
    </AppLayout>
  );
}
