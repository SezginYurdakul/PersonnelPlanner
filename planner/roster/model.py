"""CP-SAT model: one boolean per (person, shift), the rules in the agreed priority order.

Tier 1 is hard and never relaxed: availability and leave, roles (a shift with a role only goes
to people holding it, e.g. a station's qualified operators), maximum weekly hours, minimum rest
between shifts, maximum shifts per day. Everything else is a penalty, minimised one stage at a
time; each stage is pinned at its best value before the next starts, so a lower tier can never
buy anything at the expense of a higher one. Because only tier 1 is hard, the model is always
feasible (nobody working satisfies it), so the solver always returns a plan and the report says
exactly which rules had to give.

  tier 2  coverage     every shift gets its minimum number of people
  tier 3  single_type  one shift type per person per week
          mixed_share  tie-break inside tier 3: whoever has to mix types, the mixed weeks are
                       shared out evenly (max-min spread per person)
  tier 4  contract     contract hours filled + no shift above its maximum (summed, in minutes)
          over_max     tie-break inside tier 4: rather short hours than an over-full shift
          short_share  next tie-break: whatever contract time can't be filled is shared out evenly
  tier 5  rotation     next week's type follows the rotation cycle
          fairness     even nights / Sundays / Saturdays / rotation breaks (weights from the input)
          weekend      weekend shifts at their minimum staffing; runs before fairness when the
                       weekend policy is single_staffed
          cost         mode cost only, right after rotation: hourly cost x paid hours (surcharges
                       included); fairness and weekend still follow as tie-breaks

Shares and fairness compare people within their own group (e.g. leads with leads, operators
with operators); the group spreads are summed.
"""
from __future__ import annotations

from collections import defaultdict
from dataclasses import dataclass

from ortools.sat.python import cp_model

from .problem import Problem


@dataclass
class Stage:
    name: str
    value: int | None  # None: no solution within the stage's time limit (later stages skipped)
    optimal: bool
    seconds: float


@dataclass
class Solution:
    assigned: frozenset[tuple[int, int]]  # (employee index, shift index)
    stages: list[Stage]


