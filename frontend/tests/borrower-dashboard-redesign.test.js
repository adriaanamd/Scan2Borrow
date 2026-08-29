import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

test('student and teacher dashboards expose their redesign scopes and shared stylesheet', () => {
  const student = read('features/student/pages/dashboard/dashboard.html');
  const teacher = read('features/teacher/pages/dashboard/dashboard.html');
  const href = '/scan2borrow/frontend/assets/css/borrower-dashboards.css';

  assert.match(student, /class="[^"]*borrower-dashboard[^"]*borrower-dashboard--student/);
  assert.match(teacher, /class="[^"]*borrower-dashboard[^"]*borrower-dashboard--teacher/);
  assert.equal(student.includes(href), true);
  assert.equal(teacher.includes(href), true);
  assert.ok(student.indexOf('/scan2borrow/frontend/assets/css/style.css') < student.indexOf(href));
  assert.ok(teacher.indexOf('/scan2borrow/frontend/assets/css/style.css') < teacher.indexOf(href));
});

test('borrower dashboard content and interaction IDs remain intact', () => {
  for (const relative of [
    'features/student/pages/dashboard/dashboard.html',
    'features/teacher/pages/dashboard/dashboard.html',
  ]) {
    const source = read(relative);
    for (const id of ['borrowForm', 'returnForm', 'current-loans', 'bulkBorrowItems', 'bulkBorrowCount', 'borrowModal', 'returnModal']) {
      assert.match(source, new RegExp(`(?:id|data-bs-target)="[^"#]*#?${id}`), `${relative} must retain ${id}`);
    }
  }
});

test('approved student and teacher design tokens are present and role-scoped', () => {
  const css = read('assets/css/borrower-dashboards.css');
  for (const token of ['#E8DCC7', '#D4B895', '#8B9D83', '#606C38', '#B08B6E', '#C66B3D', '#C08E3A', '#F7F7F8', '#002FA7']) {
    assert.match(css, new RegExp(token.replace('#', '\\#')));
  }
  assert.match(css, /\.borrower-dashboard--student/);
  assert.match(css, /\.borrower-dashboard--teacher/);
});

test('borrower dashboards expose shared layout primitives', () => {
  const css = read('assets/css/borrower-dashboards.css');
  for (const selector of [
    '.borrower-dashboard__hero',
    '.borrower-dashboard__stats',
    '.borrower-dashboard__work-grid',
    '.borrower-dashboard__panel',
    '.borrower-dashboard__table',
  ]) {
    assert.match(css, new RegExp(selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  }
});

test('student dashboard declares its Organic surface and display typography', () => {
  const css = read('assets/css/borrower-dashboards.css');
  assert.match(css, /\.borrower-dashboard--student[\s\S]*background:\s*var\(--borrower-surface\)/);
  assert.match(css, /Fraunces/);
  assert.match(css, /border-radius:\s*22px/);
});

test('teacher dashboard declares its Swiss surface and data typography', () => {
  const css = read('assets/css/borrower-dashboards.css');
  assert.match(css, /\.borrower-dashboard--teacher[\s\S]*#F7F7F8/);
  assert.match(css, /Helvetica Neue/);
  assert.match(css, /font-variant-numeric:\s*tabular-nums/);
  assert.match(css, /\.borrower-dashboard--teacher[\s\S]*border:\s*1px solid/);
});

test('student profile block exposes the Organic hero composition hooks', () => {
  const source = read('features/student/pages/dashboard/dashboard.html');
  for (const selector of ['student-dashboard__hero', 'student-dashboard__identity', 'student-dashboard__library-card']) {
    assert.match(source, new RegExp(selector));
  }
  assert.match(source, /id="borrower-name"/);
  assert.match(source, /id="borrower-meta"/);
  assert.match(source, /id="borrower-barcode"/);
  assert.match(source, /data-bs-target="#borrowModal"/);
  assert.match(source, /data-bs-target="#returnModal"/);
});
