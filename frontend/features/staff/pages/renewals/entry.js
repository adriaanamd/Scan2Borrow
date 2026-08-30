import { createPageContext } from '../../../../app/bootstrap/page-context.js';
import { StaffRenewalService } from '../../services/renewal.service.js';
import { StaffRenewalsPage } from './renewals.page.js';

export function boot(document = globalThis.document, window = globalThis.window) {
  const context = createPageContext({ document, window });
  return new StaffRenewalsPage(document, { service: new StaffRenewalService({ api: context.api }), window }).start();
}

if (typeof document !== 'undefined') boot();
