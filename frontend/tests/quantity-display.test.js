import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');

test('inventory and catalog cards expose total and available quantities', () => {
  assert.match(read('features/staff/pages/inventory/inventory.page.js'), /book\.quantity/);
  assert.match(read('features/student/pages/search/student-search.page.js'), /available_quantity/);
  assert.match(read('features/student/pages/search/student-search.page.js'), /quantity/);
  assert.match(read('features/staff/pages/inventory/inventory.html'), /<th[^>]*>Quantity<\/th>/i);
});

test('barcode lookup does not pin an unavailable copy when the title has other available copies', () => {
  for (const relative of [
    'features/student/pages/search/student-search.page.js',
    'features/student/pages/dashboard/student-dashboard.page.js',
    'features/teacher/pages/dashboard/teacher-dashboard.page.js',
  ]) {
    assert.match(
      read(relative),
      /copy\.status === ["']Available["'] \? barcode : ["']["']/,
      `${relative} must let the server allocate another available copy for a title.`,
    );
  }
});

test('borrower, receipt, and staff approval surfaces render quantities', () => {
  assert.match(read('features/student/pages/dashboard/student-dashboard.page.js'), /loan\.quantity/);
  assert.match(read('features/student/pages/history/student-history.page.js'), /item\.quantity/);
  assert.match(read('features/student/pages/receipt/receipt.page.js'), /quantity/);
  assert.match(read('features/staff/pages/dashboard/staff-dashboard.page.js'), /row\.book_count/);
});

test('staff activity, overdue, and report surfaces expose quantities', () => {
  assert.match(read('features/staff/pages/dashboard/dashboard.html'), /<th>Quantity<\/th>/i);
  assert.match(read('features/staff/pages/dashboard/staff-dashboard.page.js'), /row\.quantity/);
  assert.match(read('features/staff/pages/overdue/overdue.html'), /<th>Quantity<\/th>/i);
  assert.match(read('features/staff/pages/overdue/overdue.page.js'), /row\.quantity/);
  assert.match(read('features/staff/pages/reports/reports.page.js'), /quantity/);
});