class RosterModel:
    def __init__(self, problem: Problem):
        self.problem = problem
        self.model = cp_model.CpModel()
        self.variables = []
        self.x = {}  # (employee, shift) -> works it
        self.y = {}  # (employee, week, type) -> works at least one shift of that type that week
        self.stages = []  # (name, penalty terms), in solve order
        self._build()

    def _bool(self, name: str):
        var = self.model.new_bool_var(name)
        self.variables.append(var)
        return var

    def _int(self, upper: int, name: str):
        var = self.model.new_int_var(0, upper, name)
        self.variables.append(var)
        return var

    def _build(self) -> None:
        p, m = self.problem, self.model
        for e in p.employees:
            for s in sorted(e.available):
                self.x[e.index, s] = self._bool(f'x_{e.index}_{s}')

        staff = defaultdict(list)
        by_employee_week = defaultdict(list)
        by_employee_date = defaultdict(list)
        for (e, s), var in self.x.items():
            shift = p.shifts[s]
            staff[s].append(var)
            by_employee_week[e, shift.week].append((shift, var))
            by_employee_date[e, shift.date].append(var)

        # Tier 1: shifts per day and rest between shifts (also across week boundaries).
        for on_date in by_employee_date.values():
            if len(on_date) > p.max_shifts_per_day:
                m.add(sum(on_date) <= p.max_shifts_per_day)
        for a, b in p.rest_conflicts:
            for e in p.employees:
                first, second = self.x.get((e.index, a)), self.x.get((e.index, b))
                if first is not None and second is not None:
                    m.add_bool_or([first.Not(), second.Not()])

        missing, over_minutes, weekend_extra = [], [], []
        for s in p.shifts:
            on = staff[s.index]
            if s.min > 0:
                short = self._int(s.min, f'short_{s.index}')
                m.add(sum(on) + short >= s.min)
                missing.append(short)
            if s.max is not None and len(on) > s.max:
                excess = self._int(len(on) - s.max, f'over_{s.index}')
                m.add(excess >= sum(on) - s.max)
                over_minutes.append(s.minutes * excess)
            floor = max(s.min, 1)
            if s.weekday in p.weekend_weekdays and len(on) > floor:
                extra = self._int(len(on) - floor, f'weekend_{s.index}')
                m.add(extra >= sum(on) - floor)
                weekend_extra.append(extra)

        extra_types, short_minutes = [], []
        mixed_by_employee, short_by_employee = defaultdict(list), defaultdict(list)
        for e in p.employees:
            for w in range(p.weeks):
                items = by_employee_week.get((e.index, w), [])
                worked = sum(s.minutes * var for s, var in items)
                if items:
                    m.add(worked <= e.max_minutes[w])  # tier 1
                if e.target_minutes[w] > 0:
                    gap = self._int(e.target_minutes[w], f'gap_{e.index}_{w}')
                    m.add(worked + gap >= e.target_minutes[w])
                    short_minutes.append(gap)
                    short_by_employee[e.index].append(gap)
                used = []
                for kind in p.types:
                    of_type = [var for s, var in items if s.type == kind]
                    if of_type:
                        flag = self._bool(f'y_{e.index}_{w}_{kind}')
                        for var in of_type:
                            m.add_implication(var, flag)
                        m.add(flag <= sum(of_type))
                        self.y[e.index, w, kind] = flag
                        used.append(flag)
                if len(used) > 1:
                    extra = self._int(len(used) - 1, f'mixed_{e.index}_{w}')
                    m.add(extra >= sum(used) - 1)
                    extra_types.append(extra)
                    mixed_by_employee[e.index].append(extra)

        # When a rule has to give, share the cost out instead of leaving it all to whoever it is
        # cheapest for later (e.g. least rotation): mixed weeks within tier 3, missing contract
        # minutes within tier 4, each right after its tier's own totals are minimised.
        # Shares, like fairness, are compared within each group (e.g. leads and operators).
        mixed_share, short_share = [], []
        for group, members in _by_group(p.employees).items():
            mixers = [e for e in members if len(e.usable_types) > 1]
            if extra_types and len(mixers) > 1:
                values = [sum(mixed_by_employee.get(e.index, [])) for e in mixers]
                mixed_share.append(self._spread(f'mixed_weeks_{group}', values, p.weeks * (len(p.types) - 1)))
            contracted = [e for e in members if short_by_employee.get(e.index)]
            if len(contracted) > 1:
                values = [sum(short_by_employee[e.index]) for e in contracted]
                short_share.append(self._spread(f'short_minutes_{group}', values, max(sum(e.target_minutes) for e in contracted)))

        breaks = self._rotation_breaks()
        fairness = self._fairness_terms(breaks)
        tier5 = [('fairness', fairness), ('weekend', weekend_extra)]
        if p.weekend_policy == 'single_staffed':
            tier5.reverse()
        if p.mode == 'cost':
            # Labour cost in cents x paid minutes: who works the surcharged shifts, and how
            # much of anyone beyond what the higher tiers already fixed.
            cost = [
                p.employees[e].cost_cents * p.shifts[s].paid_minutes * var
                for (e, s), var in self.x.items()
                if p.employees[e].cost_cents and p.shifts[s].paid_minutes
            ]
            tier5.insert(0, ('cost', cost))
        self.stages = [
            ('coverage', missing),
            ('single_type', extra_types),
            ('mixed_share', mixed_share),
            ('contract', short_minutes + over_minutes),
            ('over_max', over_minutes),
            ('short_share', short_share),
            ('rotation', [flag for flags in breaks.values() for flag in flags]),
            *tier5,
        ]

    def _rotation_breaks(self) -> dict[int, list]:
        """One flag per person and week, set when the week's type doesn't follow last week's."""
        p, m = self.problem, self.model
        breaks = defaultdict(list)
        for e in p.employees:
            if not e.next_type:
                continue
            for w in range(p.weeks):
                pairs = []  # (last week's literal or True, this week's literal) that break the cycle
                for kind, expected in e.next_type.items():
                    if w == 0:
                        before = True if e.previous_type == kind else None
                    else:
                        before = self.y.get((e.index, w - 1, kind))
                    if before is None:
                        continue
                    for other in e.next_type:
                        after = self.y.get((e.index, w, other))
                        if other != expected and after is not None:
                            pairs.append((before, after))
                if not pairs:
                    continue
                flag = self._bool(f'break_{e.index}_{w}')
                for before, after in pairs:
                    if before is True:
                        m.add_implication(after, flag)
                    else:
                        m.add_bool_or([before.Not(), after.Not(), flag])
                breaks[e.index].append(flag)
        return breaks

    def _fairness_terms(self, breaks: dict[int, list]) -> list:
        """Weighted max-min spreads within each group; counts are scaled so shares follow contract hours."""
        p = self.problem
        terms = []
        for group, members in _by_group(p.employees).items():
            for dim in p.dimensions:
                matching = [s for s in p.shifts if dim.matches(s)]
                eligible = [e for e in members if e.fair_coef and any(s.index in e.can_work for s in matching)]
                if len(eligible) < 2:
                    continue
                values = [
                    e.fair_coef * (e.history_counts.get(dim.key, 0) + sum(self.x[e.index, s.index] for s in matching if (e.index, s.index) in self.x))
                    for e in eligible
                ]
                upper = max(e.fair_coef * (e.history_counts.get(dim.key, 0) + len(matching)) for e in eligible)
                terms.append(dim.weight * self._spread(f'fair_{dim.key}_{group}', values, upper))

            rotating = [e for e in members if e.next_type]
            if p.rotation_break_weight and len(rotating) > 1:
                values = [p.fair_unit * (e.history_counts.get('rotation_breaks', 0) + sum(breaks.get(e.index, []))) for e in rotating]
                upper = p.fair_unit * (max(e.history_counts.get('rotation_breaks', 0) for e in rotating) + p.weeks)
                terms.append(p.rotation_break_weight * self._spread(f'fair_rotation_breaks_{group}', values, upper))
        return terms

    def _spread(self, name: str, values: list, upper: int):
        high, low = self._int(upper, f'{name}_high'), self._int(upper, f'{name}_low')
        for value in values:
            self.model.add(high >= value)
            self.model.add(low <= value)
        return high - low


