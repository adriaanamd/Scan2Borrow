import test from 'node:test';
import assert from 'node:assert/strict';
import { StaffCirculationService } from '../features/staff/services/staff-circulation.service.js';
import { CirculationPage } from '../features/staff/pages/circulation/circulation.page.js';

class FakeFormData {
  constructor() { this.values = []; }
  append(name, value) { this.values.push([name, String(value)]); }
}

function makeDocument() {
  const nodes = new Map();
  const node = (id, extra = {}) => {
    const value = {
      id,
      hidden: false,
      value: '',
      textContent: '',
      innerHTML: '',
      dataset: {},
      addEventListener() {},
      removeEventListener() {},
      classList: { add() {}, remove() {}, toggle() {} },
      ...extra,
    };
    nodes.set(id, value);
    return value;
  };
  return {
    nodes,
    node,
    getElementById(id) { return nodes.get(id) || null; },
    querySelector(selector) {
      if (selector.startsWith('#')) return nodes.get(selector.slice(1)) || null;
      return null;
    },
  };
}

function circulationDocument() {
  const document = makeDocument();
  [
    'circulation-status',
    'circulation-borrower',
    'circulation-borrower-course',
    'circulation-borrower-year',
    'circulation-borrower-status',
    'circulation-borrower-fine',
    'circulation-borrow-copy-barcode',
    'circulation-return-copy-barcode',
    'circulation-borrow-copy-list',
    'circulation-return-loans',
    'circulation-requested-count',
    'circulation-requested-books',
    'circulation-history-count',
    'circulation-history-list',
    'circulation-summary-active',
    'circulation-summary-returned',
    'circulation-summary-overdue',
    'circulation-summary-fine',
    'circulation-review-modal',
    'circulation-review-items',
    'circulation-approve-borrow',
  ].forEach((id) => document.node(id));
  return document;
}

test('StaffCirculationService sends repeated barcode fields through FormData', async () => {
  const calls = [];
  const api = {
    get(path, params) { calls.push({ method: 'GET', path, params }); return Promise.resolve({ data: { borrower: {}, active_loans: [] } }); },
    post(path, body) { calls.push({ method: 'POST', path, body }); return Promise.resolve({ ok: true, data: {} }); },
  };
  const service = new StaffCirculationService({ api, formDataFactory: () => new FakeFormData() });

  await service.lookupStudent('STU-001');
  await service.borrow({ borrowerBarcode: 'STU-001', bookBarcodes: ['COPY-001', 'COPY-002'], dueDate: '2026-09-04' });
  await service.returnBooks({ borrowerId: 7, loanIds: [55, 56], copyBarcodes: ['COPY-001', 'COPY-002'] });
  await service.decideBorrowing('approve', 91);

  assert.deepEqual(calls[0], { method: 'GET', path: '/scan2borrow/api/staff/circulation/borrower', params: { barcode: 'STU-001' } });
  assert.deepEqual(calls[1].body.values, [
    ['borrower_barcode', 'STU-001'],
    ['book_barcodes[]', 'COPY-001'],
    ['book_barcodes[]', 'COPY-002'],
    ['due_date', '2026-09-04'],
  ]);
  assert.deepEqual(calls[2].body.values, [
    ['borrower_id', '7'],
    ['loan_ids[]', '55'],
    ['loan_ids[]', '56'],
    ['copy_barcodes[]', 'COPY-001'],
    ['copy_barcodes[]', 'COPY-002'],
  ]);
  assert.deepEqual(calls[3], {
    method: 'POST',
    path: '/scan2borrow/api/staff/borrowing-action',
    body: { action: 'approve', borrowing_id: 91 },
  });
});

test('CirculationPage keeps scanned and manually entered borrow copies in one review flow', async () => {
  const document = circulationDocument();
  const calls = [];
  const service = {
    lookupStudent: async () => ({ data: {
      borrower: { id: 7, barcode: 'STU-001', name: 'Ada Lovelace', role: 'student', active_loans: 0 },
      active_loans: [],
    } }),
    borrow: async (payload) => { calls.push(payload); return { data: { transaction_code: 'S2B-DIRECT' }, message: 'Borrowing transaction approved.' }; },
    returnBooks: async () => ({ data: { returned: 0 }, message: 'Return processed successfully.' }),
  };
  const confirmations = [];
  const page = new CirculationPage(document, {
    service,
    document,
    window: { Scan2BorrowConfirmation: { confirm: async (config) => { confirmations.push(config); await config.onConfirm(); return true; } } },
  });

  await page.lookupStudent('STU-001');
  page.addBorrowCopy('COPY-SCANNED');
  page.addBorrowCopy('COPY-MANUAL');
  page.reviewBorrow();
  assert.equal(document.getElementById('circulation-review-modal').hidden, false);
  await page.approveBorrow();

  assert.equal(confirmations.length, 1);
  assert.deepEqual(calls[0].bookBarcodes, ['COPY-SCANNED', 'COPY-MANUAL']);
  assert.equal(page.borrowCopies.length, 0);
  page.rejectBorrow();
  assert.equal(calls.length, 1);
});

