"""The HTTP service on a random local port: health, a small plan, and each error status."""
import json
import threading
import urllib.error
import urllib.request

import pytest

from leads import lead, problem
from roster.server import make_server


@pytest.fixture(scope='module')
def base_url():
    server = make_server('127.0.0.1', 0)
    threading.Thread(target=server.serve_forever, daemon=True).start()
    yield f'http://127.0.0.1:{server.server_address[1]}'
    server.shutdown()
    server.server_close()


def call(url, body=None):
    request = urllib.request.Request(url, data=body, method='POST' if body is not None else 'GET')
    try:
        with urllib.request.urlopen(request, timeout=60) as response:
            return response.status, json.loads(response.read())
    except urllib.error.HTTPError as error:
        return error.code, json.loads(error.read())


def test_health(base_url):
    assert call(f'{base_url}/health') == (200, {'status': 'ok', 'busy': False})


def test_plan(base_url):
    status, body = call(f'{base_url}/plan', json.dumps(problem([lead(1, 'A'), lead(2, 'B')], weeks=1)).encode())
    assert status == 200
    assert body['status'] == 'ok'
    assert {row['name'] for row in body['weeks'][0]['employees']} == {'A', 'B'}


@pytest.mark.parametrize(('path', 'body', 'status'), [
    ('/nope', None, 404),
    ('/plan', b'{not json', 400),
    ('/plan', b'{}', 422),
])
def test_errors(base_url, path, body, status):
    code, payload = call(f'{base_url}{path}', body)
    assert code == status
    assert payload['status'] == 'error' and payload['message']
