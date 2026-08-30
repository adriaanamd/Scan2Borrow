import test from 'node:test';
import assert from 'node:assert/strict';
import { BorrowerService } from '../features/staff/services/borrower.service.js';
import { BorrowersPage } from '../features/staff/pages/borrowers/borrowers.page.js';
import { BorrowerDetailPage } from '../features/staff/pages/borrower-detail/borrower-detail.page.js';

test('BorrowerService preserves search, detail, photo, and notification payload contracts', async () => {
  const calls = [];
  const api = {
    get: async (path, params) => { calls.push({ method: 'GET', path, params }); return { ok: true, data: {} }; },
    post: async (path, body) => { calls.push({ method: 'POST', path, body }); return { ok: true }; },
  };
  const service = new BorrowerService({ api });
  await service.search('Lee');
  await service.details(7);
  await service.updatePhoto(7, 'photo-data');
  await service.notify(7, 'email');
  assert.deepEqual(calls, [
    { method: 'GET', path: '/scan2borrow/api/staff/borrowers', params: { search: 'Lee' } },
    { method: 'GET', path: '/scan2borrow/api/staff/borrower', params: { id: 7 } },
    { method: 'POST', path: '/scan2borrow/api/staff/borrower/photo', body: { user_id: 7, photo_data: 'photo-data' } },
    { method: 'POST', path: '/scan2borrow/api/staff/notify', body: { user_id: 7, channel: 'email' } },
  ]);
});

test('staff borrower list and detail pages expose separate workflow boundaries', () => {
  assert.equal(BorrowersPage.name, 'BorrowersPage');
  assert.equal(typeof BorrowersPage.prototype.load, 'function');
  assert.equal(BorrowerDetailPage.name, 'BorrowerDetailPage');
  assert.equal(typeof BorrowerDetailPage.prototype.load, 'function');
  assert.equal(typeof BorrowerDetailPage.prototype.renderHistory, 'function');
});

test('staff borrower rows keep every value under its matching table header', () => {
  const body = { innerHTML: '' };
  const page = new BorrowersPage({ querySelector: () => body });

  page.render([{
    id: 7,
    barcode: 'T-007',
    name: 'Grace Hopper',
    role: 'teacher',
    department: 'Science',
    position: 'Faculty',
    course: 'Computer Science',
    year_level: '3',
    active_loans: 2,
    overdue_loans: 1,
    status: 'active',
  }]);

  assert.equal((body.innerHTML.match(/<td/g) || []).length, 10);
  assert.match(body.innerHTML, /<td>T-007<\/td>\s*<td>Grace Hopper<\/td>\s*<td>Teacher<\/td>/);
  assert.match(body.innerHTML, /<td>Science<\/td>\s*<td>Faculty<\/td>\s*<td>Computer Science<\/td>\s*<td>3<\/td>/);
  assert.match(body.innerHTML, /<span class="badge bg-primary">2<\/span> <span class="badge bg-danger">1 overdue<\/span>/);
  assert.match(body.innerHTML, /<td>active<\/td>/);
  assert.match(body.innerHTML, /staff\/borrower\?id=7/);
  assert.match(body.innerHTML, /staff\/notify\?id=7/);
});

test('staff borrower rows use safe placeholders and encoded action ids', () => {
  const body = { innerHTML: '' };
  const page = new BorrowersPage({ querySelector: () => body });

  page.render([{
    id: '7/8',
    barcode: 'T&008',
    name: '<Grace>',
    role: '',
    department: '',
    position: null,
    course: null,
    year_level: null,
    active_loans: 0,
    overdue_loans: 0,
    status: '',
  }]);

  assert.match(body.innerHTML, /T&amp;008/);
  assert.match(body.innerHTML, /&lt;Grace&gt;/);
  assert.match(body.innerHTML, /<td>—<\/td>\s*<td>—<\/td>/);
  assert.match(body.innerHTML, /<td>—<\/td>\s*<td class="text-nowrap">/);
  assert.match(body.innerHTML, /borrower\?id=7%2F8/);
  assert.match(body.innerHTML, /notify\?id=7%2F8/);
  assert.doesNotMatch(body.innerHTML, /<Grace>/);
});
