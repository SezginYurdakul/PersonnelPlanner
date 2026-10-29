"""Shift-lead problems shaped like the bakery's current setup, from week 45 (2026-11-01) on.

Input data only: the solver knows nothing about these people or this shift layout.
"""
from datetime import date, datetime, time, timedelta

START = date(2026, 11, 1)  # a Sunday: the week runs from Sunday night to Saturday afternoon
STARTS = {'night': time(0), 'day': time(8), 'afternoon': time(16)}
DAY_ONLY = [{'weekday': d, 'type': t} for d in range(1, 8) for t in ('night', 'afternoon')]
NO_MONDAY = [{'weekday': 1}]
TEST_SECONDS = {
    'coverage': 10, 'single_type': 10, 'mixed_share': 10, 'contract': 10, 'over_max': 10, 'short_share': 10,
    'rotation': 30, 'fairness': 20, 'weekend': 15,
}


def lead(id, name, previous_type=None, unavailable=(), hours=40):
    return {
        'id': id, 'name': name, 'min_hours': hours, 'max_hours': hours,
        'previous_type': previous_type, 'unavailable': list(unavailable),
    }


def current_leads():
    # previous_type: what each lead mostly worked in the week-44 draft (Sander's week was mixed).
    return [
        lead(1, 'Erdal', 'day', DAY_ONLY),
        lead(2, 'Sander', None, NO_MONDAY),
        lead(3, 'Winfred', 'day'),
        lead(4, 'Marc', 'night'),
        lead(5, 'Kars', 'day'),
        lead(6, 'Mark', 'day'),
    ]


def lead_shifts(weeks):
    """Sunday-Friday all three shifts, Saturday only the night: 19 lead shifts a week, 1-2 leads each."""
    shifts = []
    for offset in range(weeks * 7):
        day = START + timedelta(days=offset)
        for kind, begins in STARTS.items():
            if day.isoweekday() == 6 and kind != 'night':
                continue
            begin = datetime.combine(day, begins)
            shifts.append({
                'id': f'{day}/{kind}', 'date': day.isoformat(), 'type': kind,
                'start': begin.isoformat(), 'end': (begin + timedelta(hours=8)).isoformat(),
                'min': 1, 'max': 2,
            })
    return shifts


def problem(employees, weeks=12, weekend_policy='fair'):
    return {
        'horizon_start': START.isoformat(),
        'weeks': weeks,
        'shift_types': ['night', 'day', 'afternoon'],
        'rotation': ['day', 'afternoon', 'night'],
        'rules': {'min_rest_hours': 11, 'max_shifts_per_day': 1, 'weekend_policy': weekend_policy, 'weekend_weekdays': [6, 7]},
        'fairness': {
            'dimensions': [
                {'key': 'night', 'types': ['night'], 'weight': 1},
                {'key': 'sunday', 'weekdays': [7], 'weight': 3},
                {'key': 'saturday', 'weekdays': [6], 'weight': 3},
            ],
            'rotation_break_weight': 3,
        },
        'shifts': lead_shifts(weeks),
        'employees': employees,
        'solver': {'stage_seconds': TEST_SECONDS, 'workers': 8},
    }
