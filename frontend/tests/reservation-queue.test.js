import test from 'node:test';
import assert from 'node:assert/strict';
import { ReservationService } from '../app/core/services/reservation.service.js';
import { ReservationQueueComponent } from '../app/shared/components/reservation-queue/reservation-queue.component.js';

test('reservation service uses the borrower hold API and preserves payloads', async () => {
  const calls = [];
  const api = {
    get: async (path) => { calls.push(['get', path]); return { data: { holds: [] } }; },
    post: async (path, body) => { calls.push(['post', path, body]); return { ok: true }; },
  };
  const service = new ReservationService({ api, role: 'teacher' });

  await service.list();
  await service.join(4);
  await service.action(12, 'claim');

  assert.deepEqual(calls, [
    ['get', '/scan2borrow/api/teacher/holds'],
    ['post', '/scan2borrow/api/teacher/holds', { title_id: 4 }],
    ['post', '/scan2borrow/api/teacher/holds/action', { hold_id: 12, action: 'claim' }],
  ]);
});

test('reservation queue renders real positions, expiry, and safe title text', () => {
  const root = { innerHTML: '', addEventListener() {} };
  const component = new ReservationQueueComponent(root, {
    service: { list: async () => ({ data: { holds: [] } }) },
  });

  component.render([
    { id: 12, title: '<Clean Code>', author: 'Robert C. Martin', status: 'offered', status_label: 'Ready to collect', queue_position: 1, hold_expires_at: '2026-08-31 10:00:00' },
  ]);

  assert.match(root.innerHTML, /Your holds/);
  assert.match(root.innerHTML, /Queue 01/);
  assert.match(root.innerHTML, /Ready to collect/);
  assert.match(root.innerHTML, /&lt;Clean Code&gt;/);
  assert.match(root.innerHTML, /Claim hold/);
  assert.match(root.innerHTML, /2026-08-31 10:00:00/);
});
