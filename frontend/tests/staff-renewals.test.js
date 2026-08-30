import test from 'node:test';
import assert from 'node:assert/strict';
import { StaffRenewalService } from '../features/staff/services/renewal.service.js';
import { StaffRenewalsPage } from '../features/staff/pages/renewals/renewals.page.js';

test('staff renewal service sends explicit approval decisions', async () => {
  const calls = [];
  const api = { get: async (path) => { calls.push(['get', path]); return { data: { renewals: [] } }; }, post: async (path, body) => { calls.push(['post', path, body]); return { ok: true }; } };
  const service = new StaffRenewalService({ api });
  await service.list();
  await service.decide(12, 'reject', 'Already extended');
  assert.deepEqual(calls, [
    ['get', '/scan2borrow/api/staff/renewals'],
    ['post', '/scan2borrow/api/staff/renewals/action', { renewal_id: 12, action: 'reject', note: 'Already extended' }],
  ]);
});

test('staff renewal page renders due dates, reasons, and decision controls', () => {
  const body = { innerHTML: '' };
  const root = { querySelector: () => body, addEventListener() {} };
  const page = new StaffRenewalsPage(root, { service: { list: async () => ({}) } });
  page.render([{ id: 12, title: 'Clean Code', user_name: 'Grace Hopper', original_due_date: '2026-08-30', requested_due_date: '2026-09-06', reason: 'Project deadline' }]);
  assert.match(body.innerHTML, /Grace Hopper/);
  assert.match(body.innerHTML, /Project deadline/);
  assert.match(body.innerHTML, /2026-09-06/);
  assert.match(body.innerHTML, /data-renewal-action="approve"/);
  assert.match(body.innerHTML, /data-renewal-action="reject"/);
});
