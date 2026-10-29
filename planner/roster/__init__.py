"""Week-by-week roster solver (OR-Tools CP-SAT).

Given people, contracts, availability, open shifts, history and policy weights, it decides who
works which shift over a multi-week horizon, honouring the rules in a fixed priority order (see
model.py). It always returns a plan, together with the list of rules it had to relax.
"""
from .model import solve
from .problem import ProblemError, parse
from .report import build_report


def plan(data: dict) -> dict:
    problem = parse(data)
    return build_report(problem, solve(problem))


__all__ = ['ProblemError', 'build_report', 'parse', 'plan', 'solve']
