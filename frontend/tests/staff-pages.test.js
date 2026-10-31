import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { normalizeStaffDashboard } from '../features/staff/models/index.js';
import { StaffApprovalService, StaffDashboardService } from '../features/staff/services/index.js';
import { OverviewChartComponent } from '../features/staff/components/overview-chart/overview-chart.component.js';
import { StaffDashboardPage } from '../features/staff/pages/dashboard/staff-dashboard.page.js';
import { InventoryService } from '../features/staff/services/inventory.service.js';
import { BookDrawerComponent } from '../features/staff/components/book-drawer/book-drawer.component.js';
import { InventoryPage } from '../features/staff/pages/inventory/inventory.page.js';

const testsDirectory = path.dirname(fileURLToPath(import.meta.url));
const inventorySources = [
  path.resolve(testsDirectory, '..', 'features', 'staff', 'pages', 'inventory', 'inventory.page.js'),
];
const staffDashboardSource = path.resolve(testsDirectory, '..', 'features', 'staff', 'pages', 'dashboard', 'staff-dashboard.page.js');
const adminTemplate = path.resolve(testsDirectory, '..', 'features', 'staff', 'pages', 'admin-staff', 'admin-staff.html');
const dashboardTemplate = path.resolve(testsDirectory, '..', 'features', 'staff', 'pages', 'dashboard', 'dashboard.html');
const guestRequestsTemplate = path.resolve(testsDirectory, '..', 'features', 'staff', 'pages', 'guest-requests', 'guest-requests.html');
const borrowersTemplate = path.resolve(testsDirectory, '..', 'features', 'staff', 'pages', 'borrowers', 'borrowers.html');

test('staff dashboard model normalizes stats, overview, and pending approval rows', () => {
  const model = normalizeStaffDashboard({
    stats: { total_books: '20', pending_approvals: '2' },
    pending: [{ id: '8', status: 'Pending' }],
    overview: { loan_status: { Borrowed: 3 } },
  });
  assert.equal(model.stats.total_books, 20);
  assert.equal(model.stats.pending_approvals, 2);
  assert.equal(model.pending[0].status, 'Pending');
  assert.deepEqual(model.overview.loan_status, { Borrowed: 3 });
});

test('staff services preserve dashboard polling and approval action fields', async () => {
  const calls = [];
  const api = {
    get: async (path, params) => { calls.push({ method: 'GET', path, params }); return { ok: true, data: { pending: [] }, notifications: [] }; },
    post: async (path, body) => { calls.push({ method: 'POST', path, body }); return { ok: true, message: 'Saved.' }; },
  };
  const dashboard = new StaffDashboardService({ api });
  const approvals = new StaffApprovalService({ api });
  await dashboard.load();
  await dashboard.notifications();
  await approvals.submit('approve', 8);
  assert.deepEqual(calls, [
    { method: 'GET', path: '/scan2borrow/api/staff/dashboard', params: {} },
    { method: 'GET', path: '/scan2borrow/api/staff/notifications', params: { action: 'pending_approvals' } },
    { method: 'POST', path: '/scan2borrow/api/staff/borrowing-action', body: { action: 'approve', borrowing_id: 8 } },
  ]);
});

test('overview chart component exposes all staff dashboard visualization boundaries', () => {
  for (const method of ['renderActivity', 'renderCategoryTrend', 'renderStatus', 'renderCategories', 'renderGenres', 'renderTopBorrowers', 'renderRecentActivity']) {
    assert.equal(typeof OverviewChartComponent.prototype[method], 'function', method);
  }
  assert.equal(OverviewChartComponent.CHART_WIDTH, 720);
  assert.equal(OverviewChartComponent.CHART_HEIGHT, 240);
});

test('staff dashboard exposes a feature-owned controller with approval and overview boundaries', () => {
  assert.equal(StaffDashboardPage.name, 'StaffDashboardPage');
  for (const method of ['dashboard', 'renderOverview', 'renderApprovals', 'renderReturnApprovals', 'submitBorrowing', 'submitReturn']) {
    assert.equal(typeof StaffDashboardPage.prototype[method], 'function', method);
  }
});

