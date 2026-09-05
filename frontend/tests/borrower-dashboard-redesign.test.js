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
  for (const token of ['var(--app-bg)', 'var(--card)', 'var(--accent)', 'var(--navy)', '#F7F7F8', '#002FA7']) {
    assert.match(css, new RegExp(token.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
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

test('student dashboard uses the shared Scan2Borrow system palette', () => {
  const css = read('assets/css/borrower-dashboards.css');
  const studentTokens = css.slice(css.indexOf('.borrower-dashboard--student'));
  assert.match(studentTokens, /--borrower-surface:\s*var\(--app-bg\)/);
  assert.match(studentTokens, /--borrower-panel:\s*var\(--card\)/);
  assert.match(studentTokens, /--borrower-accent:\s*var\(--accent\)/);
  assert.match(studentTokens, /--borrower-deep:\s*var\(--navy\)/);
  assert.doesNotMatch(studentTokens, /#(?:E8DCC7|D4B895|8B9D83|606C38|B08B6E|C66B3D|C08E3A)/i);
});

test('student dashboard visual rules stay inside the content region', () => {
  const css = read('assets/css/borrower-dashboards.css');
  assert.match(css, /\.borrower-dashboard--student \.content a/);
  assert.doesNotMatch(css, /\.borrower-dashboard--student a\s*\{/);
  assert.doesNotMatch(css, /\.borrower-dashboard--student h[1-6]/);
  assert.doesNotMatch(css, /\.borrower-dashboard :is\(/);
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

test('teacher profile block exposes the Swiss hero composition hooks', () => {
  const source = read('features/teacher/pages/dashboard/dashboard.html');
  for (const selector of ['teacher-dashboard__hero', 'teacher-dashboard__identity', 'teacher-dashboard__library-card']) {
    assert.match(source, new RegExp(selector));
  }
  assert.match(source, /id="teacher-name"/);
  assert.match(source, /id="teacher-meta"/);
  assert.match(source, /id="teacher-barcode"/);
  assert.match(source, /data-bs-target="#borrowModal"/);
  assert.match(source, /data-bs-target="#returnModal"/);
});

test('student statistics expose the Organic stat hooks without changing their data IDs', () => {
  const source = read('features/student/pages/dashboard/dashboard.html');
  for (const id of ['active-count', 'overdue-count', 'fine-total', 'on-time-rate']) {
    assert.match(source, new RegExp(`student-dashboard__stat[\\s\\S]*id="${id}"`));
  }
});

test('teacher statistics expose the Swiss stat hooks without changing their data IDs', () => {
  const source = read('features/teacher/pages/dashboard/dashboard.html');
  for (const id of ['active-count', 'overdue-count', 'fine-total', 'on-time-rate']) {
    assert.match(source, new RegExp(`teacher-dashboard__stat[\\s\\S]*id="${id}"`));
  }
});

test('student work area exposes capacity, shelf, and activity composition hooks', () => {
  const source = read('features/student/pages/dashboard/dashboard.html');
  for (const selector of ['student-dashboard__work-grid', 'student-dashboard__panel', 'student-dashboard__shelf', 'student-dashboard__activity']) {
    assert.match(source, new RegExp(selector));
  }
  for (const id of ['capacity-ring', 'due-soon', 'recommendations', 'recent-activity']) {
    assert.match(source, new RegExp(`id="${id}"`));
  }
});

test('teacher work area exposes desk, activity, and panel composition hooks', () => {
  const source = read('features/teacher/pages/dashboard/dashboard.html');
  for (const selector of ['teacher-dashboard__work-grid', 'teacher-dashboard__panel', 'teacher-dashboard__desk-rail', 'teacher-dashboard__activity']) {
    assert.match(source, new RegExp(selector));
  }
  for (const id of ['capacity-ring', 'due-soon', 'books-per-month', 'risk-level', 'recent-activity', 'current-loans']) {
    assert.match(source, new RegExp(`id="${id}"`));
  }
});

test('student active loans keep quantity and Actions columns inside the reading surface', () => {
  const source = read('features/student/pages/dashboard/dashboard.html');
  assert.match(source, /student-dashboard__loans/);
  assert.match(source, /student-dashboard__table/);
  for (const heading of ['Book', 'Quantity', 'Borrowed', 'Due', 'Status', 'Actions']) {
    assert.match(source, new RegExp(`<th>${heading}<\\/th>`));
  }
  assert.match(source, /id="current-loans"/);
});

test('teacher active loans keep quantity and Actions columns inside the faculty surface', () => {
  const source = read('features/teacher/pages/dashboard/dashboard.html');
  assert.match(source, /teacher-dashboard__loans/);
  assert.match(source, /teacher-dashboard__table/);
  for (const heading of ['Book', 'Quantity', 'Borrowed', 'Due', 'Status', 'Actions']) {
    assert.match(source, new RegExp(`<th>${heading}<\\/th>`));
  }
  assert.match(source, /id="current-loans"/);
});

test('student borrowing modals expose Organic modal and cart hooks', () => {
  const source = read('features/student/pages/dashboard/dashboard.html');
  assert.match(source, /student-dashboard__modal/);
  assert.match(source, /id="bulkBorrowItems"[^>]*student-dashboard__cart/);
  for (const id of ['borrowModal', 'returnModal']) {
    assert.match(source, new RegExp(`id="${id}"`));
  }
});

test('teacher borrowing modals expose Swiss modal and cart hooks', () => {
  const source = read('features/teacher/pages/dashboard/dashboard.html');
  assert.match(source, /teacher-dashboard__modal/);
  assert.match(source, /id="bulkBorrowItems"[^>]*teacher-dashboard__cart/);
  for (const id of ['borrowModal', 'returnModal']) {
    assert.match(source, new RegExp(`id="${id}"`));
  }
});

test('borrower dashboard hero and modal presentation stays in scoped CSS', () => {
  for (const relative of [
    'features/student/pages/dashboard/dashboard.html',
    'features/teacher/pages/dashboard/dashboard.html',
  ]) {
    const source = read(relative);
    assert.doesNotMatch(source, /class="text-muted"\s+style=/);
    assert.doesNotMatch(source, /class="mb-1"\s+style="font-weight: 800"/);
    assert.doesNotMatch(source, /class="modal-header[^\"]*"[\s\S]{0,160}?style=/);
  }
});

test('borrower dashboards define keyboard focus, reduced motion, and status states', () => {
  const css = read('assets/css/borrower-dashboards.css');
  assert.match(css, /:focus-visible/);
  assert.match(css, /prefers-reduced-motion:\s*reduce/);
  assert.match(css, /borrower-dashboard__status/);
  assert.match(css, /pointer-events:\s*none/);
  assert.match(read('features/student/pages/dashboard/student-dashboard.page.js'), /borrower-dashboard__status/);
  assert.match(read('features/teacher/pages/dashboard/teacher-dashboard.page.js'), /borrower-dashboard__status/);
});

test('borrower dashboard roles declare responsive compositions', () => {
  const css = read('assets/css/borrower-dashboards.css');
  assert.match(css, /student-dashboard__identity[\s\S]*flex-direction:\s*column/);
  assert.match(css, /teacher-dashboard__identity[\s\S]*flex-direction:\s*column/);
  assert.match(css, /teacher-dashboard__analytics-grid[\s\S]*grid-template-columns:\s*minmax\(0, 1fr\)/);
  assert.match(css, /student-dashboard__table[\s\S]*overflow-x:\s*auto/);
});

test('borrower summary cards stack into one column at mobile widths', () => {
  const css = read('assets/css/borrower-dashboards.css');
  const tabletStart = css.indexOf('@media (max-width: 768px)');
  const tabletRules = css.slice(
    tabletStart,
    css.indexOf('@media (max-width: 576px)', tabletStart),
  );

  assert.match(tabletRules, /\.borrower-dashboard__stats\s*\{[\s\S]*grid-template-columns:\s*minmax\(0,\s*1fr\)/);
  assert.doesNotMatch(tabletRules, /grid-template-columns:\s*repeat\(2/);
});

test('borrower work panels stack into one column at drawer widths', () => {
  const css = read('assets/css/borrower-dashboards.css');
  const drawerStart = css.indexOf('@media (max-width: 980px)');
  const drawerRules = css.slice(drawerStart, css.indexOf('@media (max-width: 768px)', drawerStart));

  assert.match(drawerRules, /\.borrower-dashboard--student\s+\.student-dashboard__work-grid\s*\{[\s\S]*grid-template-columns:\s*minmax\(0,\s*1fr\)/);
  assert.match(drawerRules, /\.borrower-dashboard--teacher\s+\.teacher-dashboard__analytics-grid\s*,[\s\S]*grid-template-columns:\s*minmax\(0,\s*1fr\)/);
});

test('borrower dashboard redesign preserves content and controller parity', () => {
  const sharedIds = [
    'active-count', 'overdue-count', 'fine-total', 'on-time-rate',
    'capacity-ring', 'capacity-value', 'capacity-remaining', 'capacity-limit',
    'due-soon', 'current-loans', 'borrowModal', 'borrowForm',
    'bulk-scan-barcode', 'bulk-scan-add', 'bulkBorrowCount', 'bulkBorrowItems',
    'borrow-error', 'borrow-message', 'returnModal', 'returnForm',
    'return_input', 'return-message', 'return-error', 'toast-host',
  ];
  for (const relative of [
    'features/student/pages/dashboard/dashboard.html',
    'features/teacher/pages/dashboard/dashboard.html',
  ]) {
    const source = read(relative);
    for (const id of sharedIds) assert.match(source, new RegExp(`id="${id}"`), `${relative} must preserve ${id}`);
  }

  const student = read('features/student/pages/dashboard/student-dashboard.page.js');
  const teacher = read('features/teacher/pages/dashboard/teacher-dashboard.page.js');
  for (const method of ['load', 'render', 'renderLoans', 'renderCart', 'lookupAndAdd', 'submitCart']) {
    assert.match(student, new RegExp(`${method}\\(`));
    assert.match(teacher, new RegExp(`${method}\\(`));
  }
  assert.match(student, /Number\(loan\.quantity \|\| 1\)/);
  assert.match(teacher, /Number\(loan\.quantity \|\| 1\)/);
  assert.match(teacher, /badge bg-\$\{type\} borrower-dashboard__status/);
});

test('borrower dashboards keep shared icon integration and avoid admin-only navigation', () => {
  for (const relative of [
    'features/student/pages/dashboard/dashboard.html',
    'features/teacher/pages/dashboard/dashboard.html',
  ]) {
    const source = read(relative);
    assert.match(source, /assets\/js\/core\/icons\.js/);
    assert.match(source, /assets\/js\/core\/app-navbar\.js/);
    assert.doesNotMatch(source, /admin-overview/);
    assert.ok(source.indexOf('borrower-dashboards.css') > source.indexOf('assets/css/style.css'));
  }
  assert.doesNotMatch(read('assets/css/borrower-dashboards.css'), /[\u{1F300}-\u{1FAFF}]/u);
});

test('borrower dashboards wire shared stats and activity presentation hooks', () => {
  const student = read('features/student/pages/dashboard/dashboard.html');
  const teacher = read('features/teacher/pages/dashboard/dashboard.html');
  assert.match(student, /class="row g-3 mb-4 borrower-dashboard__stats"/);
  assert.match(teacher, /class="row g-3 mb-4 borrower-dashboard__stats"/);
  assert.doesNotMatch(teacher, /class="[^\"]*teacher-dashboard__activity[^\"]*"\s+style=/);
  assert.match(teacher, /teacher-dashboard__activity/);
  assert.match(teacher, /activity-view-all/);
});

test('teacher return submissions include the CSRF token required by the API', () => {
  const source = read('features/teacher/pages/dashboard/teacher-dashboard.page.js');
  assert.match(source, /body\.append\("csrf", this\.csrf\)/);
});

test('borrower return surfaces explain that the librarian must verify the book', () => {
  for (const relative of [
    'features/student/pages/dashboard/dashboard.html',
    'features/teacher/pages/dashboard/dashboard.html',
    'features/student/pages/dashboard/student-dashboard.page.js',
    'features/teacher/pages/dashboard/teacher-dashboard.page.js',
  ]) {
    assert.match(read(relative), /librarian/i, relative);
  }
  assert.match(read('features/student/pages/dashboard/student-dashboard.page.js'), /Return Verification Pending/);
  assert.match(read('features/teacher/pages/dashboard/teacher-dashboard.page.js'), /Return Verification Pending/);
});
