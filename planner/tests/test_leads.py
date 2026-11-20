"""One solver, four lead teams: today's six, one fewer, one more, and Erdal without his day-only limit.

The tier values asserted below are lower bounds worked out by hand for each team (see the comments);
the solver has to reach them, which makes them optimal. It must also prove them itself, except where
a test lists the stage as unproven.
"""
import json
import subprocess
import sys
from datetime import date
from pathlib import Path

import pytest

from leads import DAY_ONLY, current_leads, lead, problem
from roster import ProblemError, parse, plan


def check(report, unproven=()):
    """The stage objectives must agree with what the report recomputes from the assignments alone."""
    assert report['status'] == 'ok'
    stages = {stage['name']: stage for stage in report['stages']}
    for name in ('coverage', 'single_type', 'mixed_share', 'contract', 'over_max', 'short_share', 'rotation'):
        if name not in unproven:
            assert stages[name]['optimal'], f'{name} not proven optimal: {stages[name]}'
    summary = report['summary']
    assert stages['coverage']['value'] == summary['coverage_missing']
    assert stages['single_type']['value'] == summary['extra_shift_types']
    assert stages['mixed_share']['value'] == spread(per_lead(report, 'extra_shift_types'))
    assert stages['contract']['value'] == round((summary['hours_short'] + summary['over_max_hours']) * 60)
    assert stages['over_max']['value'] == round(summary['over_max_hours'] * 60)
    assert stages['short_share']['value'] == round(spread(per_lead(report, 'hours_short')) * 60)
    assert stages['preference']['value'] == summary['preference_penalty'] == 0
    assert stages['rotation']['value'] == summary['rotation_breaks']
    assert summary['weekend_extras'] <= stages['weekend']['value']
    return summary


def per_lead(report, key):
    return {row['name']: row[key] for row in report['totals'] if key in row}


def spread(counts):
    return max(counts.values()) - min(counts.values()) if counts else 0


def weekly_types(report, name):
    return [next((row['types'] for row in week['employees'] if row['name'] == name), []) for week in report['weeks']]


def test_current_six_leads():
    report = plan(problem(current_leads()))
    summary = check(report)
    # 19 shifts, 6 x 5 lead shifts: everything fits. Erdal (day only) pins the other five to
    # 1 day / 2 afternoon / 2 night every week, which forces one rotation break a week (12).
    assert summary['coverage_missing'] == 0
    assert summary['extra_shift_types'] == 0
    assert summary['hours_short'] == 0
    assert summary['over_max_hours'] == 0
    assert summary['rotation_breaks'] == 12
    assert all(types == ['day'] for types in weekly_types(report, 'Erdal'))
    sander_dates = [s['date'] for week in report['weeks'] for row in week['employees'] if row['name'] == 'Sander' for s in row['shifts']]
    assert all(date.fromisoformat(d).isoweekday() != 1 for d in sander_dates)
    # Fairness stops at its time limit (best found, not proven), so these are quality bounds:
    # nights and weekends came out at their optimum in every run, rotation breaks within 1-2.
    rotating = {name: n for name, n in per_lead(report, 'night').items() if name != 'Erdal'}
    assert spread(rotating) <= 5  # nights come in weeks of 5; 12 weeks don't split evenly over 5 leads
    assert spread(per_lead(report, 'sunday')) <= 1
    assert spread({name: n for name, n in per_lead(report, 'saturday').items() if name != 'Erdal'}) <= 1
    assert spread(per_lead(report, 'rotation_breaks')) <= 2


def test_one_lead_fewer():
    report = plan(problem([e for e in current_leads() if e['name'] != 'Kars']))
    summary = check(report)
    # 5 x 5 = 25 lead shifts still cover all 19, but single types need 1 more day lead next to
    # Erdal + 2 afternoon + 2 night = 5 rotating leads; there are 4, so one lead a week mixes types
    # (coverage outranks the single-type rule), and the four share those 12 weeks evenly.
    assert summary['coverage_missing'] == 0
    assert summary['extra_shift_types'] == 12
    assert summary['hours_short'] == 0
    assert summary['over_max_hours'] == 0
    mixed = [r['week_start'] for r in report['relaxed'] if r['rule'] == 'single_type']
    assert len(set(mixed)) == 12
    assert set(per_lead(report, 'extra_shift_types').values()) == {3}


