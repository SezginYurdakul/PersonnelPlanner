"""Roles (a shift only goes to people holding its role), preferred roles, groups (fairness within each group) and spare people standing by for short shifts."""
from datetime import date, datetime, time, timedelta

import pytest

from roster import ProblemError, parse, plan

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


def test_people_stay_on_the_roles_they_prefer():
    # Both can work either station all week; each is kept on the one they prefer.
    data = problem(1, shifts(1, ['day'], ['dough', 'oven'], 's'), [
        dict(person(1, ['dough', 'oven']), preference_penalties={'oven': 3}),
        dict(person(2, ['dough', 'oven']), preference_penalties={'dough': 3}),
    ])
    report = plan(data)
    assert report['summary']['coverage_missing'] == 0
    held = {row['employee_id']: {s['shift_id'].split('|')[-1] for s in row['shifts']} for row in report['weeks'][0]['employees']}
    assert held == {1: {'dough'}, 2: {'oven'}}
    assert next(s for s in report['stages'] if s['name'] == 'preference')['value'] == 0
    assert {row['employee_id']: row['off_preference'] for row in report['totals']} == {1: 0, 2: 0}


def test_coverage_moves_people_off_the_role_they_prefer():
    # Both prefer dough, but the oven needs someone every day: one of them works it each day.
    data = problem(1, shifts(1, ['day'], ['dough', 'oven'], 's'), [
        dict(person(1, ['dough', 'oven']), preference_penalties={'oven': 3}),
        dict(person(2, ['dough', 'oven']), preference_penalties={'oven': 3}),
    ])
    report = plan(data)
    assert report['summary']['coverage_missing'] == 0
    assert report['summary']['preference_penalty'] == 15
    assert next(s for s in report['stages'] if s['name'] == 'preference')['value'] == 15
    off = [r for r in report['relaxed'] if r['rule'] == 'preference']
    assert len(off) == 5 and all(r['tier'] == 6 and r['penalty'] == 3 and r['shift_id'].endswith('|oven') for r in off)


def test_rotation_outranks_the_role_people_prefer():
    # The machine runs nights, dough days. P1 prefers the machine, but worked nights last week,
    # so the rotation sends P1 to days - and to dough - while P2 moves on to the machine nights.
    data = problem(1, shifts(1, ['night'], ['machine'], 's') + shifts(1, ['day'], ['dough'], 's'), [
        dict(person(1, ['machine', 'dough']), preference_penalties={'dough': 3}, previous_type='night'),
        dict(person(2, ['machine', 'dough']), previous_type='day'),
    ])
    data['rotation'] = ['day', 'night']
    report = plan(data)
    assert report['summary']['coverage_missing'] == 0
    held = {row['employee_id']: {s['shift_id'].split('|')[-1] for s in row['shifts']} for row in report['weeks'][0]['employees']}
    assert held == {1: {'dough'}, 2: {'machine'}}
    assert report['summary']['rotation_breaks'] == 0
    assert report['summary']['preference_penalty'] == 15
    assert {r['tier'] for r in report['relaxed'] if r['rule'] == 'preference'} == {6}



def spare_lead_problem(lead_hours):
    """Five day lead shifts (one or two leads), each backing up that day's dough shift. The
    operator works three of the five dough shifts; the leads only lead."""
    lead_shifts = [
        dict(s, max=2, spare_for=[s['id'].replace('l|', 's|').replace('|lead', '|dough')])
        for s in shifts(1, ['day'], ['lead'], 'l')
    ]
    return problem(1, lead_shifts + shifts(1, ['day'], ['dough'], 's'), [
        person(1, ['lead'], 'leads'),
        person(2, ['lead'], 'leads', hours=lead_hours),
        person(3, ['dough'], 'operators', hours=24),
    ])


def days_with(report, role, at_least):
    staffed = {}
    for row in report['weeks'][0]['employees']:
        for s in row['shifts']:
            if s['shift_id'].endswith(f'|{role}'):
                staffed[s['date']] = staffed.get(s['date'], 0) + 1
    return {day for day, count in staffed.items() if count >= at_least}


def test_a_spare_lead_stands_by_on_the_day_a_station_is_short():
    # The second lead's two spare shifts land on the two days dough has nobody.
    report = plan(spare_lead_problem(16))
    assert report['summary']['coverage_missing'] == 2
    open_days = {s['date'] for s in report['relaxed'] if s['rule'] == 'coverage'}
    assert days_with(report, 'lead', 2) == open_days
    assert report['summary']['unbacked_gaps'] == 0
    assert next(s for s in report['stages'] if s['name'] == 'spare')['value'] == 0
    assert all(s['shift_id'].endswith('|lead') for row in report['weeks'][0]['employees'] if row['employee_id'] != 3 for s in row['shifts'])


def test_a_short_shift_nobody_spare_backs_is_counted():
    # One spare lead shift for two open dough shifts: one is backed, one isn't.
    report = plan(spare_lead_problem(8))
    assert report['summary']['coverage_missing'] == 2
    assert len(days_with(report, 'lead', 2)) == 1
    assert report['summary']['unbacked_gaps'] == 1
    assert next(s for s in report['stages'] if s['name'] == 'spare')['value'] == 1


def test_spare_for_must_name_other_shifts():
    data = spare_lead_problem(16)
    data['shifts'][0]['spare_for'] = ['nope']
    with pytest.raises(ProblemError, match=r'shifts\[0\].spare_for'):
        parse(data)
