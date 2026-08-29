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
