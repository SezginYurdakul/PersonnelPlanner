import { useMemo, useState } from 'react';
import { DndContext, type DragEndEvent } from '@dnd-kit/core';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import dayjs from 'dayjs';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Card } from '../../components/ui/Card';
import { ConfirmDialog } from '../../components/ui/ConfirmDialog';
import { RuleViolationBanner } from '../../components/ui/RuleViolationBanner';
import { fetchLines } from '../lines/api';
import { fetchShiftPatterns } from '../lines/api';
import type { RankingMode, RuleResult, Schedule, ShiftAssignment, UnfilledSlot } from '../../types/scheduling';
import {
  approveSchedule,
  generateSuggestion,
  fetchScheduleByWeek,
  moveAssignment,
  createAssignment,
} from './api';
import { ScheduleGridCell } from './ScheduleGridCell';
import { OnLeaveThisWeekPanel } from './OnLeaveThisWeekPanel';
import { ManualAssignmentFormModal } from './ManualAssignmentFormModal';
import { AlternativeCandidatesPanel } from './AlternativeCandidatesPanel';

interface WeeklyScheduleGridProps {
  weekStartDate: string;
}

interface PendingSlot {
  lineId: number;
  shiftPatternId: number;
  workDate: string;
  roleId?: number;
}

interface PendingMove {
  assignment: ShiftAssignment;
  lineId: number;
  shiftPatternId: number;
  workDate: string;
}

