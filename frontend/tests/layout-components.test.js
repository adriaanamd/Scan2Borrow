import test from 'node:test';
import assert from 'node:assert/strict';
import { AppNavbarComponent } from '../app/shared/components/app-navbar/app-navbar.component.js';
import { AuthBrandComponent } from '../app/shared/components/auth-brand/auth-brand.component.js';
import { AppShellComponent } from '../app/core/layout/app-shell.component.js';

class ComponentFakeElement {
  constructor(document) {
    this.ownerDocument = document;
    this.children = [];
    this.parentNode = null;
    this.listeners = new Map();
    this.attributes = new Map();
    this.classes = new Set();
    this.classList = {
      add: (...values) => values.forEach((value) => this.classes.add(value)),
      remove: (...values) => values.forEach((value) => this.classes.delete(value)),
      contains: (value) => this.classes.has(value),
      toggle: (value, force) => {
        const next = force === undefined ? !this.classes.has(value) : force;
        if (next) this.classes.add(value);
        else this.classes.delete(value);
        return next;
      },
    };
    this.style = {};
    this.hidden = false;
    this.dataset = {};
  }

  setAttribute(name, value) {
    this.attributes.set(name, String(value));
  }

  getAttribute(name) {
    return this.attributes.get(name) ?? null;
  }

  appendChild(child) {
    child.parentNode = this;
    this.children.push(child);
    return child;
  }

  insertBefore(child) {
    child.parentNode = this;
    this.children.unshift(child);
    return child;
  }

  addEventListener(type, listener) {
    const listeners = this.listeners.get(type) || new Set();
    listeners.add(listener);
    this.listeners.set(type, listeners);
  }

  removeEventListener(type, listener) {
    this.listeners.get(type)?.delete(listener);
  }

  dispatchEvent(event) {
    if (!event.target) event.target = this;
    this.listeners.get(event.type)?.forEach((listener) => listener(event));
  }

  click() {
    this.dispatchEvent({ type: 'click', target: this });
  }

  closest(selector) {
    if (selector === '.app' && this.classes.has('app')) return this;
    return this.parentNode?.closest?.(selector) || null;
  }

  querySelector(selector) {
    if (selector === '.topbar') return this.children.find((child) => child.classes.has('topbar')) || null;
    if (selector === '.sidebar-toggle') return this.children.find((child) => child.classes.has('sidebar-toggle')) || null;
    if (selector === '.sidebar-backdrop') return this.children.find((child) => child.classes.has('sidebar-backdrop')) || null;
    if (selector === '[data-nav-path]') return this.navLink || null;
    return null;
  }

  querySelectorAll(selector) {
    if (selector === '[data-nav-path]') return this.navLink ? [this.navLink] : [];
    return [];
  }
}

function createComponentFixture() {
  const document = new ComponentFakeElement(null);
  document.ownerDocument = document;
  document.created = [];
  document.body = new ComponentFakeElement(document);
  document.createElement = () => {
    const element = new ComponentFakeElement(document);
    document.created.push(element);
    return element;
  };
  document.addEventListener = ComponentFakeElement.prototype.addEventListener;
  document.removeEventListener = ComponentFakeElement.prototype.removeEventListener;
  document.dispatchEvent = ComponentFakeElement.prototype.dispatchEvent;
  document.listeners = new Map();

  const app = new ComponentFakeElement(document);
  app.classes.add('app');
  const topbar = new ComponentFakeElement(document);
  topbar.classes.add('topbar');
  const root = new ComponentFakeElement(document);
  root.dataset.navbarRole = 'guest';
  const navLink = new ComponentFakeElement(document);
  navLink.dataset.navPath = '/scan2borrow/guest/dashboard';
  root.navLink = navLink;
  app.appendChild(root);
  app.appendChild(topbar);
  document.body.appendChild(app);

  return {
    document,
    window: { location: { pathname: '/scan2borrow/guest/dashboard' } },
    session: { load: async () => ({ role: 'guest' }) },
    root,
    get toggle() {
      return document.created.find((element) => element.classes.has('sidebar-toggle'));
    },
  };
}

test('AppNavbarComponent renders the guest navigation contract', async () => {
  const root = {
    dataset: { navbarRole: 'guest' },
    innerHTML: '',
    querySelectorAll: () => [],
  };
  const session = { load: async () => ({ role: 'guest' }) };
  const window = { location: { pathname: '/scan2borrow/guest/dashboard' } };
  const navbar = new AppNavbarComponent(root, { session, window });

  await navbar.start();

  assert.match(root.innerHTML, /My Dashboard/);
  assert.match(root.innerHTML, /\/scan2borrow\/guest\/browse/);
  assert.match(root.innerHTML, /Logout/);
  assert.equal(typeof navbar.destroy, 'function');
});

test('AuthBrandComponent renders the existing brand contract', () => {
  const root = { innerHTML: '' };
  new AuthBrandComponent(root).start();

  assert.match(root.innerHTML, /public\/logo\.png/);
  assert.match(root.innerHTML, /Binalbagan Catholic College seal/);
  assert.match(root.innerHTML, /School Library/);
});

test('AppShellComponent starts only the navbar inside its root', async () => {
  let started = false;
  const navRoot = {};
  const root = { querySelector: (selector) => selector === '[data-app-navbar]' ? navRoot : null };
  const navbar = { start: async () => { started = true; }, destroy() {} };
  const shell = new AppShellComponent(root, { navbar });

  await shell.start();
  assert.equal(started, true);
  assert.equal(typeof shell.destroy, 'function');
});

test('AppNavbarComponent exposes the responsive drawer interaction contract', async () => {
  const fixture = createComponentFixture();
  const navbar = new AppNavbarComponent(fixture.root, {
    session: fixture.session,
    window: fixture.window,
    document: fixture.document,
  });

  await navbar.start();
  assert.equal(fixture.toggle.getAttribute('aria-expanded'), 'false');

  fixture.toggle.click();

  assert.equal(fixture.root.classList.contains('is-open'), true);
  assert.equal(fixture.toggle.getAttribute('aria-expanded'), 'true');
  assert.equal(fixture.document.body.classList.contains('nav-drawer-open'), true);

  navbar.destroy();
  assert.equal(fixture.document.listeners.get('keydown')?.size || 0, 0);
});