def test_one_lead_more():
    leads = current_leads() + [lead(7, 'New lead')]
    report = plan(problem(leads))
    # The solver reaches the contract bound below within seconds but can't prove it (a pigeonhole:
    # seven leads, three types, two a type), so that stage and the share built on it run to their
    # time limits. Rotation isn't asserted here.
    summary = check(report, unproven=('contract', 'short_share', 'rotation'))
    # With single types and at most 2 leads a shift, a type takes at most 2 leads (night: 7 x 2 = 14
    # lead shifts, 3 leads would need 15). Seven leads can't all fit, so one lead shift a week is
    # either short or over the max; the tie-break keeps the max: 12 x 8h short, nothing over.
    # Erdal (day only) is never among the three night leads, so the six others share it: 16h each.
    assert summary['coverage_missing'] == 0
    assert summary['extra_shift_types'] == 0
    assert summary['hours_short'] == 96
    assert summary['over_max_hours'] == 0
    assert {name: h for name, h in per_lead(report, 'hours_short').items() if name != 'Erdal'} == dict.fromkeys(
        [e['name'] for e in leads if e['name'] != 'Erdal'], 16
    )


def test_erdal_without_day_only_limit():
    leads = [dict(e, unavailable=[]) if e['name'] == 'Erdal' else e for e in current_leads()]
    report = plan(problem(leads))
    summary = check(report)
    # Six rotating leads split 2/2/2 every week, which the cycle keeps forever. Only the start
    # breaks it: four leads come off a day week and afternoon takes two of them.
    assert summary['coverage_missing'] == 0
    assert summary['extra_shift_types'] == 0
    assert summary['hours_short'] == 0
    assert summary['over_max_hours'] == 0
    assert summary['rotation_breaks'] == 2


def test_always_returns_a_plan_and_reports_the_gaps():
    leads = [lead(1, 'Erdal', 'day', DAY_ONLY), lead(2, 'Sander')]
    report = plan(problem(leads, weeks=2))
    summary = check(report)
    # 2 x 19 shifts, 2 x 5 lead shifts a week: 18 shifts stay without a lead.
    assert summary['coverage_missing'] == 18
    assert sum(r['rule'] == 'coverage' for r in report['relaxed']) == 18
    assert summary['extra_shift_types'] == 0


def test_leave_and_previous_shifts_block_shifts():
    data = problem([lead(1, 'A'), lead(2, 'B')], weeks=1)
    data['employees'][0]['absences'] = [{'date': '2026-11-03'}, {'date': '2026-11-04'}, {'date': '2026-11-05'}]
    data['employees'][1]['previous_shifts'] = [{'start': '2026-10-31T16:00', 'end': '2026-11-01T00:00'}]
    parsed = parse(data)
    a, b = parsed.employees
    assert not any(parsed.shifts[i].date == date(2026, 11, 4) for i in a.available)
    assert a.target_minutes == [32 * 60]  # 4 workable dates left that week
    sunday_shifts = {parsed.shifts[i].type for i in b.can_work - b.available}
    assert sunday_shifts == {'night', 'day'}  # less than 11h after the 16:00-24:00 shift


@pytest.mark.parametrize(('change', 'message'), [
    (lambda d: d['employees'][0].update(unavailable=[{'weekday': 8}]), 'ISO weekday'),
    (lambda d: d['shifts'][0].update(date='2027-06-01'), 'outside the 1-week horizon'),
    (lambda d: d['employees'][0].pop('max_hours'), 'max_hours is required'),
    (lambda d: d['rules'].update(weekend_policy='random'), 'weekend_policy'),
])
def test_rejects_malformed_input(change, message):
    data = problem([lead(1, 'A')], weeks=1)
    change(data)
    with pytest.raises(ProblemError, match=message):
        parse(data)


def test_accepts_empty_lists_for_empty_objects():
    """PHP's json_encode turns an empty array into [] even where an object is meant."""
    data = problem([lead(1, 'A')], weeks=1)
    data['employees'][0].update(history={'hours': 0, 'counts': []}, week_hours=[])
    data['solver']['stage_seconds'] = []
    assert parse(data).employees[0].history_counts == {}


def test_command_line():
    data = problem([lead(1, 'A'), lead(2, 'B')], weeks=1)
    root = Path(__file__).resolve().parents[1]
    result = subprocess.run([sys.executable, '-m', 'roster'], input=json.dumps(data), capture_output=True, text=True, cwd=root)
    assert result.returncode == 0, result.stderr
    assert json.loads(result.stdout)['status'] == 'ok'
    result = subprocess.run([sys.executable, '-m', 'roster'], input='{}', capture_output=True, text=True, cwd=root)
    assert result.returncode == 2
    assert json.loads(result.stdout)['status'] == 'error'
