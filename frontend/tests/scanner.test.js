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

test('scanner announces barcode scanning after the camera starts', async () => {
  const views = new Map();
  const document = {
    getElementById(id) { return views.get(id) || null; },
    createElement() { return { id: '', innerHTML: '', parentNode: null }; },
  };
  const input = {
    value: '',
    form: null,
    parentNode: { parentNode: { appendChild(view) { views.set(view.id, view); } } },
    focus() {},
  };
  const button = { disabled: false, textContent: 'Scan barcode', hasAttribute: () => false };
  const scanner = new BarcodeScannerComponent({ id: 'barcode-scanner', addEventListener() {}, removeEventListener() {} }, {
    document,
    window: { Html5Qrcode: function Html5Qrcode() {} },
    scannerFactory: () => ({
      start() { return Promise.resolve(); },
      stop() { return Promise.resolve(); },
    }),
  });

  scanner.startScanner(input, false, button);
  await Promise.resolve();

  assert.equal(button.textContent, 'Scanning for barcode...');
  assert.equal(button.disabled, true);
});

function scannerNode() {
  return {
    children: [],
    listeners: {},
    classList: { add() {}, remove() {} },
    addEventListener(name, listener) { this.listeners[name] = listener; },
    removeEventListener(name, listener) {
      if (this.listeners[name] === listener) delete this.listeners[name];
    },
    setAttribute(name, value) { this[name] = value; },
    append(...children) { this.children.push(...children); },
    click() { this.clicked = true; },
  };
}

function makeNativeScannerFixture({ scanner, navigator = {}, fileInput = null, toastService = null } = {}) {
  const root = scannerNode();
  root.id = 'native-scanner';
  const input = {
    value: '',
    form: null,
    parentNode: { parentNode: { appendChild() {} } },
    focus() {},
  };
  const nativeInput = fileInput || scannerNode();
  const nativeButton = scannerNode();
  const view = scannerNode();
  const document = {
    head: { appendChild() {} },
    getElementById(id) {
      if (id === 'barcode-input') return input;
      if (id === 'native-scanner-view') return view;
      return null;
    },
    createElement(tag) {
      if (tag === 'input') return nativeInput;
      if (tag === 'button') return nativeButton;
      return view;
    },
  };
  const createdScanner = scanner || {
    start() { return Promise.resolve(); },
    stop() { return Promise.resolve(); },
    scanFile() { return Promise.resolve(' B-7 '); },
  };
  const component = new BarcodeScannerComponent(root, {
    document,
    window: { Html5Qrcode: function Html5Qrcode() {} },
    navigator,
    scannerFactory: () => createdScanner,
    toastService: toastService || { show() {} },
  });
  const trigger = {
    disabled: false,
    textContent: 'Scan barcode',
    getAttribute: () => 'barcode-input',
    hasAttribute: () => false,
  };
  return { component, input, nativeInput, nativeButton, trigger };
}

test('scanner uses a native rear-camera input when live camera is unavailable', async () => {
  const fixture = makeNativeScannerFixture({ navigator: {} });

  await fixture.component.onClick({ target: { closest: () => fixture.trigger }, preventDefault() {} });

  assert.equal(fixture.nativeInput.accept, 'image/*');
  assert.equal(fixture.nativeInput.capture, 'environment');
  assert.equal(fixture.nativeInput.clicked, true);
});

test('scanner sends a native image through scanFile and the existing callback path', async () => {
  const decoded = [];
  const fileInput = scannerNode();
  fileInput.files = [{ name: 'barcode.jpg' }];
  fileInput.value = 'selected';
  const fixture = makeNativeScannerFixture({
    navigator: {},
    fileInput,
  });
  fixture.component.onDecoded = (value) => decoded.push(value);
  await fixture.component.onClick({ target: { closest: () => fixture.trigger }, preventDefault() {} });

  await fixture.component.handleNativeFile({ target: fileInput });

  assert.equal(fixture.input.value, 'B-7');
  assert.deepEqual(decoded, ['B-7']);
  assert.equal(fileInput.value, '');
});

test('scanner keeps the native retry available when image decoding fails', async () => {
  const notifications = [];
  const fileInput = scannerNode();
  fileInput.files = [{ name: 'blurred.jpg' }];
  fileInput.value = 'selected';
  const fixture = makeNativeScannerFixture({
    navigator: {},
    fileInput,
    scanner: {
      scanFile: () => Promise.reject(new Error('No barcode found')),
      stop() { return Promise.resolve(); },
    },
    toastService: { show(message, type) { notifications.push({ message, type }); } },
  });
  await fixture.component.onClick({ target: { closest: () => fixture.trigger }, preventDefault() {} });

  await fixture.component.handleNativeFile({ target: fileInput });

  assert.deepEqual(notifications, [{ message: 'No barcode found', type: 'danger' }]);
  assert.equal(fileInput.value, '');
  assert.equal(fixture.nativeButton.hidden, false);
});

test('legacy scanner contains no browser dialog calls', () => {
  const source = fs.readFileSync('frontend/assets/js/core/scanner.js', 'utf8');
  const browserDialogCall = new RegExp('\\b' + 'alert' + '\\s*\\(');
  assert.doesNotMatch(source, browserDialogCall);
});
