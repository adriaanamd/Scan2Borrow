import { createPageContext } from '../../../../app/bootstrap/page-context.js';
import { CameraCaptureComponent } from '../../../../app/shared/components/camera-capture/camera-capture.component.js';
import { AuthService } from '../../services/auth.service.js';
import { RegistrationPage } from './register.page.js';

export function boot(document = globalThis.document, window = globalThis.window) {
  const context = createPageContext({ document, window });
  const camera = new CameraCaptureComponent(document, { document, window, navigator: window.navigator });
  return new RegistrationPage(document, { api: context.api, auth: new AuthService({ api: context.api, window }), camera, document, window }).start();
}

if (typeof document !== 'undefined') boot();
