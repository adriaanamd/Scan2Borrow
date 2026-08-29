import test from 'node:test';
import assert from 'node:assert/strict';
import { normalizeStaffDashboard } from '../features/staff/models/index.js';
import { StaffApprovalService, StaffDashboardService } from '../features/staff/services/index.js';
import { OverviewChartComponent } from '../features/staff/components/overview-chart/overview-chart.component.js';

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
