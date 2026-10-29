"""Turns the JSON problem into plain indexed data for the model and the report.

Nothing here is specific to one company: people, contracts, availability, open shifts, history,
weights and policies all come from the input. Conventions: weekdays are ISO (1 = Monday ..
7 = Sunday), dates are ISO dates, times are local ISO datetimes without a timezone (a shift
ending at midnight ends at 00:00 of the next day), hours may be fractional and are converted
to whole minutes. An empty object may also arrive as an empty list (PHP encodes both as
[]), so optional objects read `or {}`.
"""
from __future__ import annotations

import math
from collections import defaultdict
from dataclasses import dataclass
from datetime import date, datetime, timedelta

WEEKEND_POLICIES = ('fair', 'single_staffed')
MODES = ('fair', 'cost')
DEFAULT_STAGE_SECONDS = {
    'coverage': 10,
    'single_type': 10,
    'mixed_share': 10,
    'contract': 10,
    'over_max': 10,
    'short_share': 10,
    'rotation': 30,
    'fairness': 60,
    'weekend': 30,
    'cost': 30,
}
# Proportional fairness multiplies counts by lcm(shares) / share; past this the lcm is
# approximated, which only makes the proportions slightly inexact.
MAX_FAIRNESS_SCALE = 100_000


class ProblemError(ValueError):
    """The input is malformed; the message names the offending field."""


@dataclass(frozen=True)
class Shift:
    index: int
    id: object
    date: date
    week: int
    weekday: int
    type: str
    start: datetime
    end: datetime
    minutes: int
    paid_minutes: int  # minutes as paid, surcharges included - what cost is measured in
    min: int
    max: int | None
    role: str | None  # only people holding this role can work it; None = anyone


@dataclass(frozen=True)
class Dimension:
    """A fairness count, e.g. night shifts (types) or Sundays (weekdays)."""

    key: str
    types: frozenset[str] | None
    weekdays: frozenset[int] | None
    weight: int

    def matches(self, shift: Shift) -> bool:
        return (self.types is None or shift.type in self.types) and (
            self.weekdays is None or shift.weekday in self.weekdays
        )


@dataclass
class Employee:
    index: int
    id: object
    name: str
    max_minutes: list[int]  # per week, never exceeded
    target_minutes: list[int]  # per week: contract minimum, capped by what availability allows
    can_work: frozenset[int]  # shift indexes allowed by recurring availability (ignores leave)
    available: frozenset[int]  # can_work minus leave and rest conflicts with earlier shifts
    usable_types: frozenset[str]  # shift types in can_work; more than one = can mix types
    next_type: dict[str, str]  # rotation step per shift type; empty = exempt (one usable type)
    previous_type: str | None  # shift type of the week before the horizon
    history_counts: dict[str, int]
    group: str  # fairness and shares compare people within one group only
    cost_cents: int  # hourly cost in cents (cost mode)
    fair_coef: int = 0  # makes fairness counts proportional to contract hours; 0 = not compared


@dataclass
class Problem:
    horizon_start: date
    weeks: int
    types: list[str]
    shifts: list[Shift]
    employees: list[Employee]
    rest_conflicts: list[tuple[int, int]]  # shift index pairs one person can't both work
    max_shifts_per_day: int
    weekend_policy: str
    mode: str  # fair: fairness after rotation; cost: labour cost after rotation, then fairness
    weekend_weekdays: frozenset[int]
    dimensions: list[Dimension]
    rotation_break_weight: int
    fair_unit: int  # what one shift of a person with the largest share counts in fairness terms
    stage_seconds: dict[str, float]
    workers: int

    def week_start(self, week: int) -> date:
        return self.horizon_start + timedelta(weeks=week)