test('staff dashboard exposes librarian-approved return review fields and endpoint', () => {
  const source = fs.readFileSync(staffDashboardSource, 'utf8');
  const template = fs.readFileSync(dashboardTemplate, 'utf8');
  assert.match(source, /return_approvals/);
  assert.match(source, /\/scan2borrow\/api\/staff\/return-action/);
  assert.match(source, /\/scan2borrow\/api\/staff\/return-approvals/);
  assert.match(source, /return_status/);
  assert.match(source, /api\/staff\/return-approvals/);
  assert.match(template, /returnApprovalList/);
  assert.match(template, /Returns awaiting verification/);
});

test('staff dashboard does not expose approval shortcut buttons', () => {
  const template = fs.readFileSync(dashboardTemplate, 'utf8');
  assert.doesNotMatch(template, /id="pending-approvals-trigger"/);
  assert.doesNotMatch(template, /id="return-approvals-trigger"/);
  assert.doesNotMatch(template, /<button[^>]*data-bs-target="#approvalModal"/);
  assert.doesNotMatch(template, /<button[^>]*data-bs-target="#returnApprovalModal"/);
});

test('approval cards prioritize the borrower name over the book title', () => {
  const source = fs.readFileSync(staffDashboardSource, 'utf8');
  const returnCardStart = source.indexOf('\n  returnApprovalCard(row)');
  const approvalCardStart = source.indexOf('\n  approvalCard(row)');

  assert.ok(returnCardStart >= 0);
  assert.ok(approvalCardStart >= 0);

  const returnCard = source.slice(returnCardStart, source.indexOf('\n  updateApprovalCounts()', returnCardStart));
  const approvalCard = source.slice(approvalCardStart, source.indexOf('\n  async refreshNotifications()', approvalCardStart));

  assert.match(approvalCard, /approval-card__borrower/);
  assert.match(approvalCard, /approval-card__book-title/);
  assert.match(approvalCard, /borrower_photo/);
  assert.match(approvalCard, /approval-card__borrower-photo/);
  assert.ok(approvalCard.indexOf('approval-card__borrower') < approvalCard.indexOf('approval-card__book-title'));
  assert.match(returnCard, /return-approval-card__borrower/);
  assert.match(returnCard, /return-approval-card__book-title/);
  assert.ok(returnCard.indexOf('return-approval-card__borrower') < returnCard.indexOf('return-approval-card__book-title'));
});

test('inventory boundaries preserve list, bulk action, drawer, and page contracts', async () => {
  const calls = [];
  const service = new InventoryService({
    api: {
      get: async (path, params) => { calls.push({ method: 'GET', path, params }); return { ok: true, data: [] }; },
      post: async (path, body) => { calls.push({ method: 'POST', path, body }); return { ok: true }; },
    },
  });
  await service.list({ page: 2, per_page: 10, sort: 'created_at', dir: 'desc' });
  await service.action('archive', { ids: ['1', '2'] });
  assert.deepEqual(calls, [
    { method: 'GET', path: '/scan2borrow/api/books', params: { action: 'list', page: 2, per_page: 10, sort: 'created_at', dir: 'desc' } },
    { method: 'POST', path: '/scan2borrow/api/books', body: { action: 'archive', ids: ['1', '2'] } },
  ]);
  assert.equal(typeof BookDrawerComponent.prototype.open, 'function');
  assert.equal(InventoryPage.name, 'InventoryPage');
});

test('inventory controllers use the shared confirmation service for destructive actions', () => {
  for (const sourcePath of inventorySources) {
    const source = fs.readFileSync(sourcePath, 'utf8');
    assert.match(source, /Scan2BorrowConfirmation\.confirm/, sourcePath);
    assert.doesNotMatch(source, /window\.confirm/, sourcePath);
  }
});

