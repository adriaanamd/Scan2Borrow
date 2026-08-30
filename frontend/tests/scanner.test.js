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

test('legacy scanner contains no browser dialog calls', () => {
  const source = fs.readFileSync('frontend/assets/js/core/scanner.js', 'utf8');
  const browserDialogCall = new RegExp('\\b' + 'alert' + '\\s*\\(');
  assert.doesNotMatch(source, browserDialogCall);
});
