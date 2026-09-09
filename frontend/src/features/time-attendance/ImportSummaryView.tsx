import { Badge } from '../../components/ui/Badge';
import { Card } from '../../components/ui/Card';
import type { ImportSummary } from '../../types/time-attendance';

export function ImportSummaryView({ summary }: { summary: ImportSummary }) {
  return (
    <Card>
      <div className="mb-3 flex items-center gap-3">
        <h2 className="text-base font-extrabold text-x-brown">Import Result</h2>
        <Badge tone="success">{summary.imported_count} imported</Badge>
        {summary.error_count > 0 && <Badge tone="danger">{summary.error_count} errors</Badge>}
      </div>

      {summary.errors.length > 0 && (
        <table className="w-full text-sm">
          <thead className="border-b border-slate-200 text-left text-xs uppercase text-slate-500">
            <tr>
              <th className="py-2 pr-4">Row</th>
              <th className="py-2">Message</th>
            </tr>
          </thead>
          <tbody>
            {summary.errors.map((error, i) => (
              <tr key={i} className="border-b border-slate-100">
                <td className="py-2 pr-4 tabular-nums text-slate-600">{error.row}</td>
                <td className="py-2 text-rose-700">{error.message}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </Card>
  );
}
