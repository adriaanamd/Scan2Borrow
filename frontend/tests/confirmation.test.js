import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const testsDirectory = path.dirname(fileURLToPath(import.meta.url));
const servicePath = path.resolve(testsDirectory, '..', 'assets', 'js', 'core', 'confirmation.js');

class FakeElement {
  constructor(tagName) {
    this.tagName = tagName.toUpperCase();
    this.children = [];
    this.attributes = {};
    this.listeners = new Map();
    this.className = '';
    this.classList = {
      add: (...names) => names.forEach((name) => {
        if (!this.className.split(' ').includes(name)) this.className += (this.className ? ' ' : '') + name;
      }),
      remove: (...names) => {
        this.className = this.className.split(' ').filter((name) => !names.includes(name)).join(' ');
      },
      contains: (name) => this.className.split(' ').includes(name),
    };
    this.textContent = '';
    this.disabled = false;
    this.hidden = false;
    this.type = '';
  }

  appendChild(child) { this.children.push(child); child.parentNode = this; return child; }
  setAttribute(name, value) { this.attributes[name] = String(value); if (name === 'id') this.id = String(value); }
  getAttribute(name) { return this.attributes[name] ?? null; }
  addEventListener(type, listener) {
    const listeners = this.listeners.get(type) || [];
    listeners.push(listener);
    this.listeners.set(type, listeners);
  }
  dispatchEvent(event) {
    for (const listener of this.listeners.get(event.type) || []) {
      listener({ ...event, currentTarget: this, target: this });
    }
  }
  click() { this.dispatchEvent({ type: 'click', preventDefault() {}, stopPropagation() {} }); }
  matches(selector) { return selector === '.nav-logout' ? this.classList.contains('nav-logout') : false; }
  querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
  querySelectorAll(selector) {
    const matches = (element) => selector === '[data-confirm-title]' ? element.getAttribute('data-confirm-title') !== null
      : selector === '[data-confirm-message-target]' ? element.getAttribute('data-confirm-message-target') !== null
      : selector === '[data-confirm-cancel]' ? element.getAttribute('data-confirm-cancel') !== null
      : selector === '[data-confirm-confirm]' ? element.getAttribute('data-confirm-confirm') !== null
      : selector === '#scan2borrow-confirmation-modal' ? element.id === 'scan2borrow-confirmation-modal'
      : false;
    const result = [];
    const visit = (element) => {
      if (matches(element)) result.push(element);
      element.children.forEach(visit);
    };
    this.children.forEach(visit);
    return result;
  }
}

class FakeDocument extends FakeElement {
  constructor() {
    super('document');
    this.body = new FakeElement('body');
  }

  createElement(tagName) { return new FakeElement(tagName); }
}

function loadService({ bootstrap = {}, nativeResult = true } = {}) {
  const document = new FakeDocument();
  const window = {
    bootstrap,
    confirmCalls: [],
    confirm(message) { this.confirmCalls.push(message); return nativeResult; },
  };
  window.document = document;
  const context = vm.createContext({ document, window, globalThis: window, console });
  vm.runInContext(fs.readFileSync(servicePath, 'utf8'), context, { filename: servicePath });
  return { service: window.Scan2BorrowConfirmation, document, window };
}

function createConfirmationFixture(options = {}) {
  const modalState = { shown: false, hidden: false };
  const bootstrap = options.bootstrap === null ? null : {
    Modal: {
      getOrCreateInstance() {
        return {
          show() { modalState.shown = true; },
          hide() { modalState.hidden = true; },
        };
      },
    },
  };
  const fixture = loadService({ ...options, bootstrap });
  const getModalElement = () => fixture.document.body.querySelector('#scan2borrow-confirmation-modal');
  const modal = {
    get title() { return getModalElement()?.querySelector('[data-confirm-title]'); },
    get message() { return getModalElement()?.querySelector('[data-confirm-message-target]'); },
    get confirm() { return getModalElement()?.querySelector('[data-confirm-confirm]'); },
    cancel() { getModalElement()?.querySelector('[data-confirm-cancel]')?.click(); },
    state: modalState,
  };
  return { ...fixture, modal };
}

test('cancel resolves false and never calls the continuation', async () => {
  const { service, modal } = createConfirmationFixture();
  let called = false;
  const result = service.confirm({
    title: 'Delete book',
    message: 'This cannot be undone.',
    onConfirm: () => { called = true; },
  });
  modal.cancel();
  assert.equal(await result, false);
  assert.equal(called, false);
});

test('confirm renders context and executes once', async () => {
  const { service, modal } = createConfirmationFixture();
  let calls = 0;
  const result = service.confirm({
    title: 'Archive Clean Code',
    message: 'The book leaves the active catalog.',
    confirmLabel: 'Archive',
    confirmClass: 'btn-warning',
    onConfirm: () => { calls += 1; },
  });
  assert.equal(modal.title.textContent, 'Archive Clean Code');
  assert.equal(modal.message.textContent, 'The book leaves the active catalog.');
  assert.equal(modal.confirm.textContent, 'Archive');
  modal.confirm.click();
  modal.confirm.click();
  assert.equal(await result, true);
  assert.equal(calls, 1);
});

test('Bootstrap absence falls back to native confirmation', async () => {
  const { service, window } = createConfirmationFixture({
    bootstrap: null,
    nativeResult: false,
  });
  let called = false;
  assert.equal(await service.confirm({
    message: 'Leave the session?',
    onConfirm: () => { called = true; },
  }), false);
  assert.deepEqual(window.confirmCalls, ['Leave the session?']);
  assert.equal(called, false);
});

function htmlFiles(rootDirectory) {
  return fs.readdirSync(rootDirectory, { withFileTypes: true }).flatMap((entry) => {
    const entryPath = path.join(rootDirectory, entry.name);
    return entry.isDirectory() ? htmlFiles(entryPath) : entry.name.endsWith('.html') ? [entryPath] : [];
  });
}

test('session templates load confirmation before the navbar', () => {
  const templateRoot = path.resolve(testsDirectory, '..');
  const templates = htmlFiles(path.join(templateRoot, 'features'));

  for (const template of templates) {
    const source = fs.readFileSync(template, 'utf8');
    const navbarIndex = source.indexOf('core/app-navbar.js');
    if (navbarIndex < 0) continue;
    const confirmationIndex = source.indexOf('core/confirmation.js');
    assert.ok(confirmationIndex >= 0, path.relative(templateRoot, template));
    assert.ok(confirmationIndex < navbarIndex, path.relative(templateRoot, template));
  }
});

test('form guards use the clicked submitter action metadata', async () => {
  const { service, modal } = createConfirmationFixture();
  let prevented = false;
  let submitted = 0;
  const form = {
    dataset: { confirmAction: 'request' },
    querySelector: () => ({ value: 'A reason' }),
    requestSubmit: () => { submitted += 1; },
  };
  const submitter = {
    dataset: {
      confirmAction: 'reject',
      confirmTitle: 'Reject guest request',
      confirmMessage: 'Reject this guest borrow request?',
      confirmLabel: 'Reject',
      confirmClass: 'btn-danger',
    },
    focus() {},
  };
  service.guardForm({
    target: { closest: () => form },
    submitter,
    preventDefault() { prevented = true; },
  });
  await Promise.resolve();
  assert.equal(prevented, true);
  assert.equal(modal.title.textContent, 'Reject guest request');
  assert.equal(modal.confirm.textContent, 'Reject');
  modal.confirm.click();
  await Promise.resolve();
  assert.equal(submitted, 1);
});
