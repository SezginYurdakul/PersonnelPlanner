import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Card } from '../../components/ui/Card';
import { ConfirmDialog } from '../../components/ui/ConfirmDialog';
import { TimeSelect } from '../../components/ui/TimeSelect';
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
  onRemove?: () => void;
  hasExistingAssignment: boolean;
  // The existing assignment's own hours, for a flexible/day-specific-hours role
  // (shiftPatternId null) - so ranking uses its real hours instead of an arbitrary
  // fallback window (see AlternativeCandidateService::shiftTimeRange()).
  startsAt?: string | null;
  endsAt?: string | null;
  // The shift pattern's own standard hours (e.g. "08:00:00"/"16:00:00") - used to seed the
  // time editor below when the assignment has no custom override of its own yet.
  standardStartTime?: string | null;
  standardEndTime?: string | null;
  // Updates the currently-assigned person's own hours in place (e.g. 08:00-16:00 ->
  // 07:00-15:00), without changing who's assigned - distinct from onAssign, which only ever
  // swaps the employee. Omitted entirely means this slot has no single existing assignment
  // to retime (hasExistingAssignment=false).
  onUpdateTime?: (timeOverride: { startsAt: string; endsAt: string } | null) => void;
}

function toHm(time?: string | null): string {
  return time ? time.slice(0, 5) : '';
}

