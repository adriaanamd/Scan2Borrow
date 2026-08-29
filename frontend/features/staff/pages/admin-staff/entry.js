import { createPageContext } from '../../../../app/bootstrap/page-context.js';
import { AdminStaffService } from '../../services/admin-staff.service.js';
import { AdminStaffPage } from './admin-staff.page.js';

export function boot(document = globalThis.document, window = globalThis.window) {
  const context = createPageContext({ document, window });
  return new AdminStaffPage(document, { service: new AdminStaffService({ api: context.api }), window }).start();
}

if (typeof document !== 'undefined') boot();
