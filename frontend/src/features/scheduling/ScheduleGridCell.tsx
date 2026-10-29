import { useDraggable, useDroppable } from '@dnd-kit/core';
import { Badge } from '../../components/ui/Badge';
import type { ShiftAssignment, UnfilledSlot } from '../../types/scheduling';
import { EmployeeAssignmentGroup } from './EmployeeAssignmentGroup';
import { RoleKindIcon } from './RoleKindIcon';

interface ScheduleGridCellProps {
  lineId: number;
  shiftPatternId: number;
  workDate: string;
  assignments: ShiftAssignment[];
  unfilledSlots: UnfilledSlot[];
  onSlotClick: (slot: { lineId: number; shiftPatternId: number; workDate: string; roleId?: number }) => void;
  onAssignmentClick: (assignment: ShiftAssignment) => void;
}

function unfilledTooltip(slot: UnfilledSlot): string {
  if (slot.reason) return slot.reason;
  if (slot.alternatives.length === 0) return 'No candidates found for this role.';
  const unavailable = slot.alternatives.filter((c) => !c.qualified || c.violates_employee_constraint).length;
  const ruleViolations = slot.alternatives.filter((c) => c.qualified && !c.rule_compliant).length;
  return `${slot.alternatives.length} considered - ${unavailable} unqualified/unavailable, ${ruleViolations} rule violations.`;
}

interface UnfilledSlotButtonProps {
  slot: UnfilledSlot;
  lineId: number;
  shiftPatternId: number;
  workDate: string;
  tone: 'blocking' | 'advisory';
  onClick: () => void;
}

// Draggable so an unfilled/open slot can be dropped straight onto a person's card (see
// EmployeeAssignmentGroup's droppable) as a quicker alternative to opening the candidate
// list - clicking still works exactly as before (the drag activation distance from
// WeeklyScheduleGrid's PointerSensor keeps a plain click from being swallowed as a drag).
function UnfilledSlotButton({ slot, lineId, shiftPatternId, workDate, tone, onClick }: UnfilledSlotButtonProps) {
  const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
    id: `unfilled-${lineId}-${shiftPatternId}-${workDate}-${slot.role.id}`,
    data: { unfilledSlot: slot },
  });

  const isBlocking = tone === 'blocking';

  return (
    <button
      ref={setNodeRef}
      {...listeners}
      {...attributes}
      type="button"
      title={unfilledTooltip(slot)}
      onClick={onClick}
      className={`flex w-full cursor-grab items-center justify-between rounded-lg border border-dashed px-2 py-1.5 text-left text-xs active:cursor-grabbing ${
        isDragging ? 'opacity-50 shadow-lg' : ''
      } ${isBlocking ? 'border-rose-300 bg-rose-50' : 'border-amber-300 bg-amber-50'}`}
    >
      <span className={`flex items-center gap-1 truncate ${isBlocking ? 'text-rose-700' : 'text-amber-700'}`}>
        <RoleKindIcon roleKind={slot.role.role_kind} tone={isBlocking ? 'text-rose-500' : 'text-amber-500'} />
        {slot.role.name}
      </span>
      <Badge tone={isBlocking ? 'danger' : 'warning'}>
        {slot.missing_count > 1
          ? `${isBlocking ? 'Unfilled' : 'Open'} (${slot.missing_count})`
          : isBlocking
            ? 'Unfilled'
            : 'Open'}
      </Badge>
    </button>
  );
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

  const byEmployee = new Map<number, ShiftAssignment[]>();
  for (const a of assignments) {
    const list = byEmployee.get(a.employee.id) ?? [];
    list.push(a);
    byEmployee.set(a.employee.id, list);
  }
  // Station rows before secondary-task rows within each employee's own group - preserves
  // today's ordering, now scoped per employee instead of globally across the whole cell.
  for (const list of byEmployee.values()) {
    list.sort((a, b) => (a.role?.role_kind === 'secondary_task' ? 1 : 0) - (b.role?.role_kind === 'secondary_task' ? 1 : 0));
  }

  const blockingUnfilled = unfilledSlots.filter((s) => s.blocking);
  const advisoryUnfilled = unfilledSlots.filter((s) => !s.blocking);

  const isEmpty = assignments.length === 0 && unfilledSlots.length === 0;

  return (
    <div
      ref={setNodeRef}
      data-cell-id={cellId}
      className={`min-h-[72px] space-y-1 rounded-xl border p-1.5 transition-colors ${
        isOver ? 'border-x-gold bg-amber-50' : 'border-transparent'
      }`}
    >
      {Array.from(byEmployee.entries()).map(([employeeId, employeeAssignments]) => (
        <EmployeeAssignmentGroup
          key={employeeId}
          cellId={cellId}
          employeeId={employeeId}
          lineId={lineId}
          shiftPatternId={shiftPatternId}
          workDate={workDate}
          assignments={employeeAssignments}
          onAssignmentClick={onAssignmentClick}
        />
      ))}

      {blockingUnfilled.map((slot) => (
        <UnfilledSlotButton
          key={`blocking-${slot.role.id}`}
          slot={slot}
          lineId={lineId}
          shiftPatternId={shiftPatternId}
          workDate={workDate}
          tone="blocking"
          onClick={() => onSlotClick({ lineId, shiftPatternId, workDate, roleId: slot.role.id })}
        />
      ))}
      {advisoryUnfilled.map((slot) => (
        <UnfilledSlotButton
          key={`advisory-${slot.role.id}`}
          slot={slot}
          lineId={lineId}
          shiftPatternId={shiftPatternId}
          workDate={workDate}
          tone="advisory"
          onClick={() => onSlotClick({ lineId, shiftPatternId, workDate, roleId: slot.role.id })}
        />
      ))}

      {isEmpty && (
        <button
          type="button"
          onClick={() => onSlotClick({ lineId, shiftPatternId, workDate })}
          className="flex h-full min-h-[56px] w-full items-center justify-center rounded-lg text-[11px] text-slate-300 hover:bg-x-warmbg hover:text-slate-400"
        >
          vrij
        </button>
      )}
    </div>
  );
}
