import test from 'node:test';
import assert from 'node:assert/strict';
import { StaffReportService, StaffNotificationService } from '../features/staff/services/index.js';

test('staff utility services preserve report filters, export/print flags, and notification actions', async () => {
  const calls = [];
  const api = {
    get: async (path, params) => { calls.push({ method: 'GET', path, params }); return { ok: true, data: { report: {} }, notifications: [] }; },
    post: async (path, body) => { calls.push({ method: 'POST', path, body }); return { ok: true }; },
  };
  const reports = new StaffReportService({ api });
  const notifications = new StaffNotificationService({ api, window: { setInterval: () => 1, clearInterval() {} } });
  await reports.load({ type: 'overdue', from: '2026-01-01', to: '2026-08-29' });
  assert.equal(reports.exportUrl({ type: 'overdue', from: '2026-01-01' }), '/scan2borrow/api/staff/reports/export?type=overdue&from=2026-01-01');
  assert.equal(reports.printUrl({ type: 'overdue' }), '/scan2borrow/staff/reports?type=overdue&print=1');
  await notifications.load({ action: 'pending_approvals' });
  assert.deepEqual(calls, [
    { method: 'GET', path: '/scan2borrow/api/staff/reports', params: { type: 'overdue', from: '2026-01-01', to: '2026-08-29' } },
    { method: 'GET', path: '/scan2borrow/api/staff/notifications', params: { action: 'pending_approvals' } },
  ]);
  notifications.stop();
});
