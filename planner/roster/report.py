"""The plan as JSON, rebuilt from the assignments alone (independent of the CP-SAT variables).

Every hard rule is re-checked here; a violation would be a model bug and raises. Every soft rule
the plan had to relax is listed with its tier, so the caller can show the planner exactly what
gave and why, and the summary totals can be compared with the stage objectives.
"""
from __future__ import annotations

from collections import defaultdict

from .model import Solution
from .problem import Problem


def build_report(problem: Problem, solution: Solution) -> dict:
    p = problem
    staff = defaultdict(list)  # shift index -> employee indexes
    worked = defaultdict(list)  # (employee index, week) -> shifts in start order
    for e, s in sorted(solution.assigned, key=lambda key: (key[0], p.shifts[key[1]].start)):
        staff[s].append(e)
        worked[e, p.shifts[s].week].append(p.shifts[s])
    _check_hard_rules(p, solution.assigned, worked)

    relaxed = []
    summary = defaultdict(int)
    for s in p.shifts:
        count = len(staff[s.index])
        where = {'shift_id': s.id, 'date': s.date.isoformat(), 'type': s.type}
        if count < s.min:
            relaxed.append({'rule': 'coverage', 'tier': 2, **where, 'assigned': count, 'min': s.min})
            summary['coverage_missing'] += s.min - count
        if s.max is not None and count > s.max:
            relaxed.append({'rule': 'max_per_shift', 'tier': 4, **where, 'assigned': count, 'max': s.max})
            summary['over_max_minutes'] += (count - s.max) * s.minutes
        if s.weekday in p.weekend_weekdays:
            summary['weekend_extras'] += max(0, count - max(s.min, 1))

    types_used = {}
    extra_types = defaultdict(int)  # employee index -> extra shift types over the horizon
    short = defaultdict(int)  # employee index -> contract minutes not filled over the horizon
    weeks = []
    for w in range(p.weeks):
        week_start = p.week_start(w).isoformat()
        rows = []
        for e in p.employees:
            shifts = worked.get((e.index, w), [])
            used = sorted({s.type for s in shifts}, key=p.types.index)
            types_used[e.index, w] = used
            minutes = sum(s.minutes for s in shifts)
            if len(used) > 1:
                relaxed.append({'rule': 'single_type', 'tier': 3, 'employee_id': e.id, 'week_start': week_start, 'types': used})
                summary['extra_shift_types'] += len(used) - 1
                extra_types[e.index] += len(used) - 1
            if minutes < e.target_minutes[w]:
                relaxed.append({
                    'rule': 'contract_hours', 'tier': 4, 'employee_id': e.id, 'week_start': week_start,
                    'hours': _hours(minutes), 'target_hours': _hours(e.target_minutes[w]),
                })
                summary['hours_short_minutes'] += e.target_minutes[w] - minutes
                short[e.index] += e.target_minutes[w] - minutes
            if shifts:
                rows.append({
                    'employee_id': e.id,
                    'name': e.name,
                    'types': used,
                    'hours': _hours(minutes),
                    'shifts': [{'shift_id': s.id, 'date': s.date.isoformat(), 'type': s.type} for s in shifts],
                })
        weeks.append({'start': week_start, 'employees': rows})

    breaks = defaultdict(int)
    for e in p.employees:
        if not e.next_type:
            continue
        for w in range(p.weeks):
            before = types_used[e.index, w - 1] if w else ([e.previous_type] if e.previous_type else [])
            wrong = [
                (kind, other)
                for kind in before if kind in e.next_type
                for other in types_used[e.index, w] if other in e.next_type and other != e.next_type[kind]
            ]
            if wrong:
                kind, other = wrong[0]
                relaxed.append({
                    'rule': 'rotation', 'tier': 5, 'employee_id': e.id, 'week_start': p.week_start(w).isoformat(),
                    'from': kind, 'to': other, 'expected': e.next_type[kind],
                })
                breaks[e.index] += 1
    summary['rotation_breaks'] = sum(breaks.values())

    totals = []
    for e in p.employees:
        shifts = [p.shifts[s] for (i, s) in solution.assigned if i == e.index]
        row = {'employee_id': e.id, 'name': e.name, 'group': e.group, 'hours': _hours(sum(s.minutes for s in shifts))}
        if any(e.target_minutes):
            row['hours_short'] = _hours(short[e.index])
        for dim in p.dimensions:
            row[dim.key] = sum(dim.matches(s) for s in shifts)
        if len(e.usable_types) > 1:
            row['extra_shift_types'] = extra_types[e.index]
        if e.next_type:
            row['rotation_breaks'] = breaks[e.index]
        totals.append(row)

    return {
        'status': 'ok' if all(stage.value is not None for stage in solution.stages) else 'partial',
        'summary': {
            'coverage_missing': summary['coverage_missing'],
            'extra_shift_types': summary['extra_shift_types'],
            'hours_short': _hours(summary['hours_short_minutes']),
            'over_max_hours': _hours(summary['over_max_minutes']),
            'rotation_breaks': summary['rotation_breaks'],
            'weekend_extras': summary['weekend_extras'],
            'cost': round(sum(p.employees[e].cost_cents * p.shifts[s].paid_minutes for e, s in solution.assigned) / 6000, 2),
        },
        'stages': [vars(stage) for stage in solution.stages],
        'relaxed': relaxed,
        'totals': totals,
        'weeks': weeks,
    }


def _check_hard_rules(p: Problem, assigned: frozenset, worked: dict) -> None:
    per_date = defaultdict(int)
    for e, s in assigned:
        if s not in p.employees[e].available:
            raise RuntimeError(f'{p.employees[e].name} was given unavailable shift {p.shifts[s].id}')
        per_date[e, p.shifts[s].date] += 1
    if any(count > p.max_shifts_per_day for count in per_date.values()):
        raise RuntimeError('someone was given more shifts on one day than allowed')
    for a, b in p.rest_conflicts:
        for e in p.employees:
            if (e.index, a) in assigned and (e.index, b) in assigned:
                raise RuntimeError(f'{e.name} works {p.shifts[a].id} and {p.shifts[b].id} without enough rest')
    for (e, w), shifts in worked.items():
        if sum(s.minutes for s in shifts) > p.employees[e].max_minutes[w]:
            raise RuntimeError(f'{p.employees[e].name} is over max hours in week {w}')


def _hours(minutes: int) -> float | int:
    return minutes // 60 if minutes % 60 == 0 else round(minutes / 60, 2)
