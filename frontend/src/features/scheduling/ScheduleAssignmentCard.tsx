import { useDraggable } from '@dnd-kit/core';
import type { ShiftAssignment } from '../../types/scheduling';

interface ScheduleAssignmentCardProps {
  assignment: ShiftAssignment;
  onClick?: () => void;
}

export function ScheduleAssignmentCard({ assignment, onClick }: ScheduleAssignmentCardProps) {
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: `assignment-${assignment.id}`,
    data: { assignment },
  });

  const start = assignment.starts_at ?? assignment.shift_pattern?.start_time ?? null;
  const end = assignment.ends_at ?? assignment.shift_pattern?.end_time ?? null;
  const isSecondaryTask = assignment.role?.role_kind === 'secondary_task';

  const style = transform
    ? { transform: `translate3d(${transform.x}px, ${transform.y}px, 0)`, zIndex: 20 }
    : undefined;

  return (
    <div
      ref={setNodeRef}
      style={style}
      {...listeners}
      {...attributes}
      onClick={onClick}
      data-assignment-id={assignment.id}
      className={`cursor-grab rounded-lg border px-2 py-1.5 text-xs shadow-sm transition-shadow active:cursor-grabbing ${
        isDragging ? 'opacity-50 shadow-lg' : ''
      } ${
        isSecondaryTask
          ? 'border-blue-200 bg-blue-50 text-blue-900'
          : 'border-x-border bg-white text-x-brown'
      }`}
    >
      <div className="flex items-center justify-between gap-1">
        <span className="truncate font-bold">
          {assignment.employee.first_name} {assignment.employee.last_name}
        </span>
        {assignment.source === 'manual' ? (
          <i className="fa-solid fa-hand text-[10px] text-amber-600" title="Manually assigned" />
        ) : (
          <i className="fa-solid fa-wand-magic-sparkles text-[10px] text-slate-400" title="Auto-suggested" />
        )}
      </div>
      {assignment.role && <div className="truncate text-[11px] text-slate-500">{assignment.role.name}</div>}
      {start && end && (
        <div className="tabular-nums text-[11px] text-slate-400">
          {start.slice(0, 5)}–{end.slice(0, 5)}
        </div>
      )}
    </div>
  );
}