def _by_group(employees: list) -> dict[str, list]:
    groups = defaultdict(list)
    for e in employees:
        groups[e.group].append(e)
    return groups


def solve(problem: Problem) -> Solution:
    roster = RosterModel(problem)
    model = roster.model
    solver = cp_model.CpSolver()
    solver.parameters.num_workers = problem.workers
    assigned = frozenset()
    stages = []
    for name, terms in roster.stages:
        if not terms:
            stages.append(Stage(name, 0, True, 0.0))
            continue
        penalty = sum(terms)
        model.minimize(penalty)
        solver.parameters.max_time_in_seconds = problem.stage_seconds[name]
        status = solver.solve(model)
        if status not in (cp_model.OPTIMAL, cp_model.FEASIBLE):
            # Out of time before any solution: keep the previous stage's plan, which satisfies
            # every pinned tier, and leave the remaining (lower) tiers unoptimised.
            stages.append(Stage(name, None, False, round(solver.wall_time, 2)))
            break
        best = round(solver.objective_value)
        stages.append(Stage(name, best, status == cp_model.OPTIMAL, round(solver.wall_time, 2)))
        assigned = frozenset(key for key, var in roster.x.items() if solver.value(var))
        model.add(penalty <= best)
        model.clear_hints()
        for var in roster.variables:
            model.add_hint(var, solver.value(var))
    return Solution(assigned, stages)
