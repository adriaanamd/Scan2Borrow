import test from 'node:test';
import assert from 'node:assert/strict';
import { normalizeDashboard, normalizeBook, normalizeLoan, normalizeUser } from '../features/student/models/index.js';
import { StudentDashboardService, StudentSearchService, StudentSettingsService } from '../features/student/services/index.js';

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
    title: 'Title', author: '', borrow_date: '', due_date: '', status: 'Overdue', return_status: 'none', transaction_code: '',
  });
  assert.equal(normalizeLoan({ status: 'Return Verification Pending', return_status: 'pending' }).return_status, 'pending');
});

test('student services preserve endpoint paths, query names, and action fields', async () => {
  const calls = [];
  const api = {
    get: async (path, params) => { calls.push({ method: 'GET', path, params }); return { ok: true, data: { user: {} } }; },
    post: async (path, body) => { calls.push({ method: 'POST', path, body }); return { ok: true, data: {} }; },
  };
  const dashboard = new StudentDashboardService({ api });
  const search = new StudentSearchService({ api });
  const settings = new StudentSettingsService({ api });

  await dashboard.load();
  await dashboard.borrow('BOOK-1', '2026-09-01');
  await dashboard.returnBook('TXN-1');
  await search.search({ search: 'clean', category_name: 'Programming', floor: '2', sort: 'title' });
  await search.borrow('BOOK-2');
  await settings.load();

  assert.deepEqual(calls, [
    { method: 'GET', path: '/scan2borrow/api/student/dashboard', params: {} },
    { method: 'POST', path: '/scan2borrow/api/student/dashboard', body: { action: 'borrow', book_barcode: 'BOOK-1', due_date: '2026-09-01' } },
    { method: 'POST', path: '/scan2borrow/api/student/dashboard', body: { action: 'return_unified', return_input: 'TXN-1' } },
    { method: 'GET', path: '/scan2borrow/api/student/books', params: { search: 'clean', category_name: 'Programming', floor: '2', sort: 'title' } },
    { method: 'POST', path: '/scan2borrow/api/student/borrow', body: { action: 'borrow', book_barcode: 'BOOK-2' } },
    { method: 'GET', path: '/scan2borrow/api/student/dashboard', params: {} },
  ]);
});
