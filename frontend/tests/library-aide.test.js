import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { AppNavbarComponent } from '../app/shared/components/app-navbar/app-navbar.component.js';
import { AideDashboardPage } from '../features/staff/pages/aide-dashboard/aide-dashboard.page.js';

const testsDirectory = path.dirname(fileURLToPath(import.meta.url));
const templatePath = path.resolve(testsDirectory, '..', 'features', 'staff', 'pages', 'aide-dashboard', 'aide-dashboard.html');
const navbarPath = path.resolve(testsDirectory, '..', 'assets', 'js', 'core', 'app-navbar.js');
const componentNavbarPath = path.resolve(testsDirectory, '..', 'app', 'shared', 'components', 'app-navbar', 'app-navbar.component.js');

test('library aide dashboard owns the scan-first shell contract', () => {
  const template = fs.readFileSync(templatePath, 'utf8');

  assert.match(template, /data-app-page="staff-aide-dashboard"/);
  assert.match(template, /data-page="staff-aide-dashboard"/);
  assert.match(template, /data-aide-scan-lane/);
  assert.match(template, /Borrower/);
  assert.match(template, /Copies/);
  assert.match(template, /Confirm/);
  assert.doesNotMatch(template, /Staff Management|College Settings|API Docs/);
});

test('both navbar implementations recognize the library aide profile', () => {
  for (const sourcePath of [navbarPath, componentNavbarPath]) {
    const source = fs.readFileSync(sourcePath, 'utf8');
    assert.match(source, /library_aide/, sourcePath);
    assert.match(source, /renderLibraryAide/, sourcePath);
  }
});

test('component navbar renders operational links for a library aide', async () => {
  const root = {
    dataset: { navbarRole: 'session' },
    innerHTML: '',
    querySelectorAll: () => [],
  };
  const session = {
    load: async () => ({ role: 'admin', access_profile: 'library_aide' }),
  };
  const navbar = new AppNavbarComponent(root, {
    session,
    window: { location: { pathname: '/scan2borrow/staff/aide/dashboard' } },
  });

  await navbar.start();

  for (const pathValue of [
    '/scan2borrow/staff/aide/dashboard',
    '/scan2borrow/staff/circulation',
    '/scan2borrow/staff/students',
    '/scan2borrow/staff/books',
    '/scan2borrow/staff/reports',
    '/scan2borrow/staff/notify',
    '/scan2borrow/logout',
  ]) {
    assert.match(root.innerHTML, new RegExp(`href="${pathValue.replaceAll('/', '\\/')}"`), pathValue);
  }
  assert.doesNotMatch(root.innerHTML, /admin\/staff|admin\/api-docs|Staff Management|College Settings/);
});

test('aide dashboard renders live stats and an explicit empty activity state', () => {
  const stats = new Map([
    ['available-copies', { textContent: '' }],
    ['pending-requests', { textContent: '' }],
    ['active-borrowers', { textContent: '' }],
    ['overdue-items', { textContent: '' }],
  ]);
  const activity = { innerHTML: '' };
  const page = Object.create(AideDashboardPage.prototype);
  page.root = {
    querySelector(selector) {
      if (selector.startsWith('[data-aide-stat=')) return stats.get(selector.match(/"([^"]+)"/)[1]);
      if (selector === '[data-aide-activity]') return activity;
      return null;
    },
  };
  page.displayNumber = (value) => String(Number(value) || 0);
  page.escape = (value) => String(value ?? '');

  page.renderStats({ available_books: 42, pending_approvals: 3, borrowers: 18, overdue_loans: 2 });
  page.renderActivity([]);

  assert.equal(stats.get('available-copies').textContent, '42');
  assert.equal(stats.get('pending-requests').textContent, '3');
  assert.equal(activity.innerHTML, '<p class="aide-state">No pending activity.</p>');
});

test('inventory controls mark destructive mutations as system-admin-only', () => {
  const template = fs.readFileSync(path.resolve(testsDirectory, '..', 'features', 'staff', 'pages', 'inventory', 'inventory.html'), 'utf8');
  const source = fs.readFileSync(path.resolve(testsDirectory, '..', 'features', 'staff', 'pages', 'inventory', 'inventory.page.js'), 'utf8');

  assert.match(template, /data-bulk="archive"[^>]*data-system-admin-only/);
  assert.match(template, /data-bulk="delete"[^>]*data-system-admin-only/);
  assert.match(source, /access_profile === "library_aide"/);
  assert.match(source, /data-act="edit"/);
  assert.match(source, /data-act="copies"/);
});
