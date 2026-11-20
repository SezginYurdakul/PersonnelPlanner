import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { ConfirmDialog } from '../../components/ui/ConfirmDialog';
import { TimeSelect } from '../../components/ui/TimeSelect';
import type { Candidate, RankingMode } from '../../types/scheduling';
import { fetchAlternativeCandidates } from './api';
import { CandidateLoad } from './CandidateLoad';

export interface TimeOverride {
  startsAt: string;
  endsAt: string;
}

export interface LineOverride {
  lineId: number;
  shiftPatternId: number;
}

interface LineOption extends LineOverride {
  lineName: string;
}

interface ManualAssignmentFormModalProps {
  scheduleId: number;
  lineId: number;
  shiftPatternId: number | null;
  workDate: string;
  roleId: number;
  roleName: string;
  rankingMode: RankingMode;
  // Only for a line-independent role (e.g. Shift Lead/Vardiya Sefi) - every OTHER line
  // running this same slot type today, so the admin can anchor this assignment's hours to
  // whichever line the person is actually covering, instead of being stuck with whichever
  // line the backend arbitrarily "borrowed" hours from when it first flagged this open.
  lineOptions?: LineOption[];
  onClose: () => void;
  onAssign: (
    employeeId: number,
    confirmOverride: boolean,
    timeOverride?: TimeOverride,
    lineOverride?: LineOverride,
  ) => void;
}

