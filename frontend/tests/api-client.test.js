import test from 'node:test';
import assert from 'node:assert/strict';
import { ApiError } from '../app/core/api/api-error.js';
import { ApiClient } from '../app/core/api/api-client.js';

test('ApiError preserves HTTP status and compatibility payload', () => {
  const error = new ApiError('Denied', {
    status: 403,
    payload: { ok: false },
  });

  assert.equal(error.name, 'ApiError');
  assert.equal(error.status, 403);
  assert.deepEqual(error.payload, { ok: false });
});

test('ApiClient encodes GET queries and preserves successful payloads', async () => {
  let request;
  const api = new ApiClient({
    fetchImpl: async (url, options) => {
      request = { url, options };
      return new Response(JSON.stringify({ ok: true, data: { count: 2 } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      });
    },
  });

  const payload = await api.get('/scan2borrow/api/books', { search: 'Clean Code', page: 2 });

  assert.deepEqual(payload, { ok: true, data: { count: 2 } });
  assert.equal(request.url, '/scan2borrow/api/books?search=Clean+Code&page=2');
  assert.equal(request.options.credentials, 'same-origin');
  assert.equal(request.options.headers.Accept, 'application/json');
});

test('ApiClient encodes POST bodies and raises ApiError for rejected envelopes', async () => {
  let request;
  const api = new ApiClient({
    csrf: 'token-123',
    fetchImpl: async (url, options) => {
      request = { url, options };
      return new Response(JSON.stringify({ ok: false, errors: ['Denied'] }), {
        status: 403,
        headers: { 'Content-Type': 'application/json' },
      });
    },
  });

  await assert.rejects(
    () => api.post('/scan2borrow/api/books', { action: 'archive', id: '4' }),
    (error) => error instanceof ApiError && error.status === 403 && error.message === 'Denied',
  );
  assert.equal(request.options.body.toString(), 'action=archive&id=4&csrf=token-123');
});
