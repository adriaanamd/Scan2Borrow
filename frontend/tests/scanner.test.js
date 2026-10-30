import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { BarcodeScannerComponent } from '../app/shared/components/barcode-scanner/barcode-scanner.component.js';

test('BarcodeScannerComponent owns a removable click listener', () => {
  const listeners = {};
  const root = {
    addEventListener(name, listener) { listeners[name] = listener; },
    removeEventListener(name, listener) {
      if (listeners[name] === listener) delete listeners[name];
    },
  };
  const scanner = new BarcodeScannerComponent(root, { document: {}, window: {} });

  scanner.start();
  assert.equal(typeof listeners.click, 'function');
  scanner.destroy();
  assert.equal(listeners.click, undefined);
});

test('scanner library URL remains html5-qrcode 2.3.8', () => {
  assert.equal(
    BarcodeScannerComponent.libraryUrl,
    'https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js',
  );
});

test('scanner reports library failures through the toast service', async () => {
  const notifications = [];
  const button = {
    getAttribute: () => 'barcode-input',
    hasAttribute: () => false,
  };
  const input = {};
  const scanner = new BarcodeScannerComponent({ addEventListener() {} }, {
    document: { getElementById: () => input },
    window: {},
    toastService: {
      show(message, type) { notifications.push({ message, type }); },
    },
  });
  scanner.loadLibrary = () => Promise.reject(new Error('Failed to load scanner library'));

  await scanner.onClick({
    target: { closest: () => button },
    preventDefault() {},
  });

  assert.deepEqual(notifications, [
    { message: 'Failed to load scanner library', type: 'danger' },
  ]);
});

test('scanner callbacks receive decoded values after the camera stops', async () => {
  const views = new Map();
  const scannerIds = [];
  const decoded = [];
  let success;
  const document = {
    getElementById(id) { return views.get(id) || null; },
    createElement() { return { id: '', innerHTML: '', parentNode: null }; },
  };
  const makeRoot = (id) => ({ id, addEventListener() {}, removeEventListener() {} });
  const makeInput = () => ({
    value: '',
    form: null,
    parentNode: { parentNode: { appendChild(view) { views.set(view.id, view); } } },
    focus() {},
  });
  const makeButton = () => ({ disabled: false, textContent: '', hasAttribute: () => false });
  const makeScanner = () => ({
    start(_camera, _config, callback) { success = callback; return Promise.resolve(); },
    stop() { return Promise.resolve(); },
  });
  const first = new BarcodeScannerComponent(makeRoot('borrower-scanner'), {
    document,
    window: { Html5Qrcode: function Html5Qrcode() {} },
    scannerFactory(viewId) { scannerIds.push(viewId); return makeScanner(); },
    onDecoded(value, input) { decoded.push({ value, input }); },
  });
  const second = new BarcodeScannerComponent(makeRoot('book-scanner'), {
    document,
    window: { Html5Qrcode: function Html5Qrcode() {} },
    scannerFactory(viewId) { scannerIds.push(viewId); return makeScanner(); },
  });

  first.startScanner(makeInput(), false, makeButton());
  await success('  STU-001  ');
  await Promise.resolve();
  second.startScanner(makeInput(), false, makeButton());

  assert.deepEqual(scannerIds, ['borrower-scanner-view', 'book-scanner-view']);
  assert.equal(decoded[0].value, 'STU-001');
  assert.equal(decoded[0].input.value, 'STU-001');
});

test('legacy scanner contains no browser dialog calls', () => {
  const source = fs.readFileSync('frontend/assets/js/core/scanner.js', 'utf8');
  const browserDialogCall = new RegExp('\\b' + 'alert' + '\\s*\\(');
  assert.doesNotMatch(source, browserDialogCall);
});
