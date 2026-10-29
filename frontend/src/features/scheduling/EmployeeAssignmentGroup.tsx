import { useDraggable, useDroppable } from '@dnd-kit/core';
import type { ShiftAssignment } from '../../types/scheduling';
import { ScheduleAssignmentCard } from './ScheduleAssignmentCard';

interface EmployeeAssignmentGroupProps {
  cellId: string;
  employeeId: number;
  lineId: number;
  shiftPatternId: number;
  workDate: string;
  assignments: ShiftAssignment[];
  onAssignmentClick: (assignment: ShiftAssignment) => void;
}

export function EmployeeAssignmentGroup({
  cellId,
  employeeId,
  lineId,
  shiftPatternId,
  workDate,
  assignments,
  onAssignmentClick,
}: EmployeeAssignmentGroupProps) {
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: `group-${cellId}-${employeeId}`,
    data: { groupAssignments: assignments },
  });

  // Lets an unfilled/open slot (see ScheduleGridCell's UnfilledSlotButton) be dropped
  // straight onto this person to assign them to it - keyed on employeeId rather than any
  // one of their existing assignments, since it's the PERSON being targeted.
  const { setNodeRef: setDropRef, isOver } = useDroppable({
    id: `person-${cellId}-${employeeId}`,
    data: { employeeId, lineId, shiftPatternId, workDate },
  });

  const dropHighlight = isOver ? 'ring-2 ring-x-gold' : '';

  if (assignments.length === 1) {
    return (
      <div ref={setDropRef} className={`rounded-lg ${dropHighlight}`}>
        <ScheduleAssignmentCard assignment={assignments[0]} onClick={() => onAssignmentClick(assignments[0])} />
      </div>
    );
  }

  const first = assignments[0];
  const style = transform
    ? { transform: `translate3d(${transform.x}px, ${transform.y}px, 0)`, zIndex: 20 }
    : undefined;

  return (
    <div
      ref={setDropRef}
      className={`rounded-lg border-2 border-slate-300 bg-white shadow ${isDragging ? 'opacity-50 shadow-lg' : ''} ${dropHighlight}`}
    >
      <div
        ref={setNodeRef}
        style={style}
        {...listeners}
        {...attributes}
        className="cursor-grab truncate rounded-t-lg border-b-2 border-slate-300 bg-x-warmbg px-2 py-1 text-xs font-bold text-x-brown active:cursor-grabbing"
      >
        {first.employee.first_name} {first.employee.last_name}
      </div>
      <div className="space-y-1 p-1">
        {assignments.map((assignment) => (
          <ScheduleAssignmentCard
            key={assignment.id}
            assignment={assignment}
            onClick={() => onAssignmentClick(assignment)}
            showName={false}
          />
        ))}
      </div>
    </div>
  );
}