test('staff account actions use shared confirmation and admin markup has no native confirm', () => {
  const controller = fs.readFileSync(staffDashboardSource, 'utf8');
  const template = fs.readFileSync(adminTemplate, 'utf8');
  assert.match(controller, /Scan2BorrowConfirmation\.confirm/);
  assert.doesNotMatch(template, /onsubmit=.*confirm\(/);
});

test('staff navigation exposes the copy history page', () => {
  const navbar = fs.readFileSync(path.resolve(testsDirectory, '..', 'assets', 'js', 'core', 'app-navbar.js'), 'utf8');
  assert.match(navbar, /staff\/copy-history/);
  assert.match(navbar, /Copy History/);
  assert.match(navbar, /staff\/circulation/);
  assert.match(navbar, /Circulation/);
});

test('staff borrowers page exposes scan ID lookup modal controls', () => {
  const template = fs.readFileSync(borrowersTemplate, 'utf8');
  assert.match(template, /id="borrower-lookup-trigger"/);
  assert.match(template, /data-bs-target="#borrower-lookup-modal"/);
  assert.match(template, /id="borrower-lookup-modal"/);
  assert.match(template, /id="borrower-lookup-form"/);
  assert.match(template, /id="borrower-lookup-scanner"/);
  assert.match(template, /id="borrower-lookup-result"/);
  assert.match(template, /View borrower/);
  assert.match(template, /Open circulation/);
});

test('staff circulation page exposes scan-first borrow and return controls', () => {
  const pageDirectory = path.resolve(testsDirectory, '..', 'features', 'staff', 'pages', 'circulation');
  const template = fs.readFileSync(path.join(pageDirectory, 'circulation.html'), 'utf8');
  const stylesheet = fs.readFileSync(path.join(pageDirectory, 'circulation.css'), 'utf8');

  assert.match(template, /data-app-page="staff-circulation"/);
  assert.match(template, /circulation-borrower-scanner/);
  assert.match(template, /circulation-book-scanner/);
  assert.match(template, /circulation-return-scanner/);
  assert.match(template, /circulation-review-modal/);
  assert.match(template, /circulation-approve-borrow/);
  assert.match(template, /id="circulation-borrower-photo"/);
  assert.match(template, /assets\/js\/core\/media\.js/);
  assert.match(stylesheet, /var\(--primary\)/);
  assert.match(stylesheet, /@media/);
});

test('staff circulation page exposes the borrower profile workflow in the app shell', () => {
  const pageDirectory = path.resolve(testsDirectory, '..', 'features', 'staff', 'pages', 'circulation');
  const template = fs.readFileSync(path.join(pageDirectory, 'circulation.html'), 'utf8');
  const controller = fs.readFileSync(path.join(pageDirectory, 'circulation.page.js'), 'utf8');

  for (const id of [
    'circulation-borrower',
    'circulation-requested-section',
    'circulation-history-section',
    'circulation-summary-section',
  ]) assert.match(template, new RegExp(`id="${id}"`), id);
  assert.match(template, /Scan barcode/);
  assert.match(template, /Verify Borrower ID/);
  assert.match(template, /Borrower Information/);
  assert.match(template, /Requested Books/);
  assert.match(template, /Borrowed Books History/);
  assert.match(template, /Transaction Summary/);
  assert.match(template, /circulation-borrower-photo/);
  assert.match(controller, /data-circulation-request-action="approve"/);
  assert.match(controller, /circulation-borrower-photo/);
  assert.match(template, /data-circulation-history-filter="returned"/);
});

test('admin profile approval stays visible only on the protected admin surface', () => {
  const page = fs.readFileSync(path.resolve(testsDirectory, '..', 'features', 'staff', 'pages', 'admin-staff', 'admin-staff.page.js'), 'utf8');
  const template = fs.readFileSync(adminTemplate, 'utf8');
  assert.match(page, /profile-change-requests-body/);
  assert.match(page, /safePhotoPath/);
  assert.match(template, /Only administrators can approve/);
  assert.match(template, /profile-change-review-note/);
});

test('approval and guest rejection templates use shared confirmation metadata', () => {
  for (const templatePath of [dashboardTemplate, guestRequestsTemplate]) {
    const source = fs.readFileSync(templatePath, 'utf8');
    assert.doesNotMatch(source, /(?:onsubmit|onclick)=.*confirm\(/, templatePath);
    assert.match(source, /data-confirm-action="approve"/, templatePath);
    assert.match(source, /data-confirm-action="reject"/, templatePath);
  }
  assert.match(fs.readFileSync(staffDashboardSource, 'utf8'), /Scan2BorrowConfirmation\.confirm/);
});
