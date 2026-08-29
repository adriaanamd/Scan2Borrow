import test from 'node:test';
import assert from 'node:assert/strict';
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
