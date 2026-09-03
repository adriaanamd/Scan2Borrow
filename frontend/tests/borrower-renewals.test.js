import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { RenewalService } from '../app/core/services/renewal.service.js';
import { RenewalModalComponent } from '../app/shared/components/renewal-modal/renewal-modal.component.js';
import { StudentDashboardPage } from '../features/student/pages/dashboard/student-dashboard.page.js';
import { TeacherDashboardPage } from '../features/teacher/pages/dashboard/teacher-dashboard.page.js';

const root = path.resolve('frontend');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

test('renewal service uses the role-specific borrower endpoint', async () => {
  const calls = [];
  const api = { get: async (path) => { calls.push(['get', path]); return { data: { renewals: [] } }; }, post: async (path, body) => { calls.push(['post', path, body]); return { ok: true }; } };
  const service = new RenewalService({ api, role: 'teacher' });
  await service.list();
  await service.request(88, 'Project deadline');
  assert.deepEqual(calls, [
    ['get', '/scan2borrow/api/teacher/renewals'],
    ['post', '/scan2borrow/api/teacher/renewals', { loan_id: 88, reason: 'Project deadline' }],
  ]);
});

test('renewal modal renders a selected loan and submits the existing payload', async () => {
  const root = { innerHTML: '', addEventListener() {} };
  const calls = [];
  let changed = 0;
  const submitButton = { disabled: false };
  const form = {
    elements: {
      loan_id: { value: '88' },
      reason: { value: 'Project deadline' },
    },
    querySelector: () => submitButton,
  };
  const modal = new RenewalModalComponent(root, {
    service: {
      request: async (loanId, reason) => calls.push([loanId, reason]),
    },
    onChanged: () => { changed += 1; },
    contentClass: 'student-dashboard__modal',
    headerClass: 'student-dashboard__modal-header',
  });

  modal.open({ id: 88, title: 'Clean Code', due_date: '2026-08-30' });
  assert.match(root.innerHTML, /Clean Code/);
  assert.match(root.innerHTML, /Due 2026-08-30/);
  assert.match(root.innerHTML, /name="reason"/);
  assert.match(root.innerHTML, /Request \+7 days/);

  await modal.handleSubmit({
    target: { closest: () => form },
    preventDefault() {},
  });

  assert.deepEqual(calls, [['88', 'Project deadline']]);
  assert.equal(changed, 1);
  assert.equal(submitButton.disabled, true);
});

test('borrower dashboards place renewal beside the receipt in My Books Actions', () => {
  for (const page of [
    'features/student/pages/dashboard/dashboard.html',
    'features/teacher/pages/dashboard/dashboard.html',
  ]) {
    const source = read(page);
    assert.doesNotMatch(source, /id="renewalPanel"/);
    assert.match(source, /<th>Actions<\/th>/);
    assert.match(source, /id="renewalModal"/);
  }
  for (const page of [
    'features/student/pages/dashboard/student-dashboard.page.js',
    'features/teacher/pages/dashboard/teacher-dashboard.page.js',
  ]) {
    const source = read(page);
    assert.match(source, /RenewalModalComponent/);
    assert.doesNotMatch(source, /RenewalPanelComponent|renewalPanel/);
    assert.match(source, /data-renewal-open/);
    assert.match(source, /View receipt/);
    assert.match(source, /Renew/);
    assert.match(source, /status === ["']pending["']/);
    assert.match(source, /status === ["']overdue["']/);
    assert.match(source, /renewal\.status_label \|\| renewal\.status/);
    assert.match(source, /Awaiting approval/);
    assert.match(source, /Resolve overdue balance/);
  }
});

test('borrower row actions distinguish requestable, pending, overdue, and submitted renewals', () => {
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  })[character]);
  const loan = { id: 88, title: 'Clean Code' };

  for (const Page of [StudentDashboardPage, TeacherDashboardPage]) {
    const action = (status, renewals = []) => Page.prototype.renewalAction.call({
      renewals: new Map(renewals.map((renewal) => [String(renewal.loan_id), renewal])),
      escapeHtml,
    }, { ...loan, status });

    assert.match(action('Borrowed'), /data-renewal-open/);
    assert.match(action('Borrowed'), />Renew<\/button>/);
    assert.match(action('Pending'), /Awaiting approval/);
    assert.doesNotMatch(action('Pending'), /data-renewal-open/);
    assert.match(action('Overdue'), /Resolve overdue balance/);
    assert.doesNotMatch(action('Overdue'), /data-renewal-open/);
    assert.match(action('Borrowed', [{ loan_id: 88, status: 'pending', status_label: 'Awaiting librarian approval' }]), /Awaiting librarian approval/);
    assert.doesNotMatch(action('Borrowed', [{ loan_id: 88, status: 'pending', status_label: 'Awaiting librarian approval' }]), /data-renewal-open/);
    assert.match(action('Borrowed', [{ loan_id: 88, status: 'rejected', status_label: 'Rejected' }]), /data-renewal-open/);
  }
});

test('renewal presentation no longer uses the standalone panel contract', () => {
  assert.match(read('assets/css/style.css'), /\.reservation-queue/);
  assert.doesNotMatch(read('assets/css/reservations.css'), /\.renewal-panel/);
  assert.match(read('assets/css/borrower-dashboards.css'), /\.borrower-dashboard__loan-actions/);
});