export function ManualAssignmentFormModal({
  scheduleId,
  lineId,
  shiftPatternId,
  workDate,
  roleId,
  roleName,
  rankingMode,
  lineOptions,
  onClose,
  onAssign,
}: ManualAssignmentFormModalProps) {
  const [showAll, setShowAll] = useState(false);
  const [pendingOverride, setPendingOverride] = useState<Candidate | null>(null);
  const [overrideHours, setOverrideHours] = useState(false);
  const [startsAt, setStartsAt] = useState('08:00');
  const [endsAt, setEndsAt] = useState('16:00');
  const [selectedLineId, setSelectedLineId] = useState<number | null>(lineId);

  const selectedLine = lineOptions?.find((o) => o.lineId === selectedLineId);
  // Once a line is picked from lineOptions, its own line_id/shift_pattern_id take over from
  // the props (which only ever reflect whichever line the "Unfilled" badge happened to be
  // anchored to) - everywhere below ranks candidates and creates the assignment against
  // whichever line is actually selected.
  const activeLineId = selectedLine?.lineId ?? lineId;
  const activeShiftPatternId = selectedLine?.shiftPatternId ?? shiftPatternId;

  // A flexible-hours role (shiftPatternId null) has no pattern to fall back on for its
  // hours - unlike every other role kind, entering a time range here isn't optional.
  const hoursRequired = activeShiftPatternId === null;
  const timeOverride: TimeOverride | undefined =
    hoursRequired || overrideHours ? { startsAt, endsAt } : undefined;
  const hoursMissing = hoursRequired && (!startsAt || !endsAt);
  const lineOverride: LineOverride | undefined =
    lineOptions && activeShiftPatternId !== null ? { lineId: activeLineId, shiftPatternId: activeShiftPatternId } : undefined;

  const { data: candidates, isLoading } = useQuery({
    // Included in the key so changing the time pickers on a flexible role, or the line for
    // a line-independent role, re-ranks candidates against the hours actually about to be
    // assigned, not stale ones.
    queryKey: [
      'alternative-candidates',
      scheduleId,
      activeLineId,
      activeShiftPatternId,
      workDate,
      roleId,
      rankingMode,
      timeOverride?.startsAt,
      timeOverride?.endsAt,
    ],
    queryFn: () =>
      fetchAlternativeCandidates(scheduleId, {
        line_id: activeLineId,
        shift_pattern_id: activeShiftPatternId,
        work_date: workDate,
        role_id: roleId,
        ranking_mode: rankingMode,
        starts_at: timeOverride?.startsAt,
        ends_at: timeOverride?.endsAt,
      }),
  });

  const visibleCandidates = showAll
    ? candidates
    : candidates?.filter((c) => c.qualified && c.rule_compliant && !c.violates_employee_constraint);

  function handlePick(candidate: Candidate) {
    if (!candidate.qualified || !candidate.rule_compliant || candidate.violates_employee_constraint) {
      setPendingOverride(candidate);
      return;
    }

    onAssign(candidate.employee.id, false, timeOverride, lineOverride);
  }

  return (
    <div className="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
      <div className="w-full max-w-lg rounded-2xl border border-x-border bg-white p-6 shadow-xl">
        <div className="mb-4 flex items-center justify-between">
          <h3 className="text-base font-extrabold text-x-brown">Assign — {roleName}</h3>
          <button type="button" onClick={onClose} className="text-slate-400 hover:text-slate-600">
            <i className="fa-solid fa-xmark" />
          </button>
        </div>

        <label className="mb-3 flex items-center gap-2 text-xs font-semibold text-slate-600">
          <input type="checkbox" checked={showAll} onChange={(e) => setShowAll(e.target.checked)} />
          Show all employees (including unqualified)
        </label>

        {lineOptions && lineOptions.length > 1 && (
          <div className="mb-4">
            <label className="mb-1 block text-xs font-semibold text-slate-600">
              Anchor to line&apos;s shift hours
            </label>
            <select
              className="w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
              value={activeLineId}
              onChange={(e) => setSelectedLineId(Number(e.target.value))}
            >
              {lineOptions.map((option) => (
                <option key={option.lineId} value={option.lineId}>
                  {option.lineName}
                </option>
              ))}
            </select>
          </div>
        )}

        {hoursRequired ? (
          <div className="mb-4">
            <label className="mb-1 block text-xs font-semibold text-slate-600">Start / end time</label>
            <div className="flex items-center gap-3">
              <TimeSelect value={startsAt} onChange={setStartsAt} />
              <span className="text-sm text-slate-400">–</span>
              <TimeSelect value={endsAt} onChange={setEndsAt} />
            </div>
          </div>
        ) : (
          <>
            <label className="mb-2 flex items-center gap-2 text-xs font-semibold text-slate-600">
              <input type="checkbox" checked={overrideHours} onChange={(e) => setOverrideHours(e.target.checked)} />
              Override shift hours for this line/assignment
            </label>
            {overrideHours && (
              <div className="mb-4 flex items-center gap-3">
                <TimeSelect value={startsAt} onChange={setStartsAt} />
                <span className="text-sm text-slate-400">–</span>
                <TimeSelect value={endsAt} onChange={setEndsAt} />
              </div>
            )}
          </>
        )}

        {isLoading && <p className="text-sm text-slate-500">Loading candidates…</p>}

        <ul className="max-h-80 space-y-2 overflow-y-auto">
          {visibleCandidates?.map((candidate) => (
            <li
              key={candidate.employee.id}
              className="flex items-center justify-between rounded-xl border border-x-border p-3"
            >
              <div>
                <p className="text-sm font-bold text-x-brown">
                  {candidate.employee.first_name} {candidate.employee.last_name}
                </p>
                <div className="mt-1 flex items-center gap-2 text-xs text-slate-500">
                  <CandidateLoad candidate={candidate} rankingMode={rankingMode} />
                  {!candidate.qualified && <Badge tone="warning">Requires override</Badge>}
                  {candidate.qualified && !candidate.rule_compliant && <Badge tone="danger">Rule violation</Badge>}
                  {candidate.qualified && candidate.rule_compliant && candidate.violates_employee_constraint && (
                    <Badge tone="warning">{candidate.exclusion_reason ?? 'Constraint'}</Badge>
                  )}
                </div>
              </div>
              <Button
                variant={candidate.qualified && candidate.rule_compliant ? 'primary' : 'secondary'}
                className="px-3 py-1.5 text-xs"
                disabled={hoursMissing}
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
            !pendingOverride.qualified
              ? 'is not qualified for this role.'
              : pendingOverride.exclusion_reason ?? 'would violate a rule.'
          } Assign anyway?`}
          confirmLabel="Assign anyway"
          danger
          onCancel={() => setPendingOverride(null)}
          onConfirm={() => {
            onAssign(pendingOverride.employee.id, true, timeOverride, lineOverride);
            setPendingOverride(null);
          }}
        />
      )}
    </div>
  );
}
