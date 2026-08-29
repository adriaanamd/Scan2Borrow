import { createPageContext } from '../../../../app/bootstrap/page-context.js';
import { BorrowerService } from '../../services/borrower.service.js';
import { StaffNotificationService } from '../../services/notification.service.js';
import { StaffNotifyPage } from './notify.page.js';

export function boot(document = globalThis.document, window = globalThis.window) {
  const context = createPageContext({ document, window });
  return new StaffNotifyPage(document, { borrowerService: new BorrowerService({ api: context.api }), notificationService: new StaffNotificationService({ api: context.api, window }), window }).start();
}

if (typeof document !== 'undefined') boot();
