import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { CopyPanelComponent } from '../features/staff/components/copy-panel/copy-panel.component.js';

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