def parse(data: dict) -> Problem:
    if not isinstance(data, dict):
        raise ProblemError('the problem must be a JSON object')
    horizon_start = _date(_required(data, 'horizon_start', 'problem'), 'horizon_start')
    weeks = _int(data.get('weeks', 1), 'weeks', minimum=1)

    types = _required(data, 'shift_types', 'problem')
    if not isinstance(types, list) or not types or len(set(types)) != len(types):
        raise ProblemError('shift_types must be a non-empty list of unique keys')

    rotation = data.get('rotation') or []
    if any(t not in types for t in rotation) or len(set(rotation)) != len(rotation):
        raise ProblemError('rotation must list unique keys from shift_types')

    rules = data.get('rules') or {}
    min_rest = timedelta(minutes=_minutes(rules.get('min_rest_hours', 11), 'rules.min_rest_hours'))
    max_per_day = _int(rules.get('max_shifts_per_day', 1), 'rules.max_shifts_per_day', minimum=1)
    policy = rules.get('weekend_policy', 'fair')
    mode = rules.get('mode', 'fair')
    if mode not in MODES:
        raise ProblemError(f'rules.mode must be one of {", ".join(MODES)}')
    if policy not in WEEKEND_POLICIES:
        raise ProblemError(f'rules.weekend_policy must be one of {", ".join(WEEKEND_POLICIES)}')
    weekend = frozenset(_weekday(d, 'rules.weekend_weekdays') for d in rules.get('weekend_weekdays', [6, 7]))

    shifts = [
        _shift(i, raw, f'shifts[{i}]', horizon_start, weeks, types)
        for i, raw in enumerate(_required(data, 'shifts', 'problem'))
    ]
    if len({s.id for s in shifts}) != len(shifts):
        raise ProblemError('shift ids must be unique')

    employees, shares = [], []
    for i, raw in enumerate(_required(data, 'employees', 'problem')):
        employee, share = _employee(i, raw, f'employees[{i}]', shifts, weeks, horizon_start, types, rotation, min_rest, max_per_day)
        employees.append(employee)
        shares.append(share)
    if len({e.id for e in employees}) != len(employees):
        raise ProblemError('employee ids must be unique')
    fair_unit = _set_fairness_coefficients(employees, shares)

    fairness = data.get('fairness') or {}
    dimensions = [_dimension(raw, f'fairness.dimensions[{i}]', types) for i, raw in enumerate(fairness.get('dimensions', []))]
    if len({d.key for d in dimensions}) != len(dimensions):
        raise ProblemError('fairness dimension keys must be unique')

    solver = data.get('solver') or {}
    stage_seconds = {**DEFAULT_STAGE_SECONDS, **(solver.get('stage_seconds') or {})}
    if set(stage_seconds) != set(DEFAULT_STAGE_SECONDS):
        raise ProblemError(f'solver.stage_seconds keys must be among {", ".join(DEFAULT_STAGE_SECONDS)}')

    return Problem(
        horizon_start=horizon_start,
        weeks=weeks,
        types=types,
        shifts=shifts,
        employees=employees,
        rest_conflicts=_rest_conflicts(shifts, min_rest),
        max_shifts_per_day=max_per_day,
        weekend_policy=policy,
        mode=mode,
        weekend_weekdays=weekend,
        dimensions=[d for d in dimensions if d.weight > 0],
        rotation_break_weight=_int(fairness.get('rotation_break_weight', 0), 'fairness.rotation_break_weight', minimum=0),
        fair_unit=fair_unit,
        stage_seconds={k: float(v) for k, v in stage_seconds.items()},
        workers=_int(solver.get('workers', 8), 'solver.workers', minimum=1),
    )


def _shift(index: int, raw: dict, path: str, horizon_start: date, weeks: int, types: list[str]) -> Shift:
    day = _date(_required(raw, 'date', path), f'{path}.date')
    week = (day - horizon_start).days // 7
    if not 0 <= week < weeks:
        raise ProblemError(f'{path}.date {day} is outside the {weeks}-week horizon')
    kind = _required(raw, 'type', path)
    if kind not in types:
        raise ProblemError(f'{path}.type {kind!r} is not in shift_types')
    start = _datetime(_required(raw, 'start', path), f'{path}.start')
    end = _datetime(_required(raw, 'end', path), f'{path}.end')
    if end <= start:
        raise ProblemError(f'{path}.end must be after its start')
    low = _int(raw.get('min', 0), f'{path}.min', minimum=0)
    high = raw.get('max')
    return Shift(
        index=index,
        id=_required(raw, 'id', path),
        date=day,
        week=week,
        weekday=day.isoweekday(),
        type=kind,
        start=start,
        end=end,
        minutes=int((end - start).total_seconds()) // 60,
        paid_minutes=int((end - start).total_seconds()) // 60 if raw.get('paid_hours') is None else _minutes(raw['paid_hours'], f'{path}.paid_hours'),
        min=low,
        max=None if high is None else _int(high, f'{path}.max', minimum=max(low, 1)),
        role=None if raw.get('role') is None else str(raw['role']),
    )


