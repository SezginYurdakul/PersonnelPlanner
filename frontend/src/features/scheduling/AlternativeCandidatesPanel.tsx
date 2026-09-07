import { useQuery } from '@tanstack/react-query';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Card } from '../../components/ui/Card';
import type { Candidate, RankingMode } from '../../types/scheduling';
import { fetchAlternativeCandidates } from './api';

interface AlternativeCandidatesPanelProps {
  scheduleId: number;
  lineId: number;
  shiftPatternId: number | null;
  workDate: string;
  roleId: number;
  rankingMode: RankingMode;
  onAssign: (candidate: Candidate, mode: 'replace' | 'add') => void;
  onClose: () => void;
  hasExistingAssignment: boolean;
}

export function AlternativeCandidatesPanel({
  scheduleId,
  lineId,
  shiftPatternId,
  workDate,
  roleId,
  rankingMode,
  onAssign,
  onClose,
  hasExistingAssignment,
}: AlternativeCandidatesPanelProps) {
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

  return (
    <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
      <Card className="max-h-[80vh] w-full max-w-lg overflow-y-auto">
        <div className="mb-4 flex items-center justify-between">
          <h3 className="text-base font-extrabold text-visser-brown">Who else could go here?</h3>
          <button type="button" onClick={onClose} className="text-slate-400 hover:text-slate-600">
            <i className="fa-solid fa-xmark" />
          </button>
        </div>

        {isLoading && <p className="text-sm text-slate-500">Loading candidates…</p>}

        <ul className="space-y-2">
          {candidates?.map((candidate) => (
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
                  {!candidate.qualified && <Badge tone="warning">Not qualified</Badge>}
                  {candidate.qualified && !candidate.rule_compliant && <Badge tone="danger">Rule violation</Badge>}
                </div>
                {candidate.exclusion_reason && (
                  <p className="mt-1 text-xs text-slate-400">{candidate.exclusion_reason}</p>
                )}
              </div>
              <div className="flex flex-col gap-1.5">
                <Button
                  variant={candidate.qualified && candidate.rule_compliant ? 'primary' : 'secondary'}
                  className="px-3 py-1.5 text-xs"
                  onClick={() => onAssign(candidate, hasExistingAssignment ? 'replace' : 'add')}
                >
                  {hasExistingAssignment ? 'Replace' : 'Assign'}
                </Button>
                {hasExistingAssignment && (
                  <Button
                    variant="secondary"
                    className="px-3 py-1.5 text-xs"
                    onClick={() => onAssign(candidate, 'add')}
                  >
                    Add as extra
                  </Button>
                )}
              </div>
            </li>
          ))}
        </ul>
      </Card>
    </div>
  );
}
