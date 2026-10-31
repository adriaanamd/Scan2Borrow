import test from 'node:test';
import assert from 'node:assert/strict';
import { BorrowerLookupModal } from '../features/staff/components/borrower-lookup-modal/borrower-lookup-modal.component.js';

function borrowerLookupFixture() {
  const nodes = {};
  const makeNode = (id) => {
    const node = {
      id,
      hidden: false,
      disabled: false,
      value: '',
      textContent: '',
      innerHTML: '',
      dataset: {},
      href: '',
      focused: false,
      listeners: new Map(),
      addEventListener(name, callback) { this.listeners.set(name, callback); },
      removeEventListener(name) { this.listeners.delete(name); },
      focus() { this.focused = true; },
      setAttribute(name, value) { this[name] = value; },
    };
    nodes[id] = node;
    return node;
  };

  [
    'borrower-lookup-form',
    'borrower-lookup-barcode',
    'borrower-lookup-scanner',
    'borrower-lookup-status',
    'borrower-lookup-result',
    'borrower-lookup-name',
    'borrower-lookup-id',
    'borrower-lookup-role',
    'borrower-lookup-status-value',
    'borrower-lookup-active',
    'borrower-lookup-loans',
    'borrower-lookup-details',
    'borrower-lookup-circulation',
  ].forEach(makeNode);
  nodes['borrower-lookup-result'].hidden = true;
  nodes.submit = makeNode('borrower-lookup-submit');
  nodes.submit.type = 'submit';
  nodes.camera = makeNode('borrower-lookup-camera');

  const root = makeNode('borrower-lookup-modal');
  root.querySelector = (selector) => {
    if (selector === '#borrower-lookup-form') return nodes['borrower-lookup-form'];
    if (selector === '#borrower-lookup-scanner') return nodes['borrower-lookup-scanner'];
    return null;
  };
  root.querySelectorAll = (selector) => selector === '#borrower-lookup-form button, #borrower-lookup-barcode'
    ? [nodes['borrower-lookup-barcode'], nodes.submit, nodes.camera]
    : [];

  const document = {
    getElementById(id) { return nodes[id] || null; },
  };
  return { document, root, nodes };
}

test('lookup renders borrower identity and active loans', async () => {
  const fixture = borrowerLookupFixture();
  const modal = new BorrowerLookupModal(fixture.root, {
    service: { lookupByBarcode: async (barcode) => ({ data: {
      borrower: { id: 7, barcode, name: 'Ada Lovelace', role: 'student', status: 'active', active_loans: 1 },
      active_loans: [{ title: 'Clean Code', copy_barcode: 'COPY-001', due_date: '2026-08-27' }],
    } }) },
    document: fixture.document,
    window: {},
  }).start();

  await modal.lookup('  STU-001  ');

  assert.equal(fixture.nodes['borrower-lookup-result'].hidden, false);
  assert.equal(fixture.nodes['borrower-lookup-name'].textContent, 'Ada Lovelace');
  assert.equal(fixture.nodes['borrower-lookup-id'].textContent, 'STU-001');
  assert.match(fixture.nodes['borrower-lookup-loans'].innerHTML, /Clean Code/);
  assert.match(fixture.nodes['borrower-lookup-loans'].innerHTML, /COPY-001/);
  assert.match(fixture.nodes['borrower-lookup-loans'].innerHTML, /2026-08-27/);
  assert.equal(fixture.nodes['borrower-lookup-details'].href, '/scan2borrow/staff/borrower?id=7');
});

test('lookup shows an explicit empty state for zero active loans', async () => {
  const fixture = borrowerLookupFixture();
  const modal = new BorrowerLookupModal(fixture.root, {
    service: { lookupByBarcode: async () => ({ data: { borrower: { id: 7, barcode: 'STU-001', name: 'Ada Lovelace', active_loans: 0 }, active_loans: [] } }) },
    document: fixture.document,
    window: {},
  }).start();

  await modal.lookup('STU-001');

  assert.match(fixture.nodes['borrower-lookup-loans'].innerHTML, /No active loans/);
  assert.doesNotMatch(fixture.nodes['borrower-lookup-loans'].innerHTML, /COPY-|Clean Code/);
});

test('failed lookup clears stale data and shows the server error', async () => {
  const fixture = borrowerLookupFixture();
  let fail = false;
  const modal = new BorrowerLookupModal(fixture.root, {
    service: { lookupByBarcode: async () => {
      if (fail) throw new Error('Student account not found for that ID.');
      return { data: { borrower: { id: 7, name: 'Ada Lovelace' }, active_loans: [{ title: 'Clean Code' }] } };
    } },
    document: fixture.document,
    window: {},
  }).start();

  await modal.lookup('STU-001');
  fail = true;
  await modal.lookup('UNKNOWN');

  assert.equal(fixture.nodes['borrower-lookup-result'].hidden, true);
  assert.equal(fixture.nodes['borrower-lookup-loans'].innerHTML, '');
  assert.equal(fixture.nodes['borrower-lookup-status'].textContent, 'Student account not found for that ID.');
  assert.equal(fixture.nodes['borrower-lookup-status'].dataset.state, 'error');
});

test('scanner lifecycle shares lookup and closes cleanly', async () => {
  const fixture = borrowerLookupFixture();
  let options;
  let lookedUpBarcode = '';
  let destroyed = false;
  const modal = new BorrowerLookupModal(fixture.root, {
    service: { lookupByBarcode: async (barcode) => {
      lookedUpBarcode = barcode;
      return { data: { borrower: { id: 7, barcode, name: 'Ada Lovelace', active_loans: 0 }, active_loans: [] } };
    } },
    document: fixture.document,
    window: {},
    scannerFactory: (_root, scannerOptions) => {
      options = scannerOptions;
      return { start() {}, destroy() { destroyed = true; } };
    },
  }).start();

  modal.onShown();
  await options.onDecoded('STU-001');
  await modal.onHidden();

  assert.equal(lookedUpBarcode, 'STU-001');
  assert.equal(destroyed, true);
});