test('CirculationPage renders the verified borrower workflow sections from lookup data', async () => {
  const document = circulationDocument();
  const service = {
    lookupStudent: async () => ({ data: {
      borrower: {
        id: 7,
        barcode: 'STU-001',
        name: 'Ada Lovelace',
        role: 'student',
        status: 'active',
        course: 'Computer Science',
        year_level: '3',
        active_loans: 1,
      },
      active_loans: [{ id: 55, copy_barcode: 'COPY-001', title: 'Clean Code', due_date: '2026-09-04' }],
      requested_books: [{ id: 91, title: 'Algorithms', author: 'Thomas Cormen', requested_at: '2026-10-30', status: 'Pending' }],
      summary: { active: 1, returned: 2, overdue: 0, total_fine: 0 },
      history: [{ title: 'Clean Code', author: 'Robert C. Martin', status: 'Returned', borrow_date: '2026-08-01', return_date: '2026-08-08' }],
    } }),
  };
  const page = new CirculationPage(document, { service, document, window: {} });

  await page.lookupStudent('STU-001');

  assert.equal(document.getElementById('circulation-borrower-course').textContent, 'Computer Science');
  assert.equal(document.getElementById('circulation-borrower-year').textContent, '3');
  assert.match(document.getElementById('circulation-requested-books').innerHTML, /Algorithms/);
  assert.match(document.getElementById('circulation-requested-books').innerHTML, /Approve/);
  assert.match(document.getElementById('circulation-history-list').innerHTML, /Clean Code/);
  assert.equal(document.getElementById('circulation-summary-active').textContent, '1');
});

test('CirculationPage selects exact active loans for scanned and manual returns', async () => {
  const document = circulationDocument();
  const calls = [];
  const service = {
    lookupStudent: async () => ({ data: {
      borrower: { id: 7, barcode: 'STU-001', name: 'Ada Lovelace', role: 'student', active_loans: 2 },
      active_loans: [
        { id: 55, copy_barcode: 'COPY-001', title: 'Clean Code', due_date: '2026-09-04' },
        { id: 56, copy_barcode: 'COPY-002', title: 'Refactoring', due_date: '2026-09-04' },
      ],
    } }),
    borrow: async () => ({ data: {}, message: '' }),
    returnBooks: async (payload) => { calls.push(payload); return { data: { returned: 2, total_fine: 0 }, message: 'Return processed successfully.' }; },
  };
  const page = new CirculationPage(document, { service, document, window: {} });

  await page.lookupStudent('STU-001');
  page.addReturnCopy('COPY-001');
  page.selectReturnLoan(56, true);
  await page.submitReturn();

  assert.deepEqual(calls[0], {
    borrowerId: 7,
    loanIds: [55, 56],
    copyBarcodes: ['COPY-001', 'COPY-002'],
  });
  assert.equal(page.returnLoans.length, 0);
});

test('CirculationPage keeps normalized and legacy loans distinct when numeric IDs overlap', async () => {
  const document = circulationDocument();
  const calls = [];
  const service = {
    lookupStudent: async () => ({ data: {
      borrower: { id: 7, barcode: 'STU-001', name: 'Ada Lovelace', role: 'student', active_loans: 2 },
      active_loans: [
        { id: 55, storage: 'normalized', loan_key: 'normalized:55', copy_barcode: 'COPY-001', title: 'Clean Code' },
        { id: 55, storage: 'legacy', loan_key: 'legacy:55', copy_barcode: 'LEGACY-001', title: 'Legacy Copy' },
      ],
    } }),
    returnBooks: async (payload) => { calls.push(payload); return { data: { returned: 2 }, message: 'Return processed successfully.' }; },
  };
  const page = new CirculationPage(document, { service, document, window: {} });

  await page.lookupStudent('STU-001');
  page.addReturnCopy('COPY-001');
  page.addReturnCopy('LEGACY-001');
  await page.submitReturn();

  assert.deepEqual(calls[0], {
    borrowerId: 7,
    loanIds: [55, 55],
    copyBarcodes: ['COPY-001', 'LEGACY-001'],
  });
});
