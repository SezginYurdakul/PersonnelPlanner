"""HTTP front for the solver, for the app's internal network only (no authentication).

  GET  /health  -> {"status": "ok", "busy": bool}
  POST /plan    -> the problem JSON in, the plan JSON out (see report.py)

Errors come back as {"status": "error", "message": ...}: 400 unreadable JSON, 413 body too large,
422 malformed problem, 500 solver failure. One plan is solved at a time - a solve already uses
every worker thread it is given - so concurrent requests wait their turn.
"""
import json
import logging
import os
import signal
import sys
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

from . import ProblemError, build_report, parse, solve

MAX_BODY_BYTES = 10 * 1024 * 1024
log = logging.getLogger('roster')
_solving = threading.Lock()


class Handler(BaseHTTPRequestHandler):
    server_version = 'roster'

    def do_GET(self) -> None:
        if self.path == '/health':
            self._send(200, {'status': 'ok', 'busy': _solving.locked()})
        else:
            self._error(404, 'not found')

    def do_POST(self) -> None:
        if self.path != '/plan':
            return self._error(404, 'not found')
        length = int(self.headers.get('Content-Length') or 0)
        if length > MAX_BODY_BYTES:
            return self._error(413, f'body larger than {MAX_BODY_BYTES} bytes')
        try:
            data = json.loads(self.rfile.read(length))
        except (json.JSONDecodeError, UnicodeDecodeError) as error:
            return self._error(400, f'invalid JSON: {error}')

        try:
            problem = parse(data)  # outside the lock: a bad request shouldn't wait for a running solve
        except ProblemError as error:
            return self._error(422, str(error))

        waited = time.monotonic()
        with _solving:
            started = time.monotonic()
            try:
                result = build_report(problem, solve(problem))
            except Exception:
                log.exception('solve failed')
                return self._error(500, 'solver failed, see the planner log')
        log.info(
            'plan: %s weeks, %s people, %s shifts -> %s in %.1fs (waited %.1fs) %s',
            problem.weeks, len(problem.employees), len(problem.shifts), result['status'],
            time.monotonic() - started, started - waited, result['summary'],
        )
        self._send(200, result)

    def _error(self, status: int, message: str) -> None:
        self._send(status, {'status': 'error', 'message': message})

    def _send(self, status: int, body: dict) -> None:
        payload = json.dumps(body, ensure_ascii=False).encode()
        self.send_response(status)
        self.send_header('Content-Type', 'application/json; charset=utf-8')
        self.send_header('Content-Length', str(len(payload)))
        self.end_headers()
        self.wfile.write(payload)

    def log_message(self, format: str, *args) -> None:
        log.debug('%s %s', self.address_string(), format % args)


def make_server(host: str, port: int) -> ThreadingHTTPServer:
    server = ThreadingHTTPServer((host, port), Handler)
    server.daemon_threads = True
    return server


def main() -> None:
    logging.basicConfig(level=os.environ.get('LOG_LEVEL', 'INFO'), format='%(asctime)s %(levelname)s %(message)s')
    port = int(os.environ.get('PORT', '8000'))
    server = make_server('0.0.0.0', port)
    signal.signal(signal.SIGTERM, lambda *_: sys.exit(0))  # docker stop
    log.info('roster planner listening on :%s', port)
    try:
        server.serve_forever()
    finally:
        server.server_close()


if __name__ == '__main__':
    main()
