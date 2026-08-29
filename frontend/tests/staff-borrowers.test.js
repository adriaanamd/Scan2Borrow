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
