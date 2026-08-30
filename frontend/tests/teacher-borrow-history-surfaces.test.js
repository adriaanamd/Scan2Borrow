import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const testsDirectory = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(testsDirectory, '..');

function read(relativePath) {
  return fs.readFileSync(path.join(root, relativePath), 'utf8');
}

test('teacher Borrow surface boundary retains existing dashboard contracts', () => {
  const source = read('features/teacher/pages/dashboard/dashboard.html');
  assert.match(source, /data-app-page="teacher-dashboard"/);
  assert.match(source, /id="borrowModal"/);
  assert.match(source, /id="borrowForm"/);
  assert.match(source, /id="bulk-scan-barcode"/);
  assert.match(source, /id="bulkBorrowItems"/);
  assert.match(source, /id="bulkBorrowCount"/);
  assert.match(source, /teacher-dashboard\.page\.js/);
  assert.match(source, /teacher-borrow-modal/);
  assert.match(source, /teacher-borrow-modal__scan/);
  assert.match(source, /teacher-borrow-modal__cart/);
  assert.match(source, /teacher-borrow-modal__footer/);
});

test('teacher Borrow modal exposes the dashboard styling contract', () => {
  const template = read('features/teacher/pages/dashboard/dashboard.html');
  const styles = read('assets/css/borrower-dashboards.css');
  assert.match(template, /teacher-borrow-modal/);
  assert.match(template, /teacher-borrow-modal__scan/);
  assert.match(template, /teacher-borrow-modal__cart/);
  assert.match(template, /teacher-borrow-modal__footer/);
  assert.match(styles, /teacher-borrow-modal/);
  assert.match(styles, /prefers-reduced-motion/);
});

test('teacher Borrow controller exposes cart presentation hooks', () => {
  const source = read('features/teacher/pages/dashboard/teacher-dashboard.page.js');
  assert.match(source, /teacher-borrow-cart-row/);
  assert.match(source, /teacher-borrow-cart-actions/);
  assert.match(source, /teacher-borrow-cart-count/);
});

test('teacher Borrow cart keeps rows and controls readable', () => {
  const styles = read('assets/css/borrower-dashboards.css');
  assert.match(styles, /teacher-borrow-cart-row/);
  assert.match(styles, /teacher-borrow-cart-actions/);
  assert.match(styles, /teacher-borrow-cart-count/);
});

test('teacher Borrow and history styling stays out of shared navigation', () => {
  const borrowStyles = read('assets/css/borrower-dashboards.css');
  const historyStyles = read('assets/css/teacher-history.css');
  const dashboard = read('features/teacher/pages/dashboard/dashboard.html');
  const history = read('features/teacher/pages/history/history.html');
  assert.doesNotMatch(borrowStyles, /\.sidebar|\[data-app-navbar\]/);
  assert.doesNotMatch(historyStyles, /\.sidebar|\[data-app-navbar\]/);
  assert.match(dashboard, /\/scan2borrow\/api\/teacher\/dashboard|teacher-dashboard\.page\.js/);
  assert.match(history, /data-navbar-role="teacher"/);
});

test('teacher Borrow and history preserve their existing data contracts', () => {
  const borrow = read('features/teacher/pages/dashboard/teacher-dashboard.page.js');
  const history = read('features/teacher/pages/history/teacher-history.page.js');
  assert.match(borrow, /BulkBorrowCart/);
  assert.match(borrow, /\/scan2borrow\/api\/teacher\/borrow\/lookup/);
  assert.match(borrow, /items\[\$\{index\}\]\[quantity\]/);
  assert.match(history, /\/scan2borrow\/api\/teacher\/history/);
  assert.match(read('features/teacher/pages/history/history.html'), /id="history-body"/);
});

test('teacher catalog tab has a teacher-owned template and role boundary', () => {
  const template = read('features/teacher/pages/borrow/borrow.html');
  const source = read('features/teacher/pages/borrow/teacher-borrow.page.js');

  assert.match(template, /data-app-page="teacher-search"/);
  assert.match(template, /data-navbar-role="teacher"/);
  assert.match(template, /teacher-borrow\.page\.js/);
  assert.doesNotMatch(template, /features\/student\/pages\/search\/student-search\.page\.js/);
  assert.match(template, /teacher-search\.css/);
  assert.match(source, /\/scan2borrow\/api\/teacher\/books/);
  assert.match(source, /\/scan2borrow\/api\/teacher\/borrow/);
  assert.match(template, /teacher-search-page/);
  assert.match(source, /TeacherBorrowPage/);
});

test('teacher history tab has a teacher-owned template and endpoint', () => {
  const template = read('features/teacher/pages/history/history.html');
  const source = read('features/teacher/pages/history/teacher-history.page.js');

  assert.match(template, /data-app-page="teacher-history"/);
  assert.match(template, /data-navbar-role="teacher"/);
  assert.match(template, /teacher-history\.page\.js/);
  assert.doesNotMatch(template, /features\/student\/pages\/history\/student-history\.page\.js/);
  assert.match(template, /teacher-history\.css/);
  assert.match(source, /\/scan2borrow\/api\/teacher\/history/);
  assert.doesNotMatch(source, /roleFromPath\(/);
  assert.match(template, /teacher-history-page/);
  assert.match(source, /classPrefix: "teacher-history"/);
});

test('student history keeps a student-only role boundary', () => {
  const template = read('features/student/pages/history/history.html');
  const source = read('features/student/pages/history/student-history.page.js');
  assert.match(template, /data-navbar-role="student"/);
  assert.match(template, /id="history-body"/);
  assert.match(template, /<th>Code<\/th>[\s\S]*<th>Fine<\/th>/);
  assert.match(template, /student-history\.page\.js/);
  assert.match(template, /student-history-page/);
  assert.match(source, /\/scan2borrow\/api\/student\/history/);
  assert.doesNotMatch(source, /cachedRole\(\)/);
});

test('teacher history exposes a scoped Swiss ledger contract', () => {
  const template = read('features/teacher/pages/history/history.html');
  const styles = read('assets/css/teacher-history.css');
  assert.match(template, /teacher-history\.css/);
  assert.match(styles, /\.teacher-history-page/);
  assert.match(styles, /font-variant-numeric:\s*tabular-nums/);
  assert.match(styles, /teacher-history-status/);
  assert.match(styles, /teacher-history-fine/);
  assert.match(styles, /prefers-reduced-motion/);
});

test('teacher history controller exposes teacher row hierarchy hooks', () => {
  const source = read('features/teacher/pages/history/teacher-history.page.js');
  const shared = read('app/shared/pages/borrower-history.page.js');
  assert.match(source, /classPrefix: "teacher-history"/);
  assert.match(shared, /\$\{this\.classPrefix\}-row/);
  assert.match(shared, /\$\{this\.classPrefix\}-status/);
  assert.match(shared, /\$\{this\.classPrefix\}-fine/);
  assert.match(shared, /row-overdue/);
});

test('teacher history states remain explicit and readable', () => {
  const source = read('features/teacher/pages/history/teacher-history.page.js');
  const shared = read('app/shared/pages/borrower-history.page.js');
  const styles = read('assets/css/teacher-history.css');
  assert.match(source, /classPrefix: "teacher-history"/);
  assert.match(shared, /\$\{this\.classPrefix\}-state/);
  assert.match(shared, /\$\{this\.classPrefix\}-state--empty/);
  assert.match(shared, /\$\{this\.classPrefix\}-state--error/);
  assert.match(styles, /teacher-history-state/);
  assert.match(styles, /teacher-history-state--empty/);
  assert.match(styles, /teacher-history-state--error/);
});
