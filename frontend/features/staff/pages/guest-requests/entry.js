import { createPageContext } from '../../../../app/bootstrap/page-context.js';
import { GuestRequestsPage } from './guest-requests.page.js';
import { StaffGuestRequestService } from '../../services/guest-request.service.js';

export function boot(document = globalThis.document, window = globalThis.window) {
  const context = createPageContext({ document, window });
  return new GuestRequestsPage(document, { service: new StaffGuestRequestService({ api: context.api }) }).start();
}

if (typeof document !== 'undefined') boot();
