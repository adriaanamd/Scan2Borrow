import { createPageContext } from '../../../../app/bootstrap/page-context.js';
import { AuthService } from '../../services/auth.service.js';
import { ProfileOtpPage } from './profile-otp.page.js';

export function boot(document = globalThis.document, window = globalThis.window) {
  const context = createPageContext({ document, window });
  const auth = new AuthService({ api: context.api, window });
  return new ProfileOtpPage(document, { auth, document, window }).start();
}

if (typeof document !== 'undefined') boot();
