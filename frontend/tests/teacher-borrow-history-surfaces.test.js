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
