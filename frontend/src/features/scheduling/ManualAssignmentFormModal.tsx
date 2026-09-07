import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { ConfirmDialog } from '../../components/ui/ConfirmDialog';
import type { Candidate, RankingMode } from '../../types/scheduling';
import { fetchAlternativeCandidates } from './api';

interface ManualAssignmentFormModalProps {
  scheduleId: number;
  lineId: number;
  shiftPatternId: number | null;
  workDate: string;
  roleId: number;
  roleName: string;
  rankingMode: RankingMode;
  onClose: () => void;
  onAssign: (employeeId: number, confirmOverride: boolean) => void;
}

export function ManualAssignmentFormModal({
  scheduleId,
  lineId,
  shiftPatternId,
  workDate,
  roleId,
  roleName,
  rankingMode,
  onClose,
  onAssign,
}: ManualAssignmentFormModalProps) {
  const [showAll, setShowAll] = useState(false);
  const [pendingOverride, setPendingOverride] = useState<Candidate | null>(null);

  const { data: candidates, isLoading } = useQuery({
    queryKey: ['alternative-candidates', scheduleId, lineId, shiftPatternId, workDate, roleId, rankingMode],
    queryFn: () =>
      fetchAlternativeCandidates(scheduleId, {
        line_id: lineId,
        shift_pattern_id: shiftPatternId,
        work_date: workDate,
        role_id: roleId,
        ranking_mode: rankingMode,
      }),
  });

  const visibleCandidates = showAll
    ? candidates
    : candidates?.filter((c) => c.qualified && c.rule_compliant);

  function handlePick(candidate: Candidate) {
    if (!candidate.qualified || !candidate.rule_compliant) {
      setPendingOverride(candidate);
      return;
    }

    onAssign(candidate.employee.id, false);
  }

  return (
    <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
      <div className="w-full max-w-lg rounded-2xl border border-visser-border bg-white p-6 shadow-xl">
        <div className="mb-4 flex items-center justify-between">
          <h3 className="text-base font-extrabold text-visser-brown">Assign — {roleName}</h3>
          <button type="button" onClick={onClose} className="text-slate-400 hover:text-slate-600">
            <i className="fa-solid fa-xmark" />
          </button>
        </div>

        <label className="mb-4 flex items-center gap-2 text-xs font-semibold text-slate-600">
          <input type="checkbox" checked={showAll} onChange={(e) => setShowAll(e.target.checked)} />
          Show all employees (including unqualified)
        </label>

        {isLoading && <p className="text-sm text-slate-500">Loading candidates…</p>}

        <ul className="max-h-80 space-y-2 overflow-y-auto">
          {visibleCandidates?.map((candidate) => (
            <li
              key={candidate.employee.id}
              className="flex items-center justify-between rounded-xl border border-visser-border p-3"
            >
              <div>
                <p className="text-sm font-bold text-visser-brown">
                  {candidate.employee.first_name} {candidate.employee.last_name}
                </p>
                <div className="mt-1 flex items-center gap-2 text-xs text-slate-500">
                  {candidate.score !== null && (
                    <span className="tabular-nums">
                      {rankingMode === 'cost' ? `€${candidate.score.toFixed(2)}` : `${candidate.score.toFixed(1)}h`}
                    </span>
                  )}
                  {!candidate.qualified && <Badge tone="warning">Requires override</Badge>}
                  {candidate.qualified && !candidate.rule_compliant && <Badge tone="danger">Rule violation</Badge>}
                </div>
              </div>
              <Button
                variant={candidate.qualified && candidate.rule_compliant ? 'primary' : 'secondary'}
                className="px-3 py-1.5 text-xs"
                onClick={() => handlePick(candidate)}
              >
                Assign
              </Button>
            </li>
          ))}
        </ul>
      </div>

      {pendingOverride && (
        <ConfirmDialog
          title="Assign anyway?"
          message={`${pendingOverride.employee.first_name} ${pendingOverride.employee.last_name} ${
            pendingOverride.qualified ? pendingOverride.exclusion_reason ?? 'would violate a rule.' : 'is not qualified for this role.'
          } Assign anyway?`}
          confirmLabel="Assign anyway"
          danger
          onCancel={() => setPendingOverride(null)}
          onConfirm={() => {
            onAssign(pendingOverride.employee.id, true);
            setPendingOverride(null);
          }}
        />
      )}
    </div>
  );
}
