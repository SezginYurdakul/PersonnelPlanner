import type { Candidate, RankingMode } from '../../types/scheduling';

function hours(value: number): string {
  return `${Number(value.toFixed(1))}`;
}

/**
 * A candidate's load in the assign and "who else" lists: their hours in this plan this week
 * against their contract, then what they are ranked by - this shift's cost, or their fairness
 * points (weighted hours over the last 8 approved weeks plus this week, so not this week's
 * hours, hence not shown in hours).
 */
export function CandidateLoad({ candidate, rankingMode }: { candidate: Candidate; rankingMode: RankingMode }) {
  const contract = candidate.contract_hours;
  const over = contract !== null && candidate.week_hours !== null && candidate.week_hours > contract.max;

  return (
    <>
      {candidate.week_hours !== null && (
        <span
          className={`font-semibold tabular-nums ${over ? 'text-rose-600' : 'text-slate-600'}`}
          title="Hours in this plan this week / contract hours"
        >
          {hours(candidate.week_hours)}
          {contract &&
            ` / ${
              contract.min !== null && contract.min !== contract.max
                ? `${hours(contract.min)}–${hours(contract.max)}`
                : hours(contract.max)
            }`}
          h
        </span>
      )}
      {candidate.score !== null &&
        (rankingMode === 'cost' ? (
          <span className="tabular-nums" title="Estimated cost for this shift">
            €{candidate.score.toFixed(2)}
          </span>
        ) : (
          <span
            className="tabular-nums text-slate-400"
            title="Fairness points: weighted hours over the last 8 approved weeks plus this week - the lowest is ranked first"
          >
            <i className="fa-solid fa-scale-balanced mr-1" />
            {Math.round(candidate.score)} pts
          </span>
        ))}
    </>
  );
}
