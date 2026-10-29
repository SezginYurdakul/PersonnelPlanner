import { useDraggable } from '@dnd-kit/core';
import type { ShiftAssignment } from '../../types/scheduling';
import { RoleKindIcon } from './RoleKindIcon';

interface ScheduleAssignmentCardProps {
  assignment: ShiftAssignment;
  onClick?: () => void;
  showName?: boolean;
}

export function ScheduleAssignmentCard({ assignment, onClick, showName = true }: ScheduleAssignmentCardProps) {
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

  const roleIcon = assignment.role && <RoleKindIcon roleKind={assignment.role.role_kind} />;

  return (
    <div
      ref={setNodeRef}
      style={style}
      {...listeners}
      {...attributes}
      onClick={onClick}
      data-assignment-id={assignment.id}
      className={`cursor-grab rounded-lg border-y border-r-2 px-2 py-1.5 text-xs shadow transition-shadow active:cursor-grabbing ${
        isDragging ? 'opacity-50 shadow-lg' : ''
      } ${
        isSecondaryTask
          ? 'border-x-gold-dark/70 border-l-[5px] border-l-x-gold-dark bg-amber-100 text-x-brown'
          : 'border-slate-300 border-l-[5px] border-l-slate-500 bg-white text-x-brown'
      }`}
    >
      <div className="flex items-center justify-between gap-1">
        {showName ? (
          <span className="truncate font-bold">
            {assignment.employee.first_name} {assignment.employee.last_name}
          </span>
        ) : (
          assignment.role && (
            <span className="flex items-center gap-1 truncate text-[11px] text-slate-500">
              {roleIcon}
              {assignment.role.name}
            </span>
          )
        )}
        {assignment.source === 'manual' ? (
          <i className="fa-solid fa-hand text-[10px] text-amber-600" title="Manually assigned" />
        ) : (
          <i className="fa-solid fa-wand-magic-sparkles text-[10px] text-slate-400" title="Auto-suggested" />
        )}
      </div>
      {showName && assignment.role && (
        <div className="flex items-center gap-1 truncate text-[11px] text-slate-500">
          {roleIcon}
          {assignment.role.name}
        </div>
      )}
      {start && end && (
        <div className="tabular-nums text-[11px] text-slate-400">
          {start.slice(0, 5)}–{end.slice(0, 5)}
        </div>
      )}
    </div>
  );
}
