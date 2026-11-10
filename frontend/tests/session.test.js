import test from 'node:test';
import assert from 'node:assert/strict';
import { SessionService } from '../app/core/auth/session.service.js';
import { SessionGuard } from '../app/core/auth/session.guard.js';

test('SessionService loads and caches session identity', async () => {
  const document = {
    querySelector(selector) {
      return selector === 'meta[name="csrf"]' ? { content: 'csrf-token' } : null;
    },
  };
  const api = { get: async () => ({ ok: true, data: { role: 'student', name: 'Ada', access_profile: null } }) };
  const session = new SessionService({ api, document });

  assert.deepEqual(await session.load(), { role: 'student', name: 'Ada', access_profile: null });
  assert.deepEqual(session.current(), { role: 'student', name: 'Ada', access_profile: null });
  assert.equal(session.csrf(), 'csrf-token');
});

test('SessionService preserves the library aide access profile', async () => {
  const api = { get: async () => ({ ok: true, data: { role: 'admin', access_profile: 'library_aide' } }) };
  const session = new SessionService({ api, document: { querySelector: () => null } });

  assert.deepEqual(await session.load(), { role: 'admin', access_profile: 'library_aide' });
  assert.equal(session.current().access_profile, 'library_aide');
});

test('SessionGuard redirects expired protected sessions', async () => {
  let cleared = false;
  const session = {
    async load() {
      const error = new Error('Expired');
      error.status = 401;
      throw error;
    },
    clear() {
      cleared = true;
    },
  };
  const window = { location: { pathname: '/scan2borrow/student/dashboard', href: '' } };

  assert.equal(await new SessionGuard({ session, window }).boot(), null);
  assert.equal(cleared, true);
  assert.equal(window.location.href, '/scan2borrow/login');
});
