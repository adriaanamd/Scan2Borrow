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

test('shared history retains its teacher-compatible route and role boundary', () => {
  const template = read('features/student/pages/history/history.html');
  const source = read('features/student/pages/history/student-history.page.js');
  assert.match(template, /data-navbar-role="session"/);
  assert.match(template, /id="history-body"/);
  assert.match(template, /<th>Code<\/th>[\s\S]*<th>Fine<\/th>/);
  assert.match(template, /student-history\.page\.js/);
  assert.match(source, /\/scan2borrow\/api\/auth\/session/);
  assert.match(source, /teacher-history-page/);
  assert.match(source, /student-history-page/);
  assert.match(source, /\/scan2borrow\/api\/student\/history/);
});

test('teacher history exposes a scoped Swiss ledger contract', () => {
  const template = read('features/student/pages/history/history.html');
  const styles = read('assets/css/teacher-history.css');
  assert.match(template, /teacher-history\.css/);
  assert.match(styles, /\.teacher-history-page/);
  assert.match(styles, /font-variant-numeric:\s*tabular-nums/);
  assert.match(styles, /teacher-history-status/);
  assert.match(styles, /teacher-history-fine/);
  assert.match(styles, /prefers-reduced-motion/);
});

test('shared history controller exposes teacher row hierarchy hooks', () => {
  const source = read('features/student/pages/history/student-history.page.js');
  assert.match(source, /teacher-history-row/);
  assert.match(source, /teacher-history-status/);
  assert.match(source, /teacher-history-fine/);
  assert.match(source, /row-overdue/);
});

test('teacher history states remain explicit and readable', () => {
  const source = read('features/student/pages/history/student-history.page.js');
  const styles = read('assets/css/teacher-history.css');
  assert.match(source, /teacher-history-state/);
  assert.match(source, /teacher-history-state--empty/);
  assert.match(source, /teacher-history-state--error/);
  assert.match(styles, /teacher-history-state/);
  assert.match(styles, /teacher-history-state--empty/);
  assert.match(styles, /teacher-history-state--error/);
});