def _employee(index, raw, path, shifts, weeks, horizon_start, types, rotation, min_rest, max_per_day) -> tuple[Employee, int]:
    min_by_week = [_minutes(raw.get('min_hours', 0), f'{path}.min_hours')] * weeks
    max_by_week = [_minutes(_required(raw, 'max_hours', path), f'{path}.max_hours')] * weeks
    for start, hours in (raw.get('week_hours') or {}).items():
        offset = (_date(start, f'{path}.week_hours') - horizon_start).days
        if offset % 7 or not 0 <= offset // 7 < weeks:
            raise ProblemError(f'{path}.week_hours key {start} is not a week start inside the horizon')
        if 'min_hours' in hours:
            min_by_week[offset // 7] = _minutes(hours['min_hours'], f'{path}.week_hours.{start}.min_hours')
        if 'max_hours' in hours:
            max_by_week[offset // 7] = _minutes(hours['max_hours'], f'{path}.week_hours.{start}.max_hours')

    blocked = set()  # (weekday, shift type or None for every type)
    for j, rule in enumerate(raw.get('unavailable', [])):
        kind = rule.get('type')
        if kind is not None and kind not in types:
            raise ProblemError(f'{path}.unavailable[{j}].type {kind!r} is not in shift_types')
        blocked.add((_weekday(rule.get('weekday'), f'{path}.unavailable[{j}].weekday'), kind))
    absent = set()  # (date, shift type or None for the whole day)
    for j, rule in enumerate(raw.get('absences', [])):
        absent.add((_date(rule.get('date'), f'{path}.absences[{j}].date'), rule.get('type')))
    earlier = [
        (_datetime(_required(s, 'start', f'{path}.previous_shifts[{j}]'), f'{path}.previous_shifts[{j}].start'),
         _datetime(_required(s, 'end', f'{path}.previous_shifts[{j}]'), f'{path}.previous_shifts[{j}].end'))
        for j, s in enumerate(raw.get('previous_shifts', []))
    ]

    roles = raw.get('roles')
    roles = frozenset(str(r) for r in roles) if roles is not None else frozenset()
    can_work = frozenset(
        s.index for s in shifts
        if (s.weekday, None) not in blocked and (s.weekday, s.type) not in blocked
        and (s.role is None or s.role in roles)
    )
    available = frozenset(
        i for i in can_work
        if (shifts[i].date, None) not in absent and (shifts[i].date, shifts[i].type) not in absent
        and not any(_too_close(start, end, shifts[i].start, shifts[i].end, min_rest) for start, end in earlier)
    )

    # Most minutes the person could work each week: the longest available shifts per date.
    capacity = [0] * weeks
    by_date = defaultdict(list)
    for i in available:
        by_date[shifts[i].date].append(shifts[i].minutes)
    for day, minutes in by_date.items():
        capacity[(day - horizon_start).days // 7] += sum(sorted(minutes, reverse=True)[:max_per_day])
    target = [min(min_by_week[w], max_by_week[w], capacity[w]) for w in range(weeks)]

    # Rotation: the next type in the cycle this person can actually work. Someone who can work
    # only one type of the cycle (e.g. day only) is exempt.
    usable = {shifts[i].type for i in can_work}
    next_type = {}
    if sum(t in usable for t in rotation) > 1:
        for pos, kind in enumerate(rotation):
            following = (rotation[(pos + step) % len(rotation)] for step in range(1, len(rotation) + 1))
            next_type[kind] = next(t for t in following if t in usable)

    previous_type = raw.get('previous_type')
    if previous_type is not None and previous_type not in types:
        raise ProblemError(f'{path}.previous_type {previous_type!r} is not in shift_types')

    history = raw.get('history') or {}
    # Fairness shares are proportional to contract hours (minimum hours, or maximum hours when
    # there is no minimum), past ones included, so part-timers get a proportional share.
    share = _minutes(history.get('hours', 0), f'{path}.history.hours') + sum(
        target[w] if min_by_week[w] > 0 else min(max_by_week[w], capacity[w]) for w in range(weeks)
    )
    employee = Employee(
        index=index,
        id=_required(raw, 'id', path),
        name=str(raw.get('name', raw['id'])),
        max_minutes=max_by_week,
        target_minutes=target,
        can_work=can_work,
        available=available,
        usable_types=frozenset(usable),
        next_type=next_type,
        previous_type=previous_type,
        history_counts={k: _int(v, f'{path}.history.counts.{k}', minimum=0) for k, v in (history.get('counts') or {}).items()},
        group=str(raw.get('group', '')),
        cost_cents=round(100 * _number(raw.get('hourly_cost', 0), f'{path}.hourly_cost')),
    )
    return employee, share


def _set_fairness_coefficients(employees: list[Employee], shares: list[int]) -> int:
    hours = {e.index: max(1, round(share / 60)) for e, share in zip(employees, shares) if share > 0}
    if not hours:
        return 1
    scale = min(math.lcm(*hours.values()), MAX_FAIRNESS_SCALE)
    for e in employees:
        e.fair_coef = max(1, round(scale / hours[e.index])) if e.index in hours else 0
    return max(1, round(scale / max(hours.values())))


def _dimension(raw: dict, path: str, types: list[str]) -> Dimension:
    kinds = raw.get('types')
    if kinds is not None and any(t not in types for t in kinds):
        raise ProblemError(f'{path}.types must be keys from shift_types')
    weekdays = raw.get('weekdays')
    return Dimension(
        key=str(_required(raw, 'key', path)),
        types=frozenset(kinds) if kinds else None,
        weekdays=frozenset(_weekday(d, f'{path}.weekdays') for d in weekdays) if weekdays else None,
        weight=_int(raw.get('weight', 1), f'{path}.weight', minimum=0),
    )


def _rest_conflicts(shifts: list[Shift], min_rest: timedelta) -> list[tuple[int, int]]:
    ordered = sorted(shifts, key=lambda s: s.start)
    pairs = []
    for i, first in enumerate(ordered):
        for second in ordered[i + 1:]:
            if second.start >= first.end + min_rest:
                break
            pairs.append((first.index, second.index))
    return pairs


def _too_close(a_start: datetime, a_end: datetime, b_start: datetime, b_end: datetime, min_rest: timedelta) -> bool:
    if a_start > b_start:
        a_start, a_end, b_start, b_end = b_start, b_end, a_start, a_end
    return b_start < a_end + min_rest


def _required(raw: dict, key: str, path: str):
    if not isinstance(raw, dict) or key not in raw or raw[key] is None:
        raise ProblemError(f'{path}.{key} is required')
    return raw[key]


def _int(value, path: str, minimum: int | None = None) -> int:
    if isinstance(value, bool) or not isinstance(value, int):
        raise ProblemError(f'{path} must be an integer')
    if minimum is not None and value < minimum:
        raise ProblemError(f'{path} must be at least {minimum}')
    return value


def _minutes(hours, path: str) -> int:
    if isinstance(hours, bool) or not isinstance(hours, (int, float)) or hours < 0:
        raise ProblemError(f'{path} must be a non-negative number of hours')
    return round(hours * 60)


def _number(value, path: str) -> float:
    if isinstance(value, bool) or not isinstance(value, (int, float)) or value < 0:
        raise ProblemError(f'{path} must be a non-negative number')
    return float(value)


def _weekday(value, path: str) -> int:
    if _int(value, path) not in range(1, 8):
        raise ProblemError(f'{path} must be an ISO weekday (1 = Monday .. 7 = Sunday)')
    return value


def _date(value, path: str) -> date:
    try:
        return date.fromisoformat(value)
    except (TypeError, ValueError):
        raise ProblemError(f'{path} must be an ISO date') from None


def _datetime(value, path: str) -> datetime:
    try:
        parsed = datetime.fromisoformat(value)
    except (TypeError, ValueError):
        raise ProblemError(f'{path} must be an ISO datetime') from None
    if parsed.tzinfo is not None:
        raise ProblemError(f'{path} must be a local time without a timezone')
    return parsed
