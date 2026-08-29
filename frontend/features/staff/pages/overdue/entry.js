import { createPageContext } from '../../../../app/bootstrap/page-context.js';
import { StaffOverdueService } from '../../services/overdue.service.js';
import { OverduePage } from './overdue.page.js';

export function boot(document = globalThis.document, window = globalThis.window) {
  const context = createPageContext({ document, window });
  return new OverduePage(document, { service: new StaffOverdueService({ api: context.api }) }).start();
}

if (typeof document !== 'undefined') boot();
