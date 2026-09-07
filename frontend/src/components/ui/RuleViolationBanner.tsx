interface ViolationEntry {
  employeeName: string;
  message: string;
  assignmentId: number;
}

interface RuleViolationBannerProps {
  violations: ViolationEntry[];
  onJumpToAssignment?: (assignmentId: number) => void;
}

export function RuleViolationBanner({ violations, onJumpToAssignment }: RuleViolationBannerProps) {
  if (violations.length === 0) {
    return null;
  }

  return (
    <div className="rounded-2xl border border-amber-300 bg-amber-50 p-4">
      <h3 className="mb-2 flex items-center gap-2 text-sm font-extrabold text-amber-900">
        <i className="fa-solid fa-triangle-exclamation" />
        Rule violations ({violations.length})
      </h3>
      <ul className="space-y-1.5">
        {violations.map((violation, index) => (
          <li key={`${violation.assignmentId}-${index}`} className="text-xs text-amber-900">
            <button
              type="button"
              onClick={() => onJumpToAssignment?.(violation.assignmentId)}
              className="font-semibold underline decoration-dotted underline-offset-2 hover:text-amber-700"
            >
              {violation.employeeName}
            </button>
            {' — '}
            {violation.message}
          </li>
        ))}
      </ul>
    </div>
  );
}
