import test from 'node:test';
import assert from 'node:assert/strict';
import { ApiError } from '../app/core/api/api-error.js';

test('ApiError preserves HTTP status and compatibility payload', () => {
  const error = new ApiError('Denied', {
    status: 403,
    payload: { ok: false },
  });

  assert.equal(error.name, 'ApiError');
  assert.equal(error.status, 403);
  assert.deepEqual(error.payload, { ok: false });
});
