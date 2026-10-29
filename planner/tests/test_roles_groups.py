"""Roles (a shift only goes to people holding its role) and groups (fairness within each group)."""
from datetime import date, datetime, time, timedelta

from roster import parse, plan

START = date(2026, 11, 1)  # Sunday
STARTS = {'night': time(0), 'day': time(8)}


def shifts(weeks, kinds, roles, prefix):
    """Sunday-Thursday, one shift per kind and role, each needing exactly one person."""
    out = []
    for offset in range(weeks * 7):
        day = START + timedelta(days=offset)
        if day.isoweekday() in (5, 6):
            continue
        for kind in kinds:
            for role in roles:
                begin = datetime.combine(day, STARTS[kind])
                out.append({
                    'id': f'{prefix}|{day}|{kind}|{role}', 'date': day.isoformat(), 'type': kind, 'role': role,
                    'start': begin.isoformat(), 'end': (begin + timedelta(hours=8)).isoformat(), 'min': 1, 'max': 1,
                })
    return out


def person(id, roles, group='', hours=40):
    return {'id': id, 'name': f'P{id}', 'min_hours': hours, 'max_hours': hours, 'roles': roles, 'group': group}


def problem(weeks, shift_list, people):
    return {
        'horizon_start': START.isoformat(),
        'weeks': weeks,
        'shift_types': ['night', 'day'],
        'fairness': {'dimensions': [{'key': 'night', 'types': ['night'], 'weight': 1}]},
        'shifts': shift_list,
        'employees': people,
        'solver': {'stage_seconds': {'fairness': 10, 'weekend': 5}},
    }


def test_shifts_go_only_to_people_holding_the_role():
    data = problem(1, shifts(1, ['day'], ['dough', 'oven'], 's'), [person(1, ['dough']), person(2, ['oven']), person(3, [])])
    parsed = parse(data)
    assert parsed.employees[2].can_work == frozenset()
    report = plan(data)
    assert report['summary']['coverage_missing'] == 0
    for row in report['weeks'][0]['employees']:
        held = {s['shift_id'].split('|')[-1] for s in row['shifts']}
        assert held == {'dough' if row['employee_id'] == 1 else 'oven'}


def test_fairness_compares_people_within_their_group():
    # Leads cover one night and one day shift a day, operators two night stations: within each
    # group nights can be even (leads 5 each, operators 10 each), across groups they can't.
    data = problem(
        2,
        shifts(2, ['night', 'day'], ['lead'], 'l') + shifts(2, ['night'], ['oven', 'dough'], 's'),
        [person(1, ['lead'], 'leads'), person(2, ['lead'], 'leads'), person(3, ['oven', 'dough'], 'operators'), person(4, ['oven', 'dough'], 'operators')],
    )
    report = plan(data)
    assert report['summary']['coverage_missing'] == 0
    nights = {row['employee_id']: row['night'] for row in report['totals']}
    assert nights == {1: 5, 2: 5, 3: 10, 4: 10}
    assert next(s for s in report['stages'] if s['name'] == 'fairness')['value'] == 0
    assert {row['group'] for row in report['totals']} == {'leads', 'operators'}


def test_cost_mode_puts_the_cheaper_person_on_the_surcharged_shifts():
    # Nights pay 50% more. Both work 40h on one type a week, so only who takes nights differs:
    # A (20/h) on nights costs 5 x 12h x 20 + 5 x 8h x 30 = 2400, B on nights 2600.
    nights_and_days = shifts(1, ['night', 'day'], [None], 's')
    for shift in nights_and_days:
        if shift['type'] == 'night':
            shift['paid_hours'] = 12
    people = [dict(person(1, None), hourly_cost=20), dict(person(2, None), hourly_cost=30)]
    data = problem(1, nights_and_days, people)
    data['rules'] = {'mode': 'cost'}
    report = plan(data)
    nights = {row['employee_id']: row['night'] for row in report['totals']}
    assert nights == {1: 5, 2: 0}
    assert report['summary']['cost'] == 2400
    assert next(s for s in report['stages'] if s['name'] == 'cost')['optimal']
