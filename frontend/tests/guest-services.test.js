import test from 'node:test';
import assert from 'node:assert/strict';
import { normalizeGuestDashboard, normalizeGuestBook, normalizeGuestHistory, normalizeGuestVisitor } from '../features/guest/models/index.js';

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
