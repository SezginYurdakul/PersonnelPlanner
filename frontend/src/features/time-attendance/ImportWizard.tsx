import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Button } from '../../components/ui/Button';
import { Card } from '../../components/ui/Card';
import type {
  CommitImportPayload,
  DetailedColumnMapping,
  ImportShape,
  ImportSummary,
  SimpleColumnMapping,
} from '../../types/time-attendance';
import { commitImport, previewImport } from './api';

interface ImportWizardProps {
  onImported: (summary: ImportSummary) => void;
}

const SIMPLE_FIELDS: { key: keyof SimpleColumnMapping; label: string }[] = [
  { key: 'employee_identifier', label: 'Employee email' },
  { key: 'work_date', label: 'Work date' },
  { key: 'clock_in', label: 'Clock in' },
  { key: 'clock_out', label: 'Clock out' },
  { key: 'break_minutes', label: 'Break minutes' },
];

const DETAILED_FIELDS: { key: keyof DetailedColumnMapping; label: string }[] = [
  { key: 'employee_identifier', label: 'Employee email' },
  { key: 'work_date', label: 'Work date' },
  { key: 'event_time', label: 'Event time' },
  { key: 'event_type', label: 'Event type' },
];

export function ImportWizard({ onImported }: ImportWizardProps) {
  const queryClient = useQueryClient();
  const [shape, setShape] = useState<ImportShape | null>(null);
  const [file, setFile] = useState<File | null>(null);
  const [importToken, setImportToken] = useState<string | null>(null);
  const [headers, setHeaders] = useState<string[]>([]);
  const [mapping, setMapping] = useState<Record<string, string>>({});

  const previewMutation = useMutation({
    mutationFn: () => previewImport(shape as ImportShape, file as File),
    onSuccess: (preview) => {
      setImportToken(preview.import_token);
      setHeaders(preview.headers);
    },
  });

  const commitMutation = useMutation({
    mutationFn: () =>
      commitImport({
        import_token: importToken as string,
        shape: shape as ImportShape,
        column_mapping: mapping as unknown as SimpleColumnMapping | DetailedColumnMapping,
      } satisfies CommitImportPayload),
    onSuccess: (summary) => {
      onImported(summary);
      void queryClient.invalidateQueries({ queryKey: ['time-clock-entries'] });
      setShape(null);
      setFile(null);
      setImportToken(null);
      setHeaders([]);
      setMapping({});
    },
  });

  function selectShape(next: ImportShape) {
    setShape(next);
    setFile(null);
    setImportToken(null);
    setHeaders([]);
    setMapping({});
  }

  const fields = shape === 'detailed' ? DETAILED_FIELDS : SIMPLE_FIELDS;
  const mappingComplete = fields
    .filter((f) => shape === 'simple' || f.key !== 'break_minutes')
    .every((f) => mapping[f.key]);

  return (
    <Card>
      <h2 className="mb-4 text-base font-extrabold text-x-brown">Import Time Clock Data</h2>

      <div className="mb-4">
        <label className="mb-1 block text-xs font-semibold text-slate-600">1. File shape</label>
        <div className="flex gap-2">
          <Button
            type="button"
            variant={shape === 'simple' ? 'primary' : 'secondary'}
            onClick={() => selectShape('simple')}
          >
            Simple
          </Button>
          <Button
            type="button"
            variant={shape === 'detailed' ? 'primary' : 'secondary'}
            onClick={() => selectShape('detailed')}
          >
            Detailed
          </Button>
        </div>
      </div>

      {shape && (
        <div className="mb-4">
          <label className="mb-1 block text-xs font-semibold text-slate-600">2. Upload file (CSV or Excel)</label>
          <input
            type="file"
            accept=".csv,.txt,.xlsx,.xls"
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
            className="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-x-warmbg file:px-3 file:py-2 file:text-xs file:font-semibold file:text-x-brown"
          />
          <div className="mt-2">
            <Button
              type="button"
              disabled={!file || previewMutation.isPending}
              onClick={() => previewMutation.mutate()}
            >
              {previewMutation.isPending ? 'Reading file…' : 'Continue'}
            </Button>
          </div>
          {previewMutation.isError && (
            <p className="mt-2 text-sm text-rose-600">Could not read this file. Check the format and try again.</p>
          )}
        </div>
      )}

      {headers.length > 0 && (
        <div className="mb-4">
          <label className="mb-2 block text-xs font-semibold text-slate-600">3. Map columns</label>
          <div className="space-y-2">
            {fields.map((field) => (
              <div key={field.key} className="flex items-center gap-3">
                <span className="w-36 text-xs text-slate-500">{field.label}</span>
                <select
                  className="flex-1 rounded border border-slate-300 px-2 py-1.5 text-sm"
                  value={mapping[field.key] ?? ''}
                  onChange={(e) => setMapping((m) => ({ ...m, [field.key]: e.target.value }))}
                >
                  <option value="">— select column —</option>
                  {headers.map((header) => (
                    <option key={header} value={header}>
                      {header}
                    </option>
                  ))}
                </select>
              </div>
            ))}
          </div>
          <div className="mt-3">
            <Button
              type="button"
              disabled={!mappingComplete || commitMutation.isPending}
              onClick={() => commitMutation.mutate()}
            >
              {commitMutation.isPending ? 'Importing…' : 'Import'}
            </Button>
          </div>
        </div>
      )}
    </Card>
  );
}
