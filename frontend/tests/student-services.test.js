import test from 'node:test';
import assert from 'node:assert/strict';
import { normalizeDashboard, normalizeBook, normalizeLoan, normalizeUser } from '../features/student/models/index.js';

test('student models normalize dashboard defaults without mutating source data', () => {
  const source = {
    user: { name: 'Ari', barcode: 'S-1' },
    stats: { active: '2', fines: '4.5' },
    recommended: [{ title: 'Clean Code' }],
    current_loans: [{ title: 'Book', status: 'Borrowed' }],
  };
  const dashboard = normalizeDashboard(source);

  assert.deepEqual(dashboard.user, { name: 'Ari', barcode: 'S-1', role: 'Student', course: '', year_level: '' });
  assert.deepEqual(dashboard.stats, { active: 2, overdue: 0, fines: 4.5, on_time_rate: 100 });
  assert.equal(dashboard.recommended[0].title, 'Clean Code');
  assert.equal(dashboard.current_loans[0].status, 'Borrowed');
  assert.equal(dashboard.max_books, 3);
  assert.deepEqual(source, {
    user: { name: 'Ari', barcode: 'S-1' },
    stats: { active: '2', fines: '4.5' },
    recommended: [{ title: 'Clean Code' }],
    current_loans: [{ title: 'Book', status: 'Borrowed' }],
  });
});

test('student models normalize books, loans, and user role fallback', () => {
  assert.deepEqual(normalizeUser({ name: 'Teacher', role: '' }), {
    name: 'Teacher', barcode: '', role: 'Student', course: '', year_level: '',
  });
  assert.deepEqual(normalizeBook({ barcode: 'B-1', title: 'Title', status: 'Available' }), {
    barcode: 'B-1', title: 'Title', author: '', category_name: '', status: 'Available',
    description: '', publisher: '', floor_no: '', shelf_no: '', row_no: '',
    cover_file: '', cover_image: '', already_borrowed: false,
  });
  assert.deepEqual(normalizeLoan({ title: 'Title', status: 'Overdue' }), {
    title: 'Title', author: '', borrow_date: '', due_date: '', status: 'Overdue', transaction_code: '',
  });
});