function needsOverride(candidate: Candidate): boolean {
  return !candidate.qualified || !candidate.rule_compliant || candidate.violates_employee_constraint;
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
  onRemove,
  hasExistingAssignment,
  startsAt,
  endsAt,
  standardStartTime,
  standardEndTime,
  onUpdateTime,
}: AlternativeCandidatesPanelProps) {
  const [pendingOverride, setPendingOverride] = useState<{ candidate: Candidate; mode: 'replace' | 'add' } | null>(
    null,
  );
  const [pendingRemove, setPendingRemove] = useState(false);
  const [isEditingTime, setIsEditingTime] = useState(false);
  const [editStartsAt, setEditStartsAt] = useState(() => toHm(startsAt) || toHm(standardStartTime) || '08:00');
  const [editEndsAt, setEditEndsAt] = useState(() => toHm(endsAt) || toHm(standardEndTime) || '16:00');
  const hasCustomTime = Boolean(startsAt && endsAt);

  const { data: candidates, isLoading } = useQuery({
    queryKey: ['alternative-candidates', scheduleId, lineId, shiftPatternId, workDate, roleId, rankingMode, startsAt, endsAt],
    queryFn: () =>
      fetchAlternativeCandidates(scheduleId, {
        line_id: lineId,
        shift_pattern_id: shiftPatternId,
        work_date: workDate,
        role_id: roleId,
        ranking_mode: rankingMode,
        starts_at: startsAt,
        ends_at: endsAt,
      }),
  });

  function handlePick(candidate: Candidate, mode: 'replace' | 'add') {
    if (needsOverride(candidate)) {
      setPendingOverride({ candidate, mode });
      return;
    }

    onAssign(candidate, mode);
  }

  return (
    <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
      <Card className="max-h-[80vh] w-full max-w-lg overflow-y-auto">
        <div className="mb-4 flex items-center justify-between">
          <h3 className="text-base font-extrabold text-x-brown">Who else could go here?</h3>
          <button type="button" onClick={onClose} className="text-slate-400 hover:text-slate-600">
            <i className="fa-solid fa-xmark" />
          </button>
        </div>

        {hasExistingAssignment && onRemove && (
          <Button
            variant="secondary"
            className="mb-4 w-full px-3 py-1.5 text-xs text-rose-700"
            onClick={() => setPendingRemove(true)}
          >
            <i className="fa-solid fa-trash mr-1.5" />
            Remove assignment
          </Button>
        )}

        {hasExistingAssignment && onUpdateTime && (
          <div className="mb-4 rounded-xl border border-x-border p-3">
            {isEditingTime ? (
              <>
                <label className="mb-2 block text-xs font-semibold text-slate-600">
                  Custom start / end time for this person
                </label>
                <div className="mb-3 flex items-center gap-3">
                  <TimeSelect value={editStartsAt} onChange={setEditStartsAt} />
                  <span className="text-sm text-slate-400">–</span>
                  <TimeSelect value={editEndsAt} onChange={setEditEndsAt} />
                </div>
                <div className="flex gap-2">
                  <Button
                    className="px-3 py-1.5 text-xs"
                    onClick={() => {
                      onUpdateTime({ startsAt: editStartsAt, endsAt: editEndsAt });
                      setIsEditingTime(false);
                    }}
                  >
                    Save time
                  </Button>
                  {hasCustomTime && (
                    <Button
                      variant="secondary"
                      className="px-3 py-1.5 text-xs"
                      onClick={() => {
                        onUpdateTime(null);
                        setIsEditingTime(false);
                      }}
                    >
                      Reset to standard
                    </Button>
                  )}
                  <Button variant="secondary" className="px-3 py-1.5 text-xs" onClick={() => setIsEditingTime(false)}>
                    Cancel
                  </Button>
                </div>
              </>
            ) : (
              <button
                type="button"
                className="flex w-full items-center justify-between text-xs font-semibold text-slate-600 hover:text-x-brown"
                onClick={() => setIsEditingTime(true)}
              >
                <span>
                  <i className="fa-solid fa-clock mr-1.5" />
                  {hasCustomTime ? `Custom time: ${toHm(startsAt)}–${toHm(endsAt)}` : 'Use a custom start/end time'}
                </span>
                <i className="fa-solid fa-chevron-right" />
              </button>
            )}
          </div>
        )}

        {isLoading && <p className="text-sm text-slate-500">Loading candidates…</p>}

        <ul className="space-y-2">
          {candidates?.map((candidate) => (
            <li
              key={candidate.employee.id}
              className="flex items-center justify-between rounded-xl border border-x-border p-3"
            >
              <div>
                <p className="text-sm font-bold text-x-brown">
                  {candidate.employee.first_name} {candidate.employee.last_name}
                </p>
                <div className="mt-1 flex items-center gap-2 text-xs text-slate-500">
                  {candidate.score !== null && (
                    <span
                      className="tabular-nums"
                      title={
                        rankingMode === 'cost'
                          ? 'Estimated cost for this shift'
                          : 'Recent fairness load: last 8 approved weeks + hours already assigned this week - not just this one shift'
                      }
                    >
                      {rankingMode === 'cost' ? `€${candidate.score.toFixed(2)}` : `${candidate.score.toFixed(1)}h`}
                    </span>
                  )}
                  {!candidate.qualified && <Badge tone="warning">Not qualified</Badge>}
                  {candidate.qualified && !candidate.rule_compliant && <Badge tone="danger">Rule violation</Badge>}
                  {candidate.qualified && candidate.rule_compliant && candidate.violates_employee_constraint && (
                    <Badge tone="warning">{candidate.exclusion_reason ?? 'Constraint'}</Badge>
                  )}
                </div>
                {candidate.exclusion_reason && (
                  <p className="mt-1 text-xs text-slate-400">{candidate.exclusion_reason}</p>
                )}
              </div>
              <div className="flex flex-col gap-1.5">
                <Button
                  variant={!needsOverride(candidate) ? 'primary' : 'secondary'}
                  className="px-3 py-1.5 text-xs"
                  onClick={() => handlePick(candidate, hasExistingAssignment ? 'replace' : 'add')}
                >
                  {hasExistingAssignment ? 'Replace' : 'Assign'}
                </Button>
                {hasExistingAssignment && (
                  <Button
                    variant="secondary"
                    className="px-3 py-1.5 text-xs"
                    onClick={() => handlePick(candidate, 'add')}
                  >
                    Add as extra
                  </Button>
                )}
              </div>
            </li>
          ))}
        </ul>
      </Card>

      {pendingOverride && (
        <ConfirmDialog
          title="Assign anyway?"
          message={`${pendingOverride.candidate.employee.first_name} ${pendingOverride.candidate.employee.last_name} ${
            !pendingOverride.candidate.qualified
              ? 'is not qualified for this role.'
              : pendingOverride.candidate.exclusion_reason ?? 'would violate a rule.'
          } Assign anyway?`}
          confirmLabel="Assign anyway"
          danger
          onCancel={() => setPendingOverride(null)}
          onConfirm={() => {
            onAssign(pendingOverride.candidate, pendingOverride.mode);
            setPendingOverride(null);
          }}
        />
      )}

      {pendingRemove && (
        <ConfirmDialog
          title="Remove this assignment?"
          message="This person will no longer be scheduled for this role in this shift."
          confirmLabel="Remove"
          danger
          onCancel={() => setPendingRemove(false)}
          onConfirm={() => {
            onRemove?.();
            setPendingRemove(false);
          }}
        />
      )}
    </div>
  );
}
