import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const testDirectory = path.dirname(fileURLToPath(import.meta.url));
const navbarPath = path.resolve(testDirectory, '..', 'assets', 'js', 'core', 'app-navbar.js');

function loadNavbar(window) {
  const document = { addEventListener() {} };
  const context = vm.createContext({ document, window, fetch: window.fetch });
  const source = `${fs.readFileSync(navbarPath, 'utf8')}\nwindow.AppNavbar = AppNavbar;`;
  vm.runInContext(source, context, { filename: navbarPath });
  return window.AppNavbar;
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
