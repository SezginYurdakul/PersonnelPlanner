import { useEffect, useMemo, useState } from 'react';
import { DndContext, PointerSensor, pointerWithin, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import dayjs from 'dayjs';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Card } from '../../components/ui/Card';
import { ConfirmDialog } from '../../components/ui/ConfirmDialog';
import { RuleViolationBanner } from '../../components/ui/RuleViolationBanner';
import { fetchLines, fetchRoles, fetchShiftPatterns } from '../lines/api';
import { formatWeekLabel } from '../../lib/week';
import { useAuth } from '../auth/AuthContext';
import type {
  LeaveConflict,
  RankingMode,
  RuleResult,
  Schedule,
  ShiftAssignment,
  UnfilledSlot,
} from '../../types/scheduling';
import type { SlotType } from '../../types/lines';
import {
  submitScheduleForReview,
  approveSchedule,
  rejectSchedule,
  unlockSchedule,
  startSuggestionRun,
  fetchSuggestionRun,
  fetchSchedule,
  moveAssignment,
  createAssignment,
  updateAssignment,
  updateSchedule,
  deleteAssignment,
} from './api';
import { ScheduleGridCell } from './ScheduleGridCell';
import { ScheduleAssignmentCard } from './ScheduleAssignmentCard';
import { RoleKindIcon } from './RoleKindIcon';
import { OnLeaveThisWeekPanel } from './OnLeaveThisWeekPanel';
import { ScheduleAcknowledgmentPanel } from './ScheduleAcknowledgmentPanel';
import { ManualAssignmentFormModal } from './ManualAssignmentFormModal';
import { AlternativeCandidatesPanel } from './AlternativeCandidatesPanel';

interface WeeklyScheduleGridProps {
  scheduleId: number;
  onBack: () => void;
}

const SLOT_TYPES: SlotType[] = ['day', 'afternoon', 'night'];

// Display order only, so each day reads in time order (00-08, 08-16, 16-24) and the week
// runs from Sunday night to Saturday afternoon. SLOT_TYPES' order still goes to the backend,
// where it also decides the order generate() fills slots in.
const SLOT_DISPLAY_ORDER: SlotType[] = ['night', 'day', 'afternoon'];

interface PendingSlot {
  lineId: number;
  shiftPatternId: number | null;
  workDate: string;
  roleId?: number;
}

interface PendingMove {
  assignment: ShiftAssignment;
  lineId: number;
  shiftPatternId: number;
  workDate: string;
}

function PlanNoteEditor({ schedule }: { schedule: Schedule }) {
  const queryClient = useQueryClient();
  const [isEditing, setIsEditing] = useState(false);
  const [label, setLabel] = useState(schedule.label ?? '');
  const [note, setNote] = useState(schedule.note ?? '');

  const updateMutation = useMutation({
    mutationFn: () => updateSchedule(schedule.id, { label: label || undefined, note: note || undefined }),
    onSuccess: (updated) => {
      queryClient.setQueryData(['schedule-plan', schedule.id], updated);
      setIsEditing(false);
    },
  });

  if (!isEditing) {
    return (
      <button type="button" onClick={() => setIsEditing(true)} className="text-left">
        <h2 className="text-base font-extrabold text-x-brown hover:underline">{schedule.label || 'Untitled plan'}</h2>
        {schedule.note && <p className="mt-0.5 max-w-md text-xs text-slate-500">{schedule.note}</p>}
      </button>
    );
  }

  return (
    <div className="max-w-sm space-y-2">
      <input
        type="text"
        placeholder="Label"
        className="w-full rounded border border-slate-300 px-2 py-1 text-sm"
        value={label}
        onChange={(e) => setLabel(e.target.value)}
      />
      <textarea
        rows={2}
        placeholder="Note"
        className="w-full rounded border border-slate-300 px-2 py-1 text-sm"
        value={note}
        onChange={(e) => setNote(e.target.value)}
      />
      <div className="flex gap-2">
        <Button className="px-3 py-1 text-xs" disabled={updateMutation.isPending} onClick={() => updateMutation.mutate()}>
          Save
        </Button>
        <Button variant="secondary" className="px-3 py-1 text-xs" onClick={() => setIsEditing(false)}>
          Cancel
        </Button>
      </div>
    </div>
  );
}

