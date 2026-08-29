import test from 'node:test';
import assert from 'node:assert/strict';
import { AppNavbarComponent } from '../app/shared/components/app-navbar/app-navbar.component.js';
import { AuthBrandComponent } from '../app/shared/components/auth-brand/auth-brand.component.js';
import { AppShellComponent } from '../app/core/layout/app-shell.component.js';

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
