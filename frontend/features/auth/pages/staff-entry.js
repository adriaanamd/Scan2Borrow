import { createPageContext } from '../../../app/bootstrap/page-context.js';
import { AuthService } from '../services/auth.service.js';
import { LoginPage } from './login/login.page.js';

export function boot(document = globalThis.document, window = globalThis.window) {
  const context = createPageContext({ document, window });
  return new LoginPage(document, { auth: new AuthService({ api: context.api, window }), window }).start();
}

if (typeof document !== 'undefined') boot();
