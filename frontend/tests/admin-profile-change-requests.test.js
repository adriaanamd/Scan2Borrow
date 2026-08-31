import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { ProfileChangeRequestService } from '../features/staff/services/profile-change-request.service.js';
import { AdminStaffPage } from '../features/staff/pages/admin-staff/admin-staff.page.js';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', 'features', 'staff', 'pages', 'admin-staff');

test('admin profile service preserves review endpoints and note payload', async () => {
  const calls = [];
  const api = { get: async (path, params) => { calls.push({ method: 'GET', path, params }); return { ok: true, data: { requests: [] } }; }, post: async (path, body) => { calls.push({ method: 'POST', path, body }); return { ok: true }; } };
  const service = new ProfileChangeRequestService({ api });
  await service.list();
  await service.action('reject', 41, 'Not enough documentation.');
  assert.deepEqual(calls, [
    { method: 'GET', path: '/scan2borrow/api/admin/profile-change-requests', params: {} },
    { method: 'POST', path: '/scan2borrow/api/admin/profile-change-request-action', body: { action: 'reject', request_id: 41, review_note: 'Not enough documentation.' } },
  ]);
});

test('admin page exposes safe profile review and decision boundaries', () => {
  const source = fs.readFileSync(path.join(root, 'admin-staff.page.js'), 'utf8');
  const template = fs.readFileSync(path.join(root, 'admin-staff.html'), 'utf8');
  for (const method of ['renderProfileChangeRequests', 'profileChangeDetail', 'bindProfileChangeActions', 'decideProfileChange']) assert.equal(typeof AdminStaffPage.prototype[method], 'function', method);
  assert.match(source, /Scan2BorrowConfirmation/);
  assert.match(source, /requested_values/);
  assert.match(template, /profile-change-requests-body/);
  assert.match(template, /profileChangeModal/);
});
