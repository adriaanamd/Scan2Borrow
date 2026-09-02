import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { RenewalService } from '../app/core/services/renewal.service.js';
import { RenewalPanelComponent } from '../app/shared/components/renewal-panel/renewal-panel.component.js';

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

test('renewal panel keeps active loan ids and approval state visible', () => {
  const root = { innerHTML: '', addEventListener() {} };
  const panel = new RenewalPanelComponent(root, { service: { list: async () => ({}) } });
  panel.render([{ id: 88, title: 'Clean Code', due_date: '2026-08-30' }], [{ loan_id: 88, status: 'pending', status_label: 'Awaiting librarian approval' }]);
  assert.match(root.innerHTML, /Renewals/);
  assert.match(root.innerHTML, /Clean Code/);
  assert.match(root.innerHTML, /Awaiting librarian approval/);
  assert.match(root.innerHTML, /data-loan-id="88"/);
});

test('borrower dashboards mount the renewal panel for the current loans', () => {
  for (const [page, role] of [
    ['features/student/pages/dashboard/dashboard.html', 'student'],
    ['features/teacher/pages/dashboard/dashboard.html', 'teacher'],
  ]) {
    assert.match(read(page), /id="renewalPanel"/);
  }
  for (const [page, role] of [
    ['features/student/pages/dashboard/student-dashboard.page.js', 'student'],
    ['features/teacher/pages/dashboard/teacher-dashboard.page.js', 'teacher'],
  ]) {
    const source = read(page);
    assert.match(source, /RenewalPanelComponent/);
    assert.match(source, /RenewalService/);
    assert.match(source, new RegExp(`role: ['"]${role}['"]`));
    assert.match(source, /renewalPanel\.load\(data\.current_loans \|\| \[\]\)/);
  }
});

test('borrower dashboards load the shared renewal presentation', () => {
  for (const page of [
    'features/student/pages/dashboard/dashboard.html',
    'features/teacher/pages/dashboard/dashboard.html',
  ]) {
    assert.match(read(page), /frontend\/assets\/css\/reservations\.css/);
  }

  const styles = read('assets/css/reservations.css');
  assert.match(styles, /\.renewal-panel\s*\{[\s\S]*border-left:\s*4px solid #002FA7;/);
  assert.match(styles, /\.renewal-panel__row\s*\{[\s\S]*display:\s*grid;/);
  assert.doesNotMatch(styles, /\.renewal-panel__button\s*\{[\s\S]*#e4002b/);
});

test('renewal rows expose a quiet operational hierarchy', () => {
  const root = { innerHTML: '', addEventListener() {} };
  const panel = new RenewalPanelComponent(root, { service: { list: async () => ({}) } });
  panel.render([{ id: 88, title: 'Clean Code', due_date: '2026-08-30' }], []);

  assert.match(root.innerHTML, /renewal-panel__loan/);
  assert.match(root.innerHTML, /renewal-panel__due/);
  assert.match(root.innerHTML, /renewal-panel__actions/);
  assert.doesNotMatch(root.innerHTML, /Borrower request/);
});

test('renewal rows do not offer requests for loans that are not borrowable', () => {
  const root = { innerHTML: '', addEventListener() {} };
  const panel = new RenewalPanelComponent(root, { service: { list: async () => ({}) } });
  panel.render([
    { id: 11, title: 'Awaiting Book', due_date: '2026-09-05', status: 'Pending' },
    { id: 12, title: 'Late Book', due_date: '2026-08-12', status: 'Overdue' },
  ], []);

  assert.doesNotMatch(root.innerHTML, /renewal-panel__form/);
  assert.match(root.innerHTML, /Awaiting approval/);
  assert.match(root.innerHTML, /Resolve overdue balance/);
});
