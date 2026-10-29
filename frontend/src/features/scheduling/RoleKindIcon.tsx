import type { SchedulingRole } from '../../types/lines';

// One small icon standing in for role_kind everywhere a role name is shown on the grid
// (filled assignment cards and unfilled/open slot buttons alike) - color alone (e.g.
// blocking-red vs advisory-amber) already carries a different meaning, so station vs
// secondary task needs its own cue that still reads correctly regardless of whatever color
// context it's dropped into. A gear vs. a checklist is a much bigger visual (and semantic -
// "a machine" vs. "a task to tick off") difference at a glance than two small geometric
// outlines (square/diamond) ever were.
export function RoleKindIcon({ roleKind, tone }: { roleKind: SchedulingRole['role_kind']; tone?: string }) {
  const isSecondaryTask = roleKind === 'secondary_task';

  return (
    <i
      className={`fa-solid ${isSecondaryTask ? 'fa-list-check' : 'fa-gear'} shrink-0 text-[10px] ${
        tone ?? (isSecondaryTask ? 'text-x-gold-dark' : 'text-slate-500')
      }`}
      title={isSecondaryTask ? 'Secondary task' : 'Station'}
    />
  );
}
