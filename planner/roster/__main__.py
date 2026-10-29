"""python -m roster < problem.json > plan.json"""
import json
import sys

from . import ProblemError, plan


def main() -> int:
    try:
        result = plan(json.load(sys.stdin))
    except (json.JSONDecodeError, ProblemError) as error:
        json.dump({'status': 'error', 'message': str(error)}, sys.stdout)
        return 2
    json.dump(result, sys.stdout, ensure_ascii=False)
    return 0


if __name__ == '__main__':
    sys.exit(main())
