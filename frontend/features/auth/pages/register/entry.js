import { createPageContext } from '../../../../app/bootstrap/page-context.js';
import { AuthService } from '../../services/auth.service.js';
import { RegistrationPage } from './register.page.js';

export function boot(document = globalThis.document, window = globalThis.window) {
  const context = createPageContext({ document, window });
  return new RegistrationPage(document, { auth: new AuthService({ api: context.api, window }), document, window }).start();
}

if (typeof document !== 'undefined') boot();