export function WeeklyScheduleGrid({ scheduleId, onBack }: WeeklyScheduleGridProps) {
  const queryClient = useQueryClient();
  const { user } = useAuth();
  const isSuperAdmin = user?.roles.includes('super_admin') ?? false;
  const [isRejecting, setIsRejecting] = useState(false);
  const [isConfirmingApprove, setIsConfirmingApprove] = useState(false);
  const [rejectionNote, setRejectionNote] = useState('');
  const [rankingMode, setRankingMode] = useState<RankingMode>('fair');
  const [selectedLineIds, setSelectedLineIds] = useState<number[]>([]);
  // lineId -> isoDayOfWeek(1-7) -> included slot types. A freshly selected line defaults
  // every day to all three slot types (today's "everything on" behavior); unchecking a
  // cell there is what produces this one generate run's line_slot_exclusions.
  const [lineDaySlots, setLineDaySlots] = useState<Record<number, Record<number, SlotType[]>>>({});
  const [pendingSlot, setPendingSlot] = useState<PendingSlot | null>(null);
  const [selectedAssignment, setSelectedAssignment] = useState<ShiftAssignment | null>(null);
  const [pendingMove, setPendingMove] = useState<PendingMove | null>(null);
  // Surfaced when dragging an unfilled/open slot onto a person fails backend validation
  // (e.g. "requires an overlapping station assignment") - unlike picking from the ranked
  // candidate list, a drag-drop assign has no pre-check, so this is the only feedback path.
  const [dragAssignError, setDragAssignError] = useState<string | null>(null);
  // Without an activation distance, dnd-kit's default PointerSensor starts a drag on the
  // very first pixel of pointer movement - which swallows the click before it reaches a
  // card's onClick, so a plain click to open the reassign panel never fired. A small
  // threshold lets an actual drag still start while a click-in-place goes through normally.
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }));
  const [approvalError, setApprovalError] = useState<{
    message: string;
    blockingSlots: UnfilledSlot[];
    leaveConflicts: LeaveConflict[];
  } | null>(null);
  const [violations, setViolations] = useState<{ employeeName: string; message: string; assignmentId: number }[]>(
    [],
  );

  const { data: lines } = useQuery({ queryKey: ['lines'], queryFn: fetchLines });
  const { data: shiftPatterns } = useQuery({ queryKey: ['shift-patterns'], queryFn: fetchShiftPatterns });
  const { data: secondaryTaskRoles } = useQuery({
    queryKey: ['roles', { role_kind: 'secondary_task' }],
    queryFn: () => fetchRoles({ role_kind: 'secondary_task' }),
  });
  const flexibleRoles = secondaryTaskRoles?.filter((r) => r.attachment_type === 'flexible') ?? [];
  const { data: schedule, isLoading } = useQuery({
    queryKey: ['schedule-plan', scheduleId],
    queryFn: () => fetchSchedule(scheduleId),
  });

  // Restores the "Lines" checkboxes and each line's day/shift grid from this schedule's
  // last "Generate Suggestion" run (generated_scope) - without this, reopening an already-
  // generated plan always came back with every line unchecked and, the moment you re-checked
  // one, every day/shift defaulted back to fully selected, silently discarding whichever
  // days you'd deliberately excluded last time (e.g. "don't generate Fri/Sat"). Keyed by
  // scheduleId (not the schedule object itself) so this only runs once per schedule opened,
  // not on every background refetch afterward - a manual change mid-session must never be
  // clobbered by a refetch triggered by, say, editing an assignment.
  const [restoredForScheduleId, setRestoredForScheduleId] = useState<number | null>(null);

  useEffect(() => {
    if (!schedule || restoredForScheduleId === scheduleId) return;

    const scope = schedule.generated_scope;

    if (scope) {
      const exclusionSet = new Set(
        (scope.line_slot_exclusions ?? []).map((e) => `${e.line_id}|${e.day_of_week}|${e.slot_type}`),
      );

      setSelectedLineIds(scope.line_ids);
      setLineDaySlots(
        Object.fromEntries(
          scope.line_ids.map((lineId) => [
            lineId,
            Object.fromEntries(
              [1, 2, 3, 4, 5, 6, 7].map((iso) => [
                iso,
                SLOT_TYPES.filter((slotType) => !exclusionSet.has(`${lineId}|${iso}|${slotType}`)),
              ]),
            ),
          ]),
        ),
      );
    }

    setRestoredForScheduleId(scheduleId);
  }, [schedule, scheduleId, restoredForScheduleId]);

  // Computed live server-side (MandatoryCoverageChecker) every time this resource renders -
  // sourced straight from the schedule query so it survives navigating away and back.
  const unfilledSlots = schedule?.unfilled_slots ?? [];

  const weekStartDate = schedule?.week_start_date;

  const days = useMemo(
    () => (weekStartDate ? Array.from({ length: 7 }, (_, i) => dayjs(weekStartDate).add(i, 'day').format('YYYY-MM-DD')) : []),
    [weekStartDate],
  );

  const activeLines = lines?.filter((l) => l.is_active) ?? [];

  // Each line only shows the day/afternoon/night rows from its own shift_pattern_group -
  // "Day Shift" is the same concept everywhere, but the concrete row (and hours) a line
  // uses depends on which group it's assigned to (ProjectPlan.md's per-line shift hours).
  function shiftPatternsForLine(lineId: number) {
    const line = activeLines.find((l) => l.id === lineId);
    if (!line?.shift_pattern_group_id) return [];
    const displayRank = (slotType: SlotType | null) =>
      slotType === null ? SLOT_DISPLAY_ORDER.length : SLOT_DISPLAY_ORDER.indexOf(slotType);
    return (shiftPatterns ?? [])
      .filter((sp) => sp.is_active && sp.shift_pattern_group_id === line.shift_pattern_group_id)
      .sort((a, b) => displayRank(a.slot_type) - displayRank(b.slot_type));
  }

  // For a line-independent role (e.g. Shift Lead/Vardiya Sefi - role.line === null), the
  // "Unfilled" badge is only ever anchored to whichever line the backend arbitrarily
  // borrowed hours from (MandatoryCoverageChecker's fallback line) - without this, manually
  // assigning it always used THAT line's hours, even when the person is actually covering a
  // completely different line's shift that day (creating an overlap between two roles that
  // are really the same shift). Every other active line running the same slot type today is
  // offered instead, so the admin can anchor the assignment to the line it actually matches.
  const pendingRole = secondaryTaskRoles?.find((r) => r.id === pendingSlot?.roleId);
  const pendingSlotType =
    pendingSlot?.shiftPatternId != null
      ? shiftPatterns?.find((sp) => sp.id === pendingSlot.shiftPatternId)?.slot_type
      : undefined;
  const pendingLineOptions: { lineId: number; lineName: string; shiftPatternId: number }[] | undefined =
    pendingRole && pendingRole.line === null && pendingSlotType
      ? activeLines
          .map((line) => {
            const pattern = shiftPatternsForLine(line.id).find((sp) => sp.slot_type === pendingSlotType);
            return pattern ? { lineId: line.id, lineName: line.name, shiftPatternId: pattern.id } : null;
          })
          .filter((option): option is { lineId: number; lineName: string; shiftPatternId: number } => option !== null)
      : undefined;

  function assignmentsFor(lineId: number, shiftPatternId: number, workDate: string): ShiftAssignment[] {
    return (
      schedule?.assignments?.filter(
        (a) =>
          a.line.id === lineId &&
          a.shift_pattern?.id === shiftPatternId &&
          a.work_date === workDate &&
          // A line-independent role's assignment (e.g. Shift Lead) still carries a real,
          // concrete line_id/shift_pattern_id here - purely borrowed to resolve hours, not
          // a sign it belongs to this line. It gets its own cross-line row instead (see
          // crossLineAssignmentsFor()), so it's excluded here to avoid showing up twice.
          a.role?.line_id !== null,
      ) ?? []
    );
  }

  function unfilledFor(lineId: number, shiftPatternId: number, workDate: string): UnfilledSlot[] {
    return unfilledSlots.filter(
      (s) => s.line?.id === lineId && s.shift_pattern?.id === shiftPatternId && s.work_date === workDate,
    );
  }

  // A line-independent role (Shift Lead/Vardiya Sefi, or a company-wide secondary task like
  // Silo Takibi - role.line_id === null) covers the WHOLE shift, not any one line, so it gets
  // its own row per slot type instead of being nested under whichever line it happened to
  // borrow hours from. Matched by slot_type rather than an exact shift_pattern_id, since two
  // different lines' "night" patterns are different concrete ShiftPattern rows (different
  // hours per shift_pattern_group) even though they're the same slot type.
  function crossLineAssignmentsFor(slotType: SlotType, workDate: string): ShiftAssignment[] {
    return (
      schedule?.assignments?.filter(
        (a) => a.role?.line_id === null && a.shift_pattern?.slot_type === slotType && a.work_date === workDate,
      ) ?? []
    );
  }

  function crossLineUnfilledFor(slotType: SlotType, workDate: string): UnfilledSlot[] {
    return unfilledSlots.filter(
      (s) => s.line === null && s.shift_pattern?.slot_type === slotType && s.work_date === workDate,
    );
  }

  // A representative (line, shift_pattern) pair for a given slot type - only used to seed a
  // NEW assignment made from the cross-line row's "Unfilled" badge (ManualAssignmentFormModal's
  // own line picker, built for exactly this, then lets the admin correct it) or to give the
  // row's droppable cells a stable, real identity. Mirrors the same "borrow any one in-scope
  // line" convention the backend already uses for these roles.
  function representativeLineFor(slotType: SlotType): { lineId: number; shiftPatternId: number } | null {
    for (const line of activeLines) {
      const pattern = shiftPatternsForLine(line.id).find((sp) => sp.slot_type === slotType);
      if (pattern) return { lineId: line.id, shiftPatternId: pattern.id };
    }
    return null;
  }

  const hasCrossLineRoles = secondaryTaskRoles?.some((r) => r.line === null) ?? false;

  // Flexible-hours roles (e.g. Production Lead) have no fixed line/shift_pattern - they're
  // grouped by day only, in one single line-independent row rather than the (line,
  // shiftPattern) cell key every other row uses.
  function flexibleAssignmentsFor(workDate: string): ShiftAssignment[] {
    return (
      schedule?.assignments?.filter((a) => a.role?.attachment_type === 'flexible' && a.work_date === workDate) ?? []
    );
  }

  // Generate queues a run on the server (the planner takes minutes, longer than a proxied
  // request may stay open); the run is polled until it completes or fails.
  const [suggestionRunId, setSuggestionRunId] = useState<string | null>(null);

  const generateMutation = useMutation({
    mutationFn: startSuggestionRun,
    onMutate: () => setSuggestionRunId(null),
    onSuccess: (run) => setSuggestionRunId(run.id),
  });

  const suggestionRunQuery = useQuery({
    queryKey: ['suggestion-run', suggestionRunId],
    queryFn: async () => {
      const run = await fetchSuggestionRun(suggestionRunId as string);
      if (run.status === 'completed' && run.schedule) {
        queryClient.setQueryData(['schedule-plan', scheduleId], run.schedule);
        setSuggestionRunId(null);
      }
      return run;
    },
    enabled: suggestionRunId !== null,
    refetchInterval: (query) =>
      query.state.data?.status === 'completed' || query.state.data?.status === 'failed' ? false : 3000,
    refetchOnWindowFocus: false,
  });

  const isGenerating =
    generateMutation.isPending ||
    (suggestionRunId !== null &&
      !suggestionRunQuery.isError &&
      suggestionRunQuery.data?.status !== 'failed');

  const generateFailure = generateMutation.isError
    ? generateMutation.error
    : suggestionRunQuery.isError
      ? suggestionRunQuery.error
      : null;
  const generateError =
    generateFailure !== null
      ? ((generateFailure as { response?: { data?: { message?: string } } }).response?.data?.message ??
        'The schedule could not be generated. Please try again.')
      : suggestionRunQuery.data?.status === 'failed'
        ? (suggestionRunQuery.data.message ?? 'The schedule could not be generated. Please try again.')
        : null;

  const approveMutation = useMutation({
    mutationFn: approveSchedule,
    onSuccess: (updated) => {
      queryClient.setQueryData(['schedule-plan', scheduleId], updated);
      setApprovalError(null);
    },
    onError: (error: unknown) => {
      const axiosError = error as {
        response?: { data?: { message: string; blocking_slots?: UnfilledSlot[]; leave_conflicts?: LeaveConflict[] } };
      };
      if (axiosError.response?.data) {
        setApprovalError({
          message: axiosError.response.data.message,
          blockingSlots: axiosError.response.data.blocking_slots ?? [],
          leaveConflicts: axiosError.response.data.leave_conflicts ?? [],
        });
      }
    },
  });

  const submitMutation = useMutation({
    mutationFn: submitScheduleForReview,
    onSuccess: (updated) => queryClient.setQueryData(['schedule-plan', scheduleId], updated),
  });

  const rejectMutation = useMutation({
    mutationFn: ({ id, note }: { id: number; note: string }) => rejectSchedule(id, note),
    onSuccess: (updated) => {
      queryClient.setQueryData(['schedule-plan', scheduleId], updated);
      setIsRejecting(false);
      setRejectionNote('');
    },
  });

  const unlockMutation = useMutation({
    mutationFn: (id: number) => unlockSchedule(id),
    onSuccess: (updated) => queryClient.setQueryData(['schedule-plan', scheduleId], updated),
  });

  function applyMutationResult(assignment: ShiftAssignment, ruleResults: RuleResult[]) {
    queryClient.setQueryData(['schedule-plan', scheduleId], (prev: Schedule | null | undefined) => {
      if (!prev) return prev;
      const others = (prev.assignments ?? []).filter((a) => a.id !== assignment.id);
      return { ...prev, assignments: [...others, assignment] };
    });

    const employeeName = `${assignment.employee.first_name} ${assignment.employee.last_name}`;
    const newViolations = ruleResults
      .filter((r) => r.status === 'violation')
      .map((r) => ({ employeeName, message: r.message ?? '', assignmentId: assignment.id }));

    setViolations((prev) => [...prev.filter((v) => v.assignmentId !== assignment.id), ...newViolations]);

    // `unfilled_slots` is a snapshot the backend only reconciles against current
    // assignments when it re-renders ScheduleResource (see ScheduleResource::
    // reconciledUnfilledSlots()) - the optimistic merge above updates `assignments` but not
    // that snapshot, so without a refetch a slot just filled by hand would keep showing its
    // stale "Unfilled" badge (and stay clickable, allowing a second person on top of it).
    queryClient.invalidateQueries({ queryKey: ['schedule-plan', scheduleId] });
  }

  const moveMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Parameters<typeof moveAssignment>[1] }) =>
      moveAssignment(id, payload),
    onSuccess: (result) => applyMutationResult(result.assignment, result.rule_results),
  });

  const updateTimeMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Parameters<typeof updateAssignment>[1] }) =>
      updateAssignment(id, payload),
    onSuccess: (result) => applyMutationResult(result.assignment, result.rule_results),
  });

  const createMutation = useMutation({
    mutationFn: createAssignment,
    onSuccess: (result) => {
      applyMutationResult(result.assignment, result.rule_results);
      setPendingSlot(null);
      setDragAssignError(null);
    },
    onError: (error: unknown) => {
      const axiosError = error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
      const data = axiosError.response?.data;
      const firstFieldError = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined;
      setDragAssignError(firstFieldError ?? data?.message ?? 'Could not assign this person to this slot.');
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteAssignment,
    onSuccess: (_data, id) => {
      queryClient.setQueryData(['schedule-plan', scheduleId], (prev: Schedule | null | undefined) => {
        if (!prev) return prev;
        return { ...prev, assignments: (prev.assignments ?? []).filter((a) => a.id !== id) };
      });
      setViolations((prev) => prev.filter((v) => v.assignmentId !== id));
      setSelectedAssignment(null);
      // Removing the sole person off a slot can turn it back into a genuinely unfilled
      // one - only the backend's reconciledUnfilledSlots() knows that, so refetch.
      queryClient.invalidateQueries({ queryKey: ['schedule-plan', scheduleId] });
    },
  });

  function handleDragEnd(event: DragEndEvent) {
    if (!isEditable) return;

    const unfilledSlot = event.active.data.current?.unfilledSlot as UnfilledSlot | undefined;
    const personTarget = event.over?.data.current as
      | { employeeId: number; lineId: number; shiftPatternId: number | null; workDate: string }
      | undefined;

    if (unfilledSlot && personTarget?.employeeId !== undefined && schedule) {
      // Line/shift/day come from the cell the person's card is actually rendered in, not
      // from the slot's own `line` field - that's null for a line-independent role (e.g.
      // Shift Lead), since MandatoryCoverageChecker only tracks that a role has SOME open
      // line to borrow, not which specific one. The person being dropped on is always
      // rendered inside one concrete cell, so that's the real target.
      createMutation.mutate({
        schedule_id: schedule.id,
        employee_id: personTarget.employeeId,
        line_id: personTarget.lineId,
        shift_pattern_id: personTarget.shiftPatternId,
        work_date: personTarget.workDate,
        role_id: unfilledSlot.role.id,
      });
      return;
    }

    const assignment = event.active.data.current?.assignment as ShiftAssignment | undefined;
    const group = event.active.data.current?.groupAssignments as ShiftAssignment[] | undefined;
    const target = event.over?.data.current as { lineId: number; shiftPatternId: number; workDate: string } | undefined;

    if (!target || (!assignment && !group)) return;

    if (assignment) {
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
      return;
    }

    if (group) {
      const sameSlot = group.every(
        (a) =>
          a.line.id === target.lineId && a.shift_pattern?.id === target.shiftPatternId && a.work_date === target.workDate,
      );

      if (sameSlot) return;

      const occupied = assignmentsFor(target.lineId, target.shiftPatternId, target.workDate).length > 0;

      // A group move onto an occupied cell isn't supported by the single-assignment
      // pendingMove/ConfirmDialog flow (that flow assumes displacing/overriding ONE
      // assignment) - refuse the whole group move rather than partially moving some
      // roles, same as an invalid drop.
      if (occupied) return;

      for (const a of group) {
        moveMutation.mutate({
          id: a.id,
          payload: { line_id: target.lineId, shift_pattern_id: target.shiftPatternId, work_date: target.workDate },
        });
      }
    }
  }

  function jumpToAssignment(assignmentId: number) {
    const el = document.querySelector(`[data-assignment-id="${assignmentId}"]`);
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    el?.classList.add('ring-2', 'ring-x-gold');
    setTimeout(() => el?.classList.remove('ring-2', 'ring-x-gold'), 1500);
  }

  const blockingCount = unfilledSlots.filter((s) => s.blocking).length;
  const isEditable = schedule?.status === 'draft';
  const canSubmit = schedule?.status === 'draft' && blockingCount === 0;
  const canApprove = isSuperAdmin && schedule?.status === 'proposed' && blockingCount === 0;
  const canReject = isSuperAdmin && schedule?.status === 'proposed';
  const canUnlock = isSuperAdmin && schedule?.status === 'approved';

  return (
    <DndContext sensors={sensors} collisionDetection={pointerWithin} onDragEnd={handleDragEnd}>
      <div className="space-y-6">
        <button type="button" onClick={onBack} className="flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-x-brown">
          <i className="fa-solid fa-arrow-left" />
          Back to plans
        </button>

        <Card>
          <div className="flex flex-wrap items-center justify-between gap-4">
            <div>
              {schedule ? (
                <PlanNoteEditor schedule={schedule} />
              ) : (
                <h2 className="text-base font-extrabold text-x-brown">Loading…</h2>
              )}
              {schedule && (
                <div className="mt-1 flex items-center gap-2">
                  <span className="text-xs text-slate-400">{formatWeekLabel(schedule.week_start_date)}</span>
                  <Badge tone={schedule.status === 'approved' ? 'success' : schedule.status === 'proposed' ? 'info' : 'neutral'}>
                    {schedule.status}
                  </Badge>
                  {!isEditable && schedule.status !== 'proposed' && (
                    <span className="text-xs text-slate-400">
                      <i className="fa-solid fa-lock mr-1" />
                      Locked
                    </span>
                  )}
                </div>
              )}
              {schedule?.rejection_note && (
                <p className="mt-2 max-w-md rounded-lg border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800">
                  <strong>Sent back for changes:</strong> {schedule.rejection_note}
                </p>
              )}
            </div>
            <div className="flex items-center gap-3">
              {blockingCount > 0 && (
                <span className="text-xs font-semibold text-rose-600">
                  {blockingCount} mandatory station{blockingCount > 1 ? 's' : ''} unfilled
                </span>
              )}
              {schedule?.status === 'draft' && (
                <Button
                  disabled={!canSubmit || submitMutation.isPending}
                  onClick={() => schedule && submitMutation.mutate(schedule.id)}
                >
                  Submit for review
                </Button>
              )}
              {canReject && (
                <Button variant="secondary" disabled={rejectMutation.isPending} onClick={() => setIsRejecting(true)}>
                  Reject
                </Button>
              )}
              {isSuperAdmin && schedule?.status === 'proposed' && (
                <Button
                  disabled={!canApprove || approveMutation.isPending}
                  onClick={() => setIsConfirmingApprove(true)}
                >
                  Approve
                </Button>
              )}
              {canUnlock && (
                <Button
                  variant="secondary"
                  disabled={unlockMutation.isPending}
                  onClick={() => schedule && unlockMutation.mutate(schedule.id)}
                >
                  Unlock for editing
                </Button>
              )}
            </div>
          </div>

          {isRejecting && (
            <div className="mt-4 border-t border-x-border pt-4">
              <label className="mb-1 block text-xs font-semibold text-slate-600">
                Why is this plan being sent back? (required)
              </label>
              <textarea
                rows={2}
                className="w-full max-w-md rounded border border-slate-300 px-2 py-1.5 text-sm"
                value={rejectionNote}
                onChange={(e) => setRejectionNote(e.target.value)}
              />
              <div className="mt-2 flex gap-2">
                <Button
                  variant="danger"
                  disabled={!rejectionNote.trim() || rejectMutation.isPending}
                  onClick={() => schedule && rejectMutation.mutate({ id: schedule.id, note: rejectionNote.trim() })}
                >
                  Send back to admin
                </Button>
                <Button variant="secondary" onClick={() => setIsRejecting(false)}>
                  Cancel
                </Button>
              </div>
            </div>
          )}
        </Card>

        {schedule && !isEditable && (
          <Card className="border-amber-200 bg-amber-50">
            <p className="text-sm font-semibold text-amber-800">
              <i className="fa-solid fa-lock mr-1.5" />
              This plan is locked in its current status ({schedule.status}) and cannot be edited manually or
              regenerated.
            </p>
          </Card>
        )}

        {schedule && isEditable && schedule.was_approved_before && (
          <Card className="border-amber-200 bg-amber-50">
            <p className="text-sm font-semibold text-amber-800">
              <i className="fa-solid fa-triangle-exclamation mr-1.5" />
              This plan was previously approved. It can only be edited manually (drag-and-drop) - Generate
              Suggestion is disabled for it permanently.
            </p>
          </Card>
        )}

        {isEditable && !schedule?.was_approved_before && (
          <Card>
            <h3 className="mb-3 text-sm font-extrabold text-x-brown">Generate Suggestion</h3>
            <div className="flex flex-wrap items-end gap-4">
              <div>
                <label className="mb-1 block text-xs font-semibold text-slate-600">Lines</label>
                {/* A native <select multiple> requires Ctrl/Cmd+click to pick more than one
                    option, which most people never discover - a plain click just replaces
                    the whole selection with one line. Checkboxes make "pick several" obvious. */}
                <div className="flex min-w-[160px] flex-col gap-1.5 rounded border border-slate-300 px-3 py-2 text-sm">
                  {activeLines.map((line) => (
                    <label key={line.id} className="flex items-center gap-2">
                      <input
                        type="checkbox"
                        checked={selectedLineIds.includes(line.id)}
                        onChange={(e) => {
                          const nextIds = e.target.checked
                            ? [...selectedLineIds, line.id]
                            : selectedLineIds.filter((id) => id !== line.id);
                          setSelectedLineIds(nextIds);
                          setLineDaySlots((prev) => {
                            const next: Record<number, Record<number, SlotType[]>> = {};
                            for (const id of nextIds) {
                              next[id] = prev[id] ?? Object.fromEntries(
                                [1, 2, 3, 4, 5, 6, 7].map((iso) => [iso, [...SLOT_TYPES]]),
                              );
                            }
                            return next;
                          });
                        }}
                      />
                      {line.name}
                    </label>
                  ))}
                </div>
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
                disabled={!weekStartDate || selectedLineIds.length === 0 || isGenerating}
                onClick={() => {
                  if (!weekStartDate) return;

                  const slotTypesInScope = new Set<SlotType>();
                  const lineSlotExclusions: { line_id: number; day_of_week: number; slot_type: SlotType }[] = [];

                  for (const lineId of selectedLineIds) {
                    const dayMap = lineDaySlots[lineId] ?? {};
                    for (const isoDay of [1, 2, 3, 4, 5, 6, 7]) {
                      const included = dayMap[isoDay] ?? [];
                      for (const slotType of SLOT_TYPES) {
                        if (included.includes(slotType)) {
                          slotTypesInScope.add(slotType);
                        } else {
                          lineSlotExclusions.push({ line_id: lineId, day_of_week: isoDay, slot_type: slotType });
                        }
                      }
                    }
                  }

                  generateMutation.mutate({
                    week_start_date: weekStartDate,
                    schedule_id: scheduleId,
                    line_ids: selectedLineIds,
                    slot_types: Array.from(slotTypesInScope),
                    ranking_mode: rankingMode,
                    line_slot_exclusions: lineSlotExclusions,
                  });
                }}
              >
                {isGenerating ? 'Generating…' : 'Generate Suggestion'}
              </Button>
            </div>

            {isGenerating && (
              <p className="mt-2 text-xs text-slate-500">
                {suggestionRunQuery.data?.status === 'running'
                  ? 'Planning shift leads and operators over the coming weeks - this can take two to three minutes.'
                  : 'Waiting for the planner - another schedule may be generating first.'}
              </p>
            )}

            {generateError !== null && <p className="mt-2 text-xs text-red-600">{generateError}</p>}

            {selectedLineIds.length > 0 && (
              <div className="mt-4 space-y-3">
                <label className="block text-xs font-semibold text-slate-600">Days &amp; shifts per line</label>
                {selectedLineIds.map((lineId) => {
                  const line = activeLines.find((l) => l.id === lineId);
                  const dayMap = lineDaySlots[lineId] ?? {};

                  function toggleCell(isoDay: number, slotType: SlotType, checked: boolean) {
                    setLineDaySlots((prev) => {
                      const prevDayMap = prev[lineId] ?? {};
                      const prevSlots = prevDayMap[isoDay] ?? [];
                      const nextSlots = checked
                        ? [...prevSlots, slotType]
                        : prevSlots.filter((s) => s !== slotType);
                      return { ...prev, [lineId]: { ...prevDayMap, [isoDay]: nextSlots } };
                    });
                  }

                  return (
                    <div key={lineId} className="overflow-x-auto rounded border border-slate-300 p-2">
                      <p className="mb-1 text-xs font-bold text-slate-700">{line?.name ?? `Line ${lineId}`}</p>
                      <table className="text-xs">
                        <thead>
                          <tr>
                            <th className="w-20" />
                            {days.map((d) => (
                              <th key={d} className="px-2 py-1 text-center font-semibold text-slate-600">
                                {dayjs(d).format('ddd D')}
                              </th>
                            ))}
                          </tr>
                        </thead>
                        <tbody>
                          {SLOT_DISPLAY_ORDER.map((slotType) => (
                            <tr key={slotType}>
                              <td className="pr-2 capitalize text-slate-600">{slotType}</td>
                              {days.map((d) => {
                                const isoDay = dayjs(d).isoWeekday();
                                const included = (dayMap[isoDay] ?? []).includes(slotType);
                                return (
                                  <td key={d} className="px-2 py-1 text-center">
                                    <input
                                      type="checkbox"
                                      checked={included}
                                      onChange={(e) => toggleCell(isoDay, slotType, e.target.checked)}
                                    />
                                  </td>
                                );
                              })}
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  );
                })}
              </div>
            )}
          </Card>
        )}

        <RuleViolationBanner violations={violations} onJumpToAssignment={jumpToAssignment} />

        {approvalError && (
          <div className="rounded-2xl border border-rose-300 bg-rose-50 p-4">
            <h3 className="mb-2 text-sm font-extrabold text-rose-800">{approvalError.message}</h3>
            <ul className="space-y-1 text-xs text-rose-700">
              {approvalError.blockingSlots.map((slot, i) => (
                <li key={i}>
                  {/* slot.line is null for a line-independent role (Shift Lead, or a
                      company-wide secondary task) - it covers the whole shift, not one line. */}
                  {slot.role.name} — {slot.line?.name ?? 'Cross-line'} — {slot.work_date}
                </li>
              ))}
            </ul>

            {approvalError.leaveConflicts.length > 0 && (
              <div className="mt-3 border-t border-rose-200 pt-3">
                <ul className="space-y-1 text-xs text-rose-700">
                  {approvalError.leaveConflicts.map((conflict) => (
                    <li key={conflict.assignment_id}>
                      {conflict.employee.first_name} {conflict.employee.last_name} —{' '}
                      {conflict.reason === 'sick_notice' ? 'reported sick' : 'on approved leave'} —{' '}
                      {conflict.role?.name ?? 'Cross-line'} — {conflict.work_date}
                    </li>
                  ))}
                </ul>
                <button
                  type="button"
                  className="mt-2 text-xs font-semibold text-rose-700 underline hover:text-rose-900"
                  onClick={() => {
                    const names = approvalError.leaveConflicts
                      .map(
                        (c) =>
                          `${c.employee.first_name} ${c.employee.last_name} (${c.work_date}, ${c.role?.name ?? 'Cross-line'})`,
                      )
                      .join(', ');
                    setRejectionNote(
                      `Cannot approve - the following people are on approved leave or reported sick: ${names}. Please reassign before resubmitting.`,
                    );
                    setIsRejecting(true);
                  }}
                >
                  Reject with this reason
                </button>
              </div>
            )}
          </div>
        )}

        {dragAssignError && (
          <div className="flex items-center justify-between rounded-2xl border border-rose-300 bg-rose-50 p-4">
            <p className="text-sm font-semibold text-rose-800">{dragAssignError}</p>
            <button
              type="button"
              onClick={() => setDragAssignError(null)}
              className="text-xs font-semibold text-rose-600 hover:text-rose-800"
            >
              Dismiss
            </button>
          </div>
        )}

        {isLoading && <p className="text-sm text-slate-500">Loading schedule…</p>}

        {schedule && (
          <>
            <div className="flex flex-wrap items-center gap-4 text-xs text-slate-500">
              <span className="flex items-center gap-1.5">
                <RoleKindIcon roleKind="station" />
                Station
              </span>
              <span className="flex items-center gap-1.5">
                <RoleKindIcon roleKind="secondary_task" />
                Secondary task
              </span>
            </div>
            <Card className="overflow-x-auto">
            <table className="w-full border-collapse text-sm">
              <thead>
                <tr>
                  <th className="w-40 border-b border-x-border p-2 text-left text-xs font-bold uppercase text-slate-500">
                    Line / Shift
                  </th>
                  {days.map((day) => (
                    <th
                      key={day}
                      className="border-b border-x-border p-2 text-left text-xs font-bold uppercase text-slate-500"
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
                {hasCrossLineRoles &&
                  SLOT_DISPLAY_ORDER.map((slotType) => {
                    const representative = representativeLineFor(slotType);
                    if (!representative) return null;

                    return (
                      <tr key={`cross-line-${slotType}`} className="bg-x-warmbg/40">
                        <td className="border-b border-x-border p-2 align-top text-xs font-bold text-x-brown">
                          Cross-line roles
                          <div className="font-normal capitalize text-slate-400">{slotType}</div>
                        </td>
                        {days.map((day) => (
                          <td key={day} className="border-b border-x-border p-1 align-top">
                            <ScheduleGridCell
                              lineId={representative.lineId}
                              shiftPatternId={representative.shiftPatternId}
                              workDate={day}
                              assignments={crossLineAssignmentsFor(slotType, day)}
                              unfilledSlots={crossLineUnfilledFor(slotType, day)}
                              onSlotClick={isEditable ? setPendingSlot : () => {}}
                              onAssignmentClick={isEditable ? setSelectedAssignment : () => {}}
                            />
                          </td>
                        ))}
                      </tr>
                    );
                  })}
                {activeLines.map((line) =>
                  shiftPatternsForLine(line.id).map((shiftPattern) => (
                    <tr key={`${line.id}-${shiftPattern.id}`}>
                      <td className="border-b border-x-border p-2 align-top text-xs font-bold text-x-brown">
                        {line.name}
                        <div className="font-normal text-slate-400">{shiftPattern.name}</div>
                      </td>
                      {days.map((day) => (
                        <td key={day} className="border-b border-x-border p-1 align-top">
                          <ScheduleGridCell
                            lineId={line.id}
                            shiftPatternId={shiftPattern.id}
                            workDate={day}
                            assignments={assignmentsFor(line.id, shiftPattern.id, day)}
                            unfilledSlots={unfilledFor(line.id, shiftPattern.id, day)}
                            onSlotClick={isEditable ? setPendingSlot : () => {}}
                            onAssignmentClick={isEditable ? setSelectedAssignment : () => {}}
                          />
                        </td>
                      ))}
                    </tr>
                  )),
                )}
                {flexibleRoles.length > 0 && (
                  <tr>
                    <td className="border-b border-x-border p-2 align-top text-xs font-bold text-x-brown">
                      Flexible roles
                      <div className="font-normal text-slate-400">Day-specific hours</div>
                    </td>
                    {days.map((day) => {
                      const dayAssignments = flexibleAssignmentsFor(day);
                      return (
                        <td key={day} className="border-b border-x-border p-1 align-top">
                          <div className="min-h-[72px] space-y-1 rounded-xl border border-transparent p-1.5">
                            {dayAssignments.map((assignment) => (
                              <ScheduleAssignmentCard
                                key={assignment.id}
                                assignment={assignment}
                                onClick={isEditable ? () => setSelectedAssignment(assignment) : undefined}
                              />
                            ))}
                            {dayAssignments.length === 0 && isEditable && (
                              <button
                                type="button"
                                onClick={() =>
                                  setPendingSlot({
                                    lineId: activeLines[0]?.id ?? 0,
                                    shiftPatternId: null,
                                    workDate: day,
                                    roleId: flexibleRoles[0]?.id,
                                  })
                                }
                                className="flex h-full min-h-[56px] w-full items-center justify-center rounded-lg text-[11px] text-slate-300 hover:bg-x-warmbg hover:text-slate-400"
                              >
                                vrij
                              </button>
                            )}
                          </div>
                        </td>
                      );
                    })}
                  </tr>
                )}
              </tbody>
            </table>
            </Card>
          </>
        )}

        {weekStartDate && <OnLeaveThisWeekPanel weekStartDate={weekStartDate} />}

        {schedule?.status === 'approved' && (
          <ScheduleAcknowledgmentPanel scheduleId={schedule.id} version={schedule.version} />
        )}
      </div>

      {pendingSlot && pendingSlot.roleId && schedule && (
        <ManualAssignmentFormModal
          scheduleId={schedule.id}
          lineId={pendingSlot.lineId}
          shiftPatternId={pendingSlot.shiftPatternId}
          workDate={pendingSlot.workDate}
          roleId={pendingSlot.roleId}
          roleName={
            pendingSlot.shiftPatternId === null
              ? (flexibleRoles.find((r) => r.id === pendingSlot.roleId)?.name ?? '')
              : (unfilledFor(pendingSlot.lineId, pendingSlot.shiftPatternId, pendingSlot.workDate).find(
                  (s) => s.role.id === pendingSlot.roleId,
                )?.role.name ?? '')
          }
          rankingMode={rankingMode}
          lineOptions={pendingLineOptions}
          onClose={() => setPendingSlot(null)}
          onAssign={(employeeId, confirmOverride, timeOverride, lineOverride) =>
            createMutation.mutate({
              schedule_id: schedule.id,
              employee_id: employeeId,
              line_id: lineOverride?.lineId ?? pendingSlot.lineId,
              shift_pattern_id: lineOverride?.shiftPatternId ?? pendingSlot.shiftPatternId,
              work_date: pendingSlot.workDate,
              role_id: pendingSlot.roleId,
              confirm_override: confirmOverride,
              starts_at: timeOverride?.startsAt,
              ends_at: timeOverride?.endsAt,
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
          startsAt={selectedAssignment.starts_at}
          endsAt={selectedAssignment.ends_at}
          standardStartTime={selectedAssignment.shift_pattern?.start_time}
          standardEndTime={selectedAssignment.shift_pattern?.end_time}
          hasExistingAssignment
          onClose={() => setSelectedAssignment(null)}
          onRemove={() => deleteMutation.mutate(selectedAssignment.id)}
          onUpdateTime={(timeOverride) => {
            if (!selectedAssignment.role) return;

            updateTimeMutation.mutate({
              id: selectedAssignment.id,
              payload: {
                employee_id: selectedAssignment.employee.id,
                line_id: selectedAssignment.line.id,
                shift_pattern_id: selectedAssignment.shift_pattern?.id ?? null,
                work_date: selectedAssignment.work_date,
                role_id: selectedAssignment.role.id,
                starts_at: timeOverride?.startsAt ?? null,
                ends_at: timeOverride?.endsAt ?? null,
              },
            });
            setSelectedAssignment(null);
          }}
          onAssign={(candidate, mode) => {
            if (!selectedAssignment.role) return;

            const payload = {
              schedule_id: schedule.id,
              employee_id: candidate.employee.id,
              line_id: selectedAssignment.line.id,
              shift_pattern_id: selectedAssignment.shift_pattern?.id ?? null,
              work_date: selectedAssignment.work_date,
              role_id: selectedAssignment.role.id,
            };

            if (mode === 'replace') {
              // "Replace" must remove the current holder first - nothing stops two
              // employees holding the same role/line/shift/date otherwise (no DB
              // uniqueness constraint on that combination), so creating the new
              // assignment without deleting the old one would leave both assigned.
              deleteMutation.mutate(selectedAssignment.id, {
                onSuccess: () => createMutation.mutate(payload),
              });
            } else {
              createMutation.mutate(payload);
            }

            setSelectedAssignment(null);
          }}
        />
      )}

      {isConfirmingApprove && schedule && (
        <ConfirmDialog
          title="Publish this schedule?"
          message={
            schedule.was_approved_before
              ? 'This schedule was already published before. Approving now will publish this correction as a new version, and every assigned employee will be notified again and asked to confirm they have seen it.'
              : 'Approving will publish this schedule and notify every assigned employee. Are you sure you want to publish it now?'
          }
          confirmLabel="Yes, publish"
          onCancel={() => setIsConfirmingApprove(false)}
          onConfirm={() => {
            approveMutation.mutate(schedule.id);
            setIsConfirmingApprove(false);
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
