import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { CopyPanelComponent } from '../features/staff/components/copy-panel/copy-panel.component.js';
import { BarcodePrintPage } from '../features/staff/pages/barcodes/barcodes.page.js';

const testsDirectory = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(testsDirectory, '..');
const inventoryTemplate = path.join(root, 'features', 'staff', 'pages', 'inventory', 'inventory.html');
const printTemplate = path.join(root, 'features', 'staff', 'pages', 'barcodes', 'barcodes.html');
const printController = path.join(root, 'features', 'staff', 'pages', 'barcodes', 'barcodes.page.js');

test('copy panel exposes irreversible unprinted export and historical PDF controls', () => {
  const source = fs.readFileSync(inventoryTemplate, 'utf8');
  assert.match(source, /id="copy-export-unprinted"/);
  assert.match(source, /id="copy-print-history"/);
  assert.match(source, /Save as PDF|Export unprinted barcodes/);
  assert.match(fs.readFileSync(path.join(root, 'features', 'staff', 'components', 'copy-panel', 'copy-panel.component.js'), 'utf8'), /barcode-print-batches/);
  assert.match(fs.readFileSync(path.join(root, 'features', 'staff', 'components', 'copy-panel', 'copy-panel.component.js'), 'utf8'), /staff\/copy-history/);
  assert.match(fs.readFileSync(path.join(root, 'features', 'staff', 'components', 'copy-panel', 'copy-panel.component.js'), 'utf8'), /value=\"Lost\"/);
  assert.match(fs.readFileSync(path.join(root, 'features', 'staff', 'components', 'copy-panel', 'copy-panel.component.js'), 'utf8'), /value=\"Damaged\"/);
});

test('print page is staff-protected and uses a dedicated browser PDF export controller', () => {
  assert.ok(fs.existsSync(printTemplate));
  assert.ok(fs.existsSync(printController));
  const template = fs.readFileSync(printTemplate, 'utf8');
  assert.match(template, /data-app-page="staff-barcode-print"/);
  assert.match(template, /JsBarcode/);
  assert.match(fs.readFileSync(printController, 'utf8'), /window\.print/);
  assert.equal(typeof CopyPanelComponent.prototype.exportUnprinted, 'function');
  assert.equal(typeof CopyPanelComponent.prototype.openPrintPage, 'function');
});

test('print page binds the default fetcher to its owning browser window', async () => {
  const originalDocument = globalThis.document;
  const originalFetch = globalThis.fetch;
  const elements = new Map([
    ['barcode-labels', { innerHTML: '' }],
    ['print-subtitle', { textContent: '' }],
    ['print-error', { textContent: '', classList: { remove() {} } }],
    ['print-pdf', { disabled: false, addEventListener() {} }],
    ['close-print', { addEventListener() {} }],
  ]);
  const fakeDocument = { getElementById: (id) => elements.get(id) };
  const token = 'a'.repeat(32);
  const fakeWindow = {
    location: { search: `?batch_token=${token}` },
    fetch(url) {
      assert.equal(this, fakeWindow);
      assert.equal(url, `/scan2borrow/api/barcode-print-batches?batch_token=${token}`);
      return Promise.resolve(new Response(JSON.stringify({
        ok: true,
        data: { title: 'Clean Code', created_at: '2026-08-30 10:00:00', labels: [] },
      }), { status: 200, headers: { 'Content-Type': 'application/json' } }));
    },
    print() {},
    close() {},
    closed: false,
    history: { back() {} },
  };

  globalThis.document = fakeDocument;
  globalThis.fetch = fakeWindow.fetch;
  try {
    const batch = await new BarcodePrintPage({ windowObject: fakeWindow }).load(token);

    assert.equal(batch.title, 'Clean Code');
  } finally {
    globalThis.document = originalDocument;
    globalThis.fetch = originalFetch;
  }
});
