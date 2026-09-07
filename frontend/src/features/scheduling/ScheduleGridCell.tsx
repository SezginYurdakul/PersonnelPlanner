import { useDroppable } from '@dnd-kit/core';
import { Badge } from '../../components/ui/Badge';
import type { ShiftAssignment, UnfilledSlot } from '../../types/scheduling';
import { ScheduleAssignmentCard } from './ScheduleAssignmentCard';

interface ScheduleGridCellProps {
  lineId: number;
  shiftPatternId: number;
  workDate: string;
  assignments: ShiftAssignment[];
  unfilledSlots: UnfilledSlot[];
  onSlotClick: (slot: { lineId: number; shiftPatternId: number; workDate: string; roleId?: number }) => void;
  onAssignmentClick: (assignment: ShiftAssignment) => void;
}

export function ScheduleGridCell({
  lineId,
  shiftPatternId,
  workDate,
  assignments,
  unfilledSlots,
  onSlotClick,
  onAssignmentClick,
}: ScheduleGridCellProps) {
  const cellId = `cell-${lineId}-${shiftPatternId}-${workDate}`;
  const { setNodeRef, isOver } = useDroppable({
    id: cellId,
    data: { lineId, shiftPatternId, workDate },
  });

  const stationAssignments = assignments.filter((a) => a.role?.role_kind !== 'secondary_task');
  const secondaryAssignments = assignments.filter((a) => a.role?.role_kind === 'secondary_task');
  const blockingUnfilled = unfilledSlots.filter((s) => s.blocking);
  const advisoryUnfilled = unfilledSlots.filter((s) => !s.blocking);

  const isEmpty = assignments.length === 0 && unfilledSlots.length === 0;

  return (
    <div
      ref={setNodeRef}
      data-cell-id={cellId}
      className={`min-h-[72px] space-y-1 rounded-xl border p-1.5 transition-colors ${
        isOver ? 'border-visser-gold bg-amber-50' : 'border-transparent'
      }`}
    >
      {stationAssignments.map((assignment) => (
        <ScheduleAssignmentCard
          key={assignment.id}
          assignment={assignment}
          onClick={() => onAssignmentClick(assignment)}
        />
      ))}
      {secondaryAssignments.map((assignment) => (
        <ScheduleAssignmentCard
          key={assignment.id}
          assignment={assignment}
          onClick={() => onAssignmentClick(assignment)}
        />
      ))}

      {blockingUnfilled.map((slot) => (
        <button
          key={`blocking-${slot.role.id}`}
          type="button"
          onClick={() => onSlotClick({ lineId, shiftPatternId, workDate, roleId: slot.role.id })}
          className="flex w-full items-center justify-between rounded-lg border border-dashed border-rose-300 bg-rose-50 px-2 py-1.5 text-left text-xs"
        >
          <span className="truncate text-rose-700">{slot.role.name}</span>
          <Badge tone="danger">Unfilled</Badge>
        </button>
      ))}
      {advisoryUnfilled.map((slot) => (
        <button
          key={`advisory-${slot.role.id}`}
          type="button"
          onClick={() => onSlotClick({ lineId, shiftPatternId, workDate, roleId: slot.role.id })}
          className="flex w-full items-center justify-between rounded-lg border border-dashed border-amber-300 bg-amber-50 px-2 py-1.5 text-left text-xs"
        >
          <span className="truncate text-amber-700">{slot.role.name}</span>
          <Badge tone="warning">Open</Badge>
        </button>
      ))}

      {isEmpty && (
        <button
          type="button"
          onClick={() => onSlotClick({ lineId, shiftPatternId, workDate })}
          className="flex h-full min-h-[56px] w-full items-center justify-center rounded-lg text-[11px] text-slate-300 hover:bg-visser-warmbg hover:text-slate-400"
        >
          vrij
        </button>
      )}
    </div>
  );
}
