import test from 'node:test';
import assert from 'node:assert/strict';
import { StaffReportService, StaffNotificationService } from '../features/staff/services/index.js';
import { StaffOverdueService } from '../features/staff/services/overdue.service.js';
import { ReportsPage } from '../features/staff/pages/reports/reports.page.js';
import { OverduePage } from '../features/staff/pages/overdue/overdue.page.js';
import { StaffGuestRequestService } from '../features/staff/services/guest-request.service.js';
import { StaffNotifyPage } from '../features/staff/pages/notify/notify.page.js';
import { GuestRequestsPage } from '../features/staff/pages/guest-requests/guest-requests.page.js';
import { AdminStaffService } from '../features/staff/services/admin-staff.service.js';
import { AdminStaffPage } from '../features/staff/pages/admin-staff/admin-staff.page.js';
import { ApiDocsPage } from '../features/staff/pages/api-docs/api-docs.page.js';

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

test('reports and overdue pages expose their operational boundaries', async () => {
  let call;
  const overdue = new StaffOverdueService({ api: { get: async (path, params) => { call = { path, params }; return { ok: true, data: { overdue: [] } }; } } });
  await overdue.load();
  assert.deepEqual(call, { path: '/scan2borrow/api/staff/overdue', params: {} });
  assert.equal(ReportsPage.name, 'ReportsPage');
  assert.equal(typeof ReportsPage.prototype.render, 'function');
  assert.equal(OverduePage.name, 'OverduePage');
  assert.equal(typeof OverduePage.prototype.render, 'function');
});

test('reports page renders data, preserves links, and prints after report readiness', async () => {
  const nodes = new Map();
  const makeNode = (extra = {}) => ({
    children: [],
    dataset: {},
    classList: { added: [], add(value) { this.added.push(value); } },
    replaceChildren(...children) { this.children = children; },
    appendChild(child) { this.children.push(child); },
    querySelector(selector) { return this.children.find((child) => child.selector === selector) || null; },
    setAttribute(name, value) { this[name] = value; },
    ...extra,
  });
  const root = makeNode({
    querySelector(selector) { return nodes.get(selector) || null; },
  });
  const table = makeNode();
  table.querySelector = (selector) => selector === 'thead' ? nodes.get('thead') : nodes.get('tbody');
  nodes.set('#staff-report-table', table);
  nodes.set('thead', makeNode());
  nodes.set('tbody', makeNode());
  nodes.set('#staff-report-document', makeNode());
  nodes.set('#staff-report-title', makeNode());
  nodes.set('#staff-report-period', makeNode());
  nodes.set('#staff-report-count', makeNode());
  nodes.set('#staff-report-generated', makeNode());
  nodes.set('#staff-report-status', makeNode());
  nodes.set('#export-report-link', makeNode());
  nodes.set('#generate-report-link', makeNode());
  nodes.set('select[name="type"]', makeNode());
  nodes.set('input[name="from"]', makeNode());
  nodes.set('input[name="to"]', makeNode());

  const created = [];
  const document = { createElement(tag) { const node = makeNode({ tag, textContent: '', selector: tag }); created.push(node); return node; } };
  const service = {
    async load(filters) { return { data: { report: { label: 'Overdue Books', headers: ['Title'], data: [['Dune']] } }, filters }; },
    exportUrl(filters) { return `/export?${filters.type}`; },
    printUrl(filters) { return `/print?${filters.type}`; },
  };
  let printed = false;
  const window = {
    location: { search: '?type=overdue&from=2026-01-01&print=1' },
    requestAnimationFrame(callback) { callback(); },
    print() { printed = true; },
  };

  const page = new ReportsPage(root, { service, window, document });
  await page.load();

  assert.match(nodes.get('select[name="type"]').innerHTML, /overdue/);
  assert.equal(nodes.get('input[name="from"]').value, '2026-01-01');
  assert.equal(nodes.get('#export-report-link').href, '/export?overdue');
  assert.equal(nodes.get('#generate-report-link').href, '/print?overdue');
  assert.equal(nodes.get('#staff-report-title').textContent, 'Overdue Books');
  assert.equal(nodes.get('#staff-report-document').dataset.reportReady, 'true');
  assert.equal(root.classList.added[0], 'report-print-mode');
  assert.equal(printed, true);
  assert.ok(created.some((node) => node.tag === 'th' && node.textContent === 'Title'));
});

test('staff notification and guest review boundaries preserve action payloads', async () => {
  let call;
  const service = new StaffGuestRequestService({ api: { get: async (path, params) => { call = { method: 'GET', path, params }; return { ok: true, data: { requests: [] } }; }, post: async (path, body) => { call = { method: 'POST', path, body }; return { ok: true }; } } });
  await service.load();
  await service.review(7, 'approve', 'Ready for release.');
  assert.deepEqual(call, { method: 'POST', path: '/scan2borrow/api/staff/guest-action', body: { id: 7, action: 'approve', notes: 'Ready for release.' } });
  assert.equal(StaffNotifyPage.name, 'StaffNotifyPage');
  assert.equal(typeof StaffNotifyPage.prototype.load, 'function');
  assert.equal(GuestRequestsPage.name, 'GuestRequestsPage');
  assert.equal(typeof GuestRequestsPage.prototype.render, 'function');
  assert.equal(typeof GuestRequestsPage.prototype.bindGuestReview, 'function');
});

test('admin staff service preserves candidate search and role-management fields', async () => {
  const calls = [];
  const api = {
    get: async (path, params) => { calls.push({ method: 'GET', path, params }); return { ok: true, data: {} }; },
    post: async (path, body) => { calls.push({ method: 'POST', path, body }); return { ok: true }; },
  };
  const service = new AdminStaffService({ api });
  await service.list('faculty');
  await service.action('promote', 9, { role: 'librarian' });
  assert.deepEqual(calls, [
    { method: 'GET', path: '/scan2borrow/api/admin/staff', params: { bsearch: 'faculty' } },
    { method: 'POST', path: '/scan2borrow/api/admin/staff-action', body: { role: 'librarian', action: 'promote', user_id: 9 } },
  ]);
  assert.equal(AdminStaffPage.name, 'AdminStaffPage');
  assert.equal(typeof AdminStaffPage.prototype.render, 'function');
  assert.equal(typeof AdminStaffPage.prototype.bindAdminActions, 'function');
});

test('API docs page preserves grouped search/rendering boundaries', () => {
  assert.equal(ApiDocsPage.name, 'ApiDocsPage');
  for (const method of ['renderTags', 'renderOperations', 'group', 'operation', 'detail', 'empty']) {
    assert.equal(typeof ApiDocsPage.prototype[method], 'function', method);
  }
});
