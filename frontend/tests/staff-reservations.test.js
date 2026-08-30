import test from 'node:test';
import assert from 'node:assert/strict';
import { StaffReservationService } from '../features/staff/services/reservation.service.js';
import { StaffReservationsPage } from '../features/staff/pages/reservations/reservations.page.js';

test('staff reservation service preserves queue filters and fulfilment payloads', async () => {
  const calls = [];
  const api = {
    get: async (path, params) => { calls.push(['get', path, params]); return { data: { reservations: [] } }; },
    post: async (path, body) => { calls.push(['post', path, body]); return { ok: true }; },
  };
  const service = new StaffReservationService({ api });

  await service.list('offered');
  await service.fulfil(12);

  assert.deepEqual(calls, [
    ['get', '/scan2borrow/api/staff/reservations', { status: 'offered' }],
    ['post', '/scan2borrow/api/staff/reservations/action', { hold_id: 12, action: 'fulfil' }],
  ]);
});

test('staff reservations page renders borrower, title, state, and fulfil action', () => {
  const body = { innerHTML: '' };
  const root = { querySelector: () => body, addEventListener() {} };
  const page = new StaffReservationsPage(root, { service: { list: async () => ({}) } });

  page.render([
    { id: 12, title: 'Clean Code', author: 'Robert C. Martin', user_name: 'Grace Hopper', status_label: 'Ready to collect', status: 'claimed', queue_position: 1 },
  ]);

  assert.match(body.innerHTML, /Grace Hopper/);
  assert.match(body.innerHTML, /Clean Code/);
  assert.match(body.innerHTML, /Ready to collect/);
  assert.match(body.innerHTML, /data-hold-id="12"/);
  assert.match(body.innerHTML, /Fulfil/);
});
