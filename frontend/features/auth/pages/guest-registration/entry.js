import { createPageContext } from '../../../../app/bootstrap/page-context.js';
import { CameraCaptureComponent } from '../../../../app/shared/components/camera-capture/camera-capture.component.js';
import { AuthService } from '../../services/auth.service.js';
import { GuestRegistrationPage } from './guest-registration.page.js';

export function boot(document = globalThis.document, window = globalThis.window) {
  const context = createPageContext({ document, window });
  const auth = new AuthService({ api: context.api, window });
  const camera = new CameraCaptureComponent(document);
  return new GuestRegistrationPage(document, { auth, camera, document, window }).start();
}

if (typeof document !== 'undefined') boot();
