import test from 'node:test';
import assert from 'node:assert/strict';
import { createPageContext } from '../app/bootstrap/page-context.js';
import { ApiClient } from '../app/core/api/api-client.js';
import { SessionService } from '../app/core/auth/session.service.js';
import { SessionGuard } from '../app/core/auth/session.guard.js';
import { ModalService } from '../app/core/services/modal.service.js';
import { NotificationService } from '../app/core/services/notification.service.js';
import { ToastService } from '../app/core/services/toast.service.js';

test('page context shares API, session, and guard dependencies', () => {
  const document = {
    querySelector: () => ({ content: 'csrf-token' }),
  };
  const window = { location: { pathname: '/scan2borrow/student/dashboard' } };
  const context = createPageContext({ document, window, fetchImpl: async () => {} });

  assert.equal(context.document, document);
  assert.equal(context.window, window);
  assert.equal(context.api instanceof ApiClient, true);
  assert.equal(context.session instanceof SessionService, true);
  assert.equal(context.guard instanceof SessionGuard, true);
  assert.equal(context.session.csrf(), 'csrf-token');
});

test('NotificationService polls with the existing five-second interval', async () => {
  const calls = [];
  const api = { get: async (path) => { calls.push(path); return { ok: true }; } };
  const timers = [];
  const service = new NotificationService({
    api,
    setIntervalImpl: (callback, interval) => { timers.push({ callback, interval }); return 3; },
    clearIntervalImpl: (id) => calls.push('clear:' + id),
  });

  service.start(() => {}, 5000);
  assert.equal(timers[0].interval, 5000);
  await timers[0].callback();
  service.stop();
  assert.deepEqual(calls, ['/scan2borrow/api/staff/notifications', 'clear:3']);
});

test('ModalService and ToastService expose stable UI service methods', () => {
  const events = [];
  const window = {
    bootstrap: {
      Modal: {
        getOrCreateInstance(element) {
          return {
            show: () => events.push('show:' + element.id),
            hide: () => events.push('hide:' + element.id),
          };
        },
      },
    },
  };
  const document = {
    getElementById(id) { return { id }; },
  };
  const modal = new ModalService({ document, window });
  modal.show('borrowModal');
  modal.hide('borrowModal');
  assert.deepEqual(events, ['show:borrowModal', 'hide:borrowModal']);

  const toast = new ToastService({ document: { createElement: () => ({}) } });
  assert.equal(typeof toast.show, 'function');
  assert.equal(typeof toast.hideAll, 'function');
});
