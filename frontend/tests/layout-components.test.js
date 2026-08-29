import test from 'node:test';
import assert from 'node:assert/strict';
import { AppNavbarComponent } from '../app/shared/components/app-navbar/app-navbar.component.js';

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
