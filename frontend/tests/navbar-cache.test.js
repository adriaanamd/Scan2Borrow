import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const testDirectory = path.dirname(fileURLToPath(import.meta.url));
const navbarPath = path.resolve(testDirectory, '..', 'assets', 'js', 'core', 'app-navbar.js');
const appNavbarPath = path.resolve(testDirectory, '..', 'app', 'shared', 'components', 'app-navbar', 'app-navbar.component.js');

function loadNavbar(window, document = { addEventListener() {} }) {
  const context = vm.createContext({ document, window, fetch: window.fetch });
  const source = `${fs.readFileSync(navbarPath, 'utf8')}\nwindow.AppNavbar = AppNavbar;`;
  vm.runInContext(source, context, { filename: navbarPath });
  return window.AppNavbar;
}

class FakeClassList {
  constructor() {
    this.values = new Set();
  }

  add(...values) {
    values.forEach((value) => this.values.add(value));
  }

  remove(...values) {
    values.forEach((value) => this.values.delete(value));
  }

  contains(value) {
    return this.values.has(value);
  }

  toggle(value, force) {
    const next = force === undefined ? !this.values.has(value) : force;
    if (next) this.values.add(value);
    else this.values.delete(value);
    return next;
  }
}

class FakeElement {
  constructor(document, tagName = 'div') {
    this.ownerDocument = document;
    this.tagName = tagName.toUpperCase();
    this.children = [];
    this.parentNode = null;
    this.listeners = new Map();
    this.attributes = new Map();
    this.classList = new FakeClassList();
    this.dataset = {};
    this.style = {};
    this.hidden = false;
    this.innerHTML = '';
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

  insertBefore(child, before) {
    child.parentNode = this;
    const index = before ? this.children.indexOf(before) : -1;
    if (index < 0) this.children.push(child);
    else this.children.splice(index, 0, child);
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
    this.parentNode?.dispatchEvent?.(event);
  }

  click() {
    this.dispatchEvent({ type: 'click', target: this });
  }

  listenerCount(type) {
    return this.listeners.get(type)?.size || 0;
  }

  closest(selector) {
    if (selector === '[data-nav-path]' && this.dataset.navPath) return this;
    if (selector === '.app' && this.classList.contains('app')) return this;
    return this.parentNode?.closest?.(selector) || null;
  }

  querySelector(selector) {
    if (selector === '.topbar') return this.children.find((child) => child.classList.contains('topbar')) || null;
    if (selector === '.sidebar-toggle') return this.children.find((child) => child.classList.contains('sidebar-toggle')) || null;
    if (selector === '.sidebar-backdrop') return this.children.find((child) => child.classList.contains('sidebar-backdrop')) || null;
    if (selector === '[data-nav-path]') return this.navLink || null;
    return null;
  }

  querySelectorAll(selector) {
    if (selector === '[data-nav-path]') return this.navLink ? [this.navLink] : [];
    return [];
  }
}

class FakeDocument extends FakeElement {
  constructor() {
    super(null, '#document');
    this.ownerDocument = this;
    this.createdElements = [];
    this.body = new FakeElement(this, 'body');
    this.appendChild(this.body);
  }

  createElement(tagName) {
    const element = new FakeElement(this, tagName);
    this.createdElements.push(element);
    return element;
  }

