import test from 'node:test';
import assert from 'node:assert/strict';
import { normalizeGuestDashboard, normalizeGuestBook, normalizeGuestHistory, normalizeGuestVisitor } from '../features/guest/models/index.js';
import {
  GuestBorrowRequestService,
  GuestBorrowedService,
  GuestCatalogService,
  GuestDashboardService,
  GuestHistoryService,
  GuestProfileService,
  GuestReceiptService,
  GuestReturnService,
} from '../features/guest/services/index.js';

test('guest models normalize visitor summary and preserve status strings', () => {
  const source = {
    visitor: { name: 'Pat', account_status: 'Pending', visitor_number: null },
    summary: { active: '1', returned: '2' },
    history: [{ request_status: 'Return Verification Pending' }],
  };
  const dashboard = normalizeGuestDashboard(source);
  assert.deepEqual(dashboard.visitor, {
    name: 'Pat', visitor_number: '', account_status: 'Pending', registration_expires_at: '', contact_no: '', email: '',
    purpose: '', purpose_other: '', id_type: '', id_barcode: '', photo_data: '',
  });
  assert.deepEqual(dashboard.summary, { active: 1, returned: 2, overdue: 0, total: 0 });
  assert.deepEqual(source.visitor, { name: 'Pat', account_status: 'Pending', visitor_number: null });
  assert.equal(normalizeGuestHistory(source.history)[0].request_status, 'Return Verification Pending');
});

test('guest book and visitor models provide stable defaults without erasing fields', () => {
  assert.deepEqual(normalizeGuestVisitor({ name: 'Pat', photo_data: 'data:image' }), {
    name: 'Pat', visitor_number: '', account_status: 'Active', registration_expires_at: '', contact_no: '', email: '',
    purpose: '', purpose_other: '', id_type: '', id_barcode: '', photo_data: 'data:image',
  });
  assert.deepEqual(normalizeGuestBook({ id: '7', title: 'Book', status: 'Available' }), {
    id: '7', title: 'Book', author: '', category_name: '', isbn: '', call_number: '', cover_file: '', status: 'Available',
  });
});

test('guest services preserve portal endpoints, query names, and form fields', async () => {
  const calls = [];
  const api = {
    get: async (path, params) => { calls.push({ method: 'GET', path, params }); return { ok: true, data: {} }; },
    post: async (path, body) => { calls.push({ method: 'POST', path, body }); return { ok: true, data: {} }; },
  };
  await new GuestDashboardService({ api }).load();
  await new GuestCatalogService({ api }).browse({ search: 'book', category: 'Research', floor: '2', id: '7' });
  await new GuestBorrowedService({ api }).load();
  await new GuestHistoryService({ api }).load({ status: 'Released', from: '2026-01-01', to: '2026-08-29' });
  await new GuestBorrowRequestService({ api }).submit({ book_id: '7', verification_photo: 'photo' });
  await new GuestReturnService({ api }).submit({ barcode: 'B-7', photo_data: 'return-photo' });
  await new GuestProfileService({ api }).load();
  await new GuestProfileService({ api }).update({ contact_no: '0917' });
  await new GuestReceiptService({ api }).load(19);
  assert.deepEqual(calls, [
    { method: 'GET', path: '/scan2borrow/api/guest/dashboard', params: {} },
    { method: 'GET', path: '/scan2borrow/api/guest/books', params: { search: 'book', category: 'Research', floor: '2', id: '7' } },
    { method: 'GET', path: '/scan2borrow/api/guest/borrowed', params: {} },
    { method: 'GET', path: '/scan2borrow/api/guest/history', params: { status: 'Released', from: '2026-01-01', to: '2026-08-29' } },
    { method: 'POST', path: '/scan2borrow/api/guest/borrow', body: { book_id: '7', verification_photo: 'photo' } },
    { method: 'POST', path: '/scan2borrow/api/guest/return', body: { barcode: 'B-7', photo_data: 'return-photo' } },
    { method: 'GET', path: '/scan2borrow/api/guest/profile', params: {} },
    { method: 'POST', path: '/scan2borrow/api/guest/profile', body: { contact_no: '0917' } },
    { method: 'GET', path: '/scan2borrow/api/guest/receipt', params: { id: 19 } },
  ]);
});
