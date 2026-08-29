import { ApiClient } from '../core/api/api-client.js';
import { SessionGuard } from '../core/auth/session.guard.js';
import { SessionService } from '../core/auth/session.service.js';

export function createPageContext({
  document = globalThis.document,
  window = globalThis.window,
  fetchImpl,
} = {}) {
  const csrf = document.querySelector('meta[name="csrf"]')?.content || '';
  const browserFetch = fetchImpl || window?.fetch?.bind(window) || globalThis.fetch?.bind(globalThis);
  const api = new ApiClient({ fetchImpl: browserFetch, csrf });
  const session = new SessionService({ document });
  const guard = new SessionGuard({ session, window });

  return { api, session, guard, document, window };
}