  querySelector(selector) {
    return this.body.querySelector(selector);
  }
}

function createNavbarFixture(pathname) {
  const document = new FakeDocument();
  const app = new FakeElement(document, 'div');
  app.classList.add('app');
  const topbar = new FakeElement(document, 'header');
  topbar.classList.add('topbar');
  const root = new FakeElement(document, 'aside');
  root.dataset.navbarRole = 'student';
  const navLink = new FakeElement(document, 'a');
  navLink.dataset.navPath = '/scan2borrow/student/dashboard';
  root.navLink = navLink;
  root.appendChild(navLink);
  app.appendChild(root);
  app.appendChild(topbar);
  document.body.appendChild(app);

  return {
    document,
    window: {
      location: { pathname },
      sessionStorage: { getItem: () => 'student', setItem() {} },
      fetch: async () => ({ json: async () => ({ ok: true, data: { role: 'student' } }) }),
    },
    root,
    navLink,
    get toggle() {
      return document.createdElements.find((element) => element.classList.contains('sidebar-toggle'));
    },
    get backdrop() {
      return document.createdElements.find((element) => element.classList.contains('sidebar-backdrop'));
    },
  };
}

test('navbar uses the cached role without refetching or rebuilding the sidebar', async () => {
  const values = new Map([['scan2borrow.nav.role', 'student']]);
  const storage = {
    getItem: (key) => values.get(key) || null,
    setItem: (key, value) => values.set(key, value),
  };
  let fetchCalls = 0;
  let renderCalls = 0;
  const root = {
    dataset: { navbarRole: 'session' },
    querySelectorAll: () => [],
    replaceChildren() {},
  };
  Object.defineProperty(root, 'innerHTML', {
    set() { renderCalls += 1; },
  });
  const window = {
    location: { pathname: '/scan2borrow/student/dashboard' },
    sessionStorage: storage,
    async fetch(url) {
      fetchCalls += 1;
      assert.equal(this, window);
      assert.equal(url, '/scan2borrow/api/auth/session');
      return { json: async () => ({ ok: true, data: { role: 'student' } }) };
    },
  };
  const AppNavbar = loadNavbar(window);
  const navbar = new AppNavbar(root);

  await navbar.start();

  assert.equal(fetchCalls, 0);
  assert.equal(renderCalls, 1);
  assert.equal(values.get('scan2borrow.nav.role'), 'student');
});

test('navbar binds the first session fetch to the window', async () => {
  let fetchCalls = 0;
  const root = {
    dataset: { navbarRole: 'session' },
    querySelectorAll: () => [],
    replaceChildren() {},
  };
  Object.defineProperty(root, 'innerHTML', { set() {} });
  const window = {
    location: { pathname: '/scan2borrow/student/dashboard' },
    sessionStorage: { getItem: () => null, setItem() {} },
    async fetch(url) {
      fetchCalls += 1;
      assert.equal(this, window);
      assert.equal(url, '/scan2borrow/api/auth/session');
      return { json: async () => ({ ok: true, data: { role: 'student' } }) };
    },
  };
  const AppNavbar = loadNavbar(window);

  await new AppNavbar(root).start();

  assert.equal(fetchCalls, 1);
});

test('navbar implementations identify logout as a confirmed action', () => {
  for (const sourcePath of [navbarPath, appNavbarPath]) {
    const source = fs.readFileSync(sourcePath, 'utf8');
    assert.match(source, /data-confirm-action=.*logout|data-confirm-action.*logout/, sourcePath);
  }
});

test('navbar ignores a stale student cache on staff routes', async () => {
  const values = new Map([['scan2borrow.nav.role', 'student']]);
  let rendered = '';
  let fetchCalls = 0;
  const root = {
    dataset: { navbarRole: 'session' },
    querySelectorAll: () => [],
    replaceChildren() {},
  };
  Object.defineProperty(root, 'innerHTML', {
    set(value) { rendered = value; },
  });
  const window = {
    location: { pathname: '/scan2borrow/staff/dashboard' },
    sessionStorage: {
      getItem: (key) => values.get(key) || null,
      setItem: (key, value) => values.set(key, value),
    },
    async fetch(url) {
      fetchCalls += 1;
      assert.equal(url, '/scan2borrow/api/auth/session');
      return { json: async () => ({ ok: true, data: { role: 'admin' } }) };
    },
  };
  const AppNavbar = loadNavbar(window);

  const navbar = new AppNavbar(root);
  await navbar.start();

  assert.equal(fetchCalls, 1);
  assert.equal(navbar.renderedRole, 'admin');
  assert.equal(values.get('scan2borrow.nav.role'), 'admin');
  assert.match(rendered, /API Docs/);
  assert.doesNotMatch(rendered, /Search Books/);
});

test('navbar gives teachers role-specific Borrow and History destinations', () => {
  let rendered = '';
  const root = {
    dataset: { navbarRole: 'teacher' },
    querySelectorAll: () => [],
    replaceChildren() {},
  };
  Object.defineProperty(root, 'innerHTML', {
    set(value) { rendered = value; },
  });
  const window = {
    location: { pathname: '/scan2borrow/teacher/dashboard' },
    sessionStorage: { getItem: () => 'teacher', setItem() {} },
    fetch: async () => ({ json: async () => ({ ok: true, data: { role: 'teacher' } }) }),
  };
  const AppNavbar = loadNavbar(window);

  new AppNavbar(root).render('teacher');

  assert.match(rendered, /href="\/scan2borrow\/teacher\/borrow"/);
  assert.match(rendered, /href="\/scan2borrow\/teacher\/history"/);
  assert.doesNotMatch(rendered, /href="\/scan2borrow\/student\/search"/);
  assert.doesNotMatch(rendered, /href="\/scan2borrow\/student\/history"/);
});

test('classic navbar creates an accessible mobile control and opens the drawer', async () => {
  const fixture = createNavbarFixture('/scan2borrow/student/dashboard');
  const AppNavbar = loadNavbar(fixture.window, fixture.document);
  const navbar = new AppNavbar(fixture.root);

  await navbar.start();
  assert.equal(fixture.toggle.getAttribute('aria-controls'), 'app-sidebar');
  assert.equal(fixture.toggle.getAttribute('aria-expanded'), 'false');

  fixture.toggle.click();

  assert.equal(fixture.root.classList.contains('is-open'), true);
  assert.equal(fixture.backdrop.hidden, false);
  assert.equal(fixture.toggle.getAttribute('aria-expanded'), 'true');
  assert.equal(fixture.document.body.classList.contains('nav-drawer-open'), true);
  assert.equal(fixture.document.body.style.overflow, 'hidden');
});

test('classic navbar closes the drawer from Escape, backdrop, and navigation links', async () => {
  const fixture = createNavbarFixture('/scan2borrow/student/dashboard');
  const AppNavbar = loadNavbar(fixture.window, fixture.document);
  const navbar = new AppNavbar(fixture.root);

  await navbar.start();
  fixture.toggle.click();
  fixture.document.dispatchEvent({ type: 'keydown', key: 'Escape' });
  assert.equal(fixture.root.classList.contains('is-open'), false);

  fixture.toggle.click();
  fixture.backdrop.click();
  assert.equal(fixture.root.classList.contains('is-open'), false);

  fixture.toggle.click();
  fixture.navLink.click();
  assert.equal(fixture.root.classList.contains('is-open'), false);
});

test('classic navbar destroy removes drawer listeners and restores body overflow', async () => {
  const fixture = createNavbarFixture('/scan2borrow/student/dashboard');
  const AppNavbar = loadNavbar(fixture.window, fixture.document);
  const navbar = new AppNavbar(fixture.root);

  await navbar.start();
  fixture.toggle.click();
  navbar.destroy();

  assert.equal(fixture.document.body.classList.contains('nav-drawer-open'), false);
  assert.equal(fixture.document.body.style.overflow, '');
  assert.equal(fixture.document.listenerCount('keydown'), 0);
});
