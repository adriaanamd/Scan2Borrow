import { createPageContext } from '../../../../app/bootstrap/page-context.js';
import { AuthService } from '../../services/auth.service.js';
import { OtpPage } from './otp.page.js';

export function boot(document = globalThis.document, window = globalThis.window) {
  const context = createPageContext({ document, window });
  return new OtpPage(document, { auth: new AuthService({ api: context.api, window }), document, window }).start();
}

if (typeof document !== 'undefined') boot();