export function WeeklyScheduleGrid({ weekStartDate }: WeeklyScheduleGridProps) {
  const queryClient = useQueryClient();
  const [rankingMode, setRankingMode] = useState<RankingMode>('fair');
  const [selectedLineIds, setSelectedLineIds] = useState<number[]>([]);
  const [selectedShiftPatternIds, setSelectedShiftPatternIds] = useState<number[]>([]);
  const [pendingSlot, setPendingSlot] = useState<PendingSlot | null>(null);
  const [selectedAssignment, setSelectedAssignment] = useState<ShiftAssignment | null>(null);
  const [pendingMove, setPendingMove] = useState<PendingMove | null>(null);
  const [approvalError, setApprovalError] = useState<{ message: string; blockingSlots: UnfilledSlot[] } | null>(
    null,
  );
  const [violations, setViolations] = useState<{ employeeName: string; message: string; assignmentId: number }[]>(
    [],
  );

  const { data: lines } = useQuery({ queryKey: ['lines'], queryFn: fetchLines });
  const { data: shiftPatterns } = useQuery({ queryKey: ['shift-patterns'], queryFn: fetchShiftPatterns });
  const { data: schedule, isLoading } = useQuery({
    queryKey: ['schedule', weekStartDate],
    queryFn: () => fetchScheduleByWeek(weekStartDate),
  });

  const [unfilledSlots, setUnfilledSlots] = useState<UnfilledSlot[]>([]);

  const days = useMemo(
    () => Array.from({ length: 7 }, (_, i) => dayjs(weekStartDate).add(i, 'day').format('YYYY-MM-DD')),
    [weekStartDate],
  );

  const activeLines = lines?.filter((l) => l.is_active) ?? [];
  const activeShiftPatterns = shiftPatterns?.filter((sp) => sp.is_active) ?? [];

  function assignmentsFor(lineId: number, shiftPatternId: number, workDate: string): ShiftAssignment[] {
    return (
      schedule?.assignments?.filter(
        (a) => a.line.id === lineId && a.shift_pattern?.id === shiftPatternId && a.work_date === workDate,
      ) ?? []
    );
  }

  function unfilledFor(lineId: number, shiftPatternId: number, workDate: string): UnfilledSlot[] {
    return unfilledSlots.filter(
      (s) => s.line.id === lineId && s.shift_pattern?.id === shiftPatternId && s.work_date === workDate,
    );
  }

  const generateMutation = useMutation({
    mutationFn: generateSuggestion,
    onSuccess: (result) => {
      queryClient.setQueryData(['schedule', weekStartDate], result.schedule);
      setUnfilledSlots(result.unfilled_slots);
    },
  });

  const approveMutation = useMutation({
    mutationFn: approveSchedule,
    onSuccess: (updated) => {
      queryClient.setQueryData(['schedule', weekStartDate], updated);
      setApprovalError(null);
    },
    onError: (error: unknown) => {
      const axiosError = error as { response?: { data?: { message: string; blocking_slots: UnfilledSlot[] } } };
      if (axiosError.response?.data) {
        setApprovalError({
          message: axiosError.response.data.message,
          blockingSlots: axiosError.response.data.blocking_slots,
        });
      }
    },
  });

  function applyMutationResult(assignment: ShiftAssignment, ruleResults: RuleResult[]) {
    queryClient.setQueryData(['schedule', weekStartDate], (prev: Schedule | null | undefined) => {
      if (!prev) return prev;
      const others = (prev.assignments ?? []).filter((a) => a.id !== assignment.id);
      return { ...prev, assignments: [...others, assignment] };
    });

    const employeeName = `${assignment.employee.first_name} ${assignment.employee.last_name}`;
    const newViolations = ruleResults
      .filter((r) => r.status === 'violation')
      .map((r) => ({ employeeName, message: r.message ?? '', assignmentId: assignment.id }));

    setViolations((prev) => [...prev.filter((v) => v.assignmentId !== assignment.id), ...newViolations]);
  }

  const moveMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Parameters<typeof moveAssignment>[1] }) =>
      moveAssignment(id, payload),
    onSuccess: (result) => applyMutationResult(result.assignment, result.rule_results),
  });

  const createMutation = useMutation({
    mutationFn: createAssignment,
    onSuccess: (result) => {
      applyMutationResult(result.assignment, result.rule_results);
      setPendingSlot(null);
    },
  });

  function handleDragEnd(event: DragEndEvent) {
    const assignment = event.active.data.current?.assignment as ShiftAssignment | undefined;
    const target = event.over?.data.current as { lineId: number; shiftPatternId: number; workDate: string } | undefined;

    if (!assignment || !target) return;

    const sameSlot =
      assignment.line.id === target.lineId &&
      assignment.shift_pattern?.id === target.shiftPatternId &&
      assignment.work_date === target.workDate;

    if (sameSlot) return;

    const occupied = assignmentsFor(target.lineId, target.shiftPatternId, target.workDate).length > 0;

    if (occupied) {
      setPendingMove({ assignment, ...target });
      return;
    }

    moveMutation.mutate({
      id: assignment.id,
      payload: { line_id: target.lineId, shift_pattern_id: target.shiftPatternId, work_date: target.workDate },
    });
  }

  function jumpToAssignment(assignmentId: number) {
    const el = document.querySelector(`[data-assignment-id="${assignmentId}"]`);
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    el?.classList.add('ring-2', 'ring-visser-gold');
    setTimeout(() => el?.classList.remove('ring-2', 'ring-visser-gold'), 1500);
  }

  const blockingCount = unfilledSlots.filter((s) => s.blocking).length;
  const canApprove = schedule && schedule.status !== 'approved' && blockingCount === 0;

  return (
    <DndContext onDragEnd={handleDragEnd}>
      <div className="space-y-6">
        <Card>
          <div className="flex flex-wrap items-center justify-between gap-4">
            <div>
              <h2 className="text-base font-extrabold text-visser-brown">
                Week of {dayjs(weekStartDate).format('D MMM YYYY')}
              </h2>
              {schedule && (
                <Badge tone={schedule.status === 'approved' ? 'success' : 'neutral'}>{schedule.status}</Badge>
              )}
            </div>
            <div className="flex items-center gap-3">
              {blockingCount > 0 && (
                <span className="text-xs font-semibold text-rose-600">
                  {blockingCount} mandatory station{blockingCount > 1 ? 's' : ''} unfilled
                </span>
              )}
              <Button
                disabled={!canApprove || approveMutation.isPending}
                onClick={() => schedule && approveMutation.mutate(schedule.id)}
              >
                Approve
              </Button>
            </div>
          </div>
        </Card>

        <Card>
          <h3 className="mb-3 text-sm font-extrabold text-visser-brown">Generate Suggestion</h3>
          <div className="flex flex-wrap items-end gap-4">
            <div>
              <label className="mb-1 block text-xs font-semibold text-slate-600">Lines</label>
              <select
                multiple
                className="min-w-[160px] rounded border border-slate-300 px-2 py-1.5 text-sm"
                value={selectedLineIds.map(String)}
                onChange={(e) =>
                  setSelectedLineIds(Array.from(e.target.selectedOptions).map((o) => Number(o.value)))
                }
              >
                {activeLines.map((line) => (
                  <option key={line.id} value={line.id}>
                    {line.name}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="mb-1 block text-xs font-semibold text-slate-600">Shifts</label>
              <select
                multiple
                className="min-w-[160px] rounded border border-slate-300 px-2 py-1.5 text-sm"
                value={selectedShiftPatternIds.map(String)}
                onChange={(e) =>
                  setSelectedShiftPatternIds(Array.from(e.target.selectedOptions).map((o) => Number(o.value)))
                }
              >
                {activeShiftPatterns.map((sp) => (
                  <option key={sp.id} value={sp.id}>
                    {sp.name} ({sp.start_time.slice(0, 5)}–{sp.end_time.slice(0, 5)})
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="mb-1 block text-xs font-semibold text-slate-600">Ranking</label>
              <select
                className="rounded border border-slate-300 px-2 py-1.5 text-sm"
                value={rankingMode}
                onChange={(e) => setRankingMode(e.target.value as RankingMode)}
              >
                <option value="fair">Fair</option>
                <option value="cost">Cost-based</option>
              </select>
            </div>
            <Button
              disabled={selectedLineIds.length === 0 || selectedShiftPatternIds.length === 0 || generateMutation.isPending}
              onClick={() =>
                generateMutation.mutate({
                  week_start_date: weekStartDate,
                  line_ids: selectedLineIds,
                  shift_pattern_ids: selectedShiftPatternIds,
                  ranking_mode: rankingMode,
                })
              }
            >
              {generateMutation.isPending ? 'Generating…' : 'Generate Suggestion'}
            </Button>
          </div>
        </Card>

        <RuleViolationBanner violations={violations} onJumpToAssignment={jumpToAssignment} />

        {approvalError && (
          <div className="rounded-2xl border border-rose-300 bg-rose-50 p-4">
            <h3 className="mb-2 text-sm font-extrabold text-rose-800">{approvalError.message}</h3>
            <ul className="space-y-1 text-xs text-rose-700">
              {approvalError.blockingSlots.map((slot, i) => (
                <li key={i}>
                  {slot.role.name} — {slot.line.name} — {slot.work_date}
                </li>
              ))}
            </ul>
          </div>
        )}

        {isLoading && <p className="text-sm text-slate-500">Loading schedule…</p>}

        {!schedule && !isLoading && (
          <Card>
            <p className="text-sm text-slate-500">
              No schedule exists for this week yet. Use "Generate Suggestion" above to create one.
            </p>
          </Card>
        )}

        {schedule && (
          <Card className="overflow-x-auto">
            <table className="w-full border-collapse text-sm">
              <thead>
                <tr>
                  <th className="w-40 border-b border-visser-border p-2 text-left text-xs font-bold uppercase text-slate-500">
                    Line / Shift
                  </th>
                  {days.map((day) => (
                    <th
                      key={day}
                      className="border-b border-visser-border p-2 text-left text-xs font-bold uppercase text-slate-500"
                    >
                      {dayjs(day).format('ddd')}
                      <div className="font-normal normal-case tabular-nums text-slate-400">
                        {dayjs(day).format('D MMM')}
                      </div>
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {activeLines.map((line) =>
                  activeShiftPatterns.map((shiftPattern) => (
                    <tr key={`${line.id}-${shiftPattern.id}`}>
                      <td className="border-b border-visser-border p-2 align-top text-xs font-bold text-visser-brown">
                        {line.name}
                        <div className="font-normal text-slate-400">{shiftPattern.name}</div>
                      </td>
                      {days.map((day) => (
                        <td key={day} className="border-b border-visser-border p-1 align-top">
                          <ScheduleGridCell
                            lineId={line.id}
                            shiftPatternId={shiftPattern.id}
                            workDate={day}
                            assignments={assignmentsFor(line.id, shiftPattern.id, day)}
                            unfilledSlots={unfilledFor(line.id, shiftPattern.id, day)}
                            onSlotClick={setPendingSlot}
                            onAssignmentClick={setSelectedAssignment}
                          />
                        </td>
                      ))}
                    </tr>
                  )),
                )}
              </tbody>
            </table>
          </Card>
        )}

        <OnLeaveThisWeekPanel weekStartDate={weekStartDate} />
      </div>

      {pendingSlot && pendingSlot.roleId && schedule && (
        <ManualAssignmentFormModal
          scheduleId={schedule.id}
          lineId={pendingSlot.lineId}
          shiftPatternId={pendingSlot.shiftPatternId}
          workDate={pendingSlot.workDate}
          roleId={pendingSlot.roleId}
          roleName={
            unfilledFor(pendingSlot.lineId, pendingSlot.shiftPatternId, pendingSlot.workDate).find(
              (s) => s.role.id === pendingSlot.roleId,
            )?.role.name ?? ''
          }
          rankingMode={rankingMode}
          onClose={() => setPendingSlot(null)}
          onAssign={(employeeId, confirmOverride) =>
            createMutation.mutate({
              schedule_id: schedule.id,
              employee_id: employeeId,
              line_id: pendingSlot.lineId,
              shift_pattern_id: pendingSlot.shiftPatternId,
              work_date: pendingSlot.workDate,
              role_id: pendingSlot.roleId,
              confirm_override: confirmOverride,
            })
          }
        />
      )}

      {selectedAssignment && schedule && (
        <AlternativeCandidatesPanel
          scheduleId={schedule.id}
          lineId={selectedAssignment.line.id}
          shiftPatternId={selectedAssignment.shift_pattern?.id ?? null}
          workDate={selectedAssignment.work_date}
          roleId={selectedAssignment.role?.id ?? 0}
          rankingMode={rankingMode}
          hasExistingAssignment
          onClose={() => setSelectedAssignment(null)}
          onAssign={(candidate, mode) => {
            if (mode === 'replace' && selectedAssignment.role) {
              createMutation.mutate({
                schedule_id: schedule.id,
                employee_id: candidate.employee.id,
                line_id: selectedAssignment.line.id,
                shift_pattern_id: selectedAssignment.shift_pattern?.id ?? null,
                work_date: selectedAssignment.work_date,
                role_id: selectedAssignment.role.id,
              });
            } else if (selectedAssignment.role) {
              createMutation.mutate({
                schedule_id: schedule.id,
                employee_id: candidate.employee.id,
                line_id: selectedAssignment.line.id,
                shift_pattern_id: selectedAssignment.shift_pattern?.id ?? null,
                work_date: selectedAssignment.work_date,
                role_id: selectedAssignment.role.id,
              });
            }
            setSelectedAssignment(null);
          }}
        />
      )}

      {pendingMove && (
        <ConfirmDialog
          title="Add as an additional person?"
          message={`This slot is already staffed. Add ${pendingMove.assignment.employee.first_name} ${pendingMove.assignment.employee.last_name} here as well?`}
          confirmLabel="Add anyway"
          onCancel={() => setPendingMove(null)}
          onConfirm={() => {
            moveMutation.mutate({
              id: pendingMove.assignment.id,
              payload: {
                line_id: pendingMove.lineId,
                shift_pattern_id: pendingMove.shiftPatternId,
                work_date: pendingMove.workDate,
                confirm_override: true,
              },
            });
            setPendingMove(null);
          }}
        />
      )}
    </DndContext>
  );
}
