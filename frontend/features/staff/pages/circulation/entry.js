import { createPageContext } from '../../../../app/bootstrap/page-context.js';
import { StaffCirculationService } from '../../services/staff-circulation.service.js';
import { CirculationPage } from './circulation.page.js';

export function boot(document = globalThis.document, window = globalThis.window) {
  const context = createPageContext({ document, window });
  const service = new StaffCirculationService({ api: context.api });
  return new CirculationPage(document, { service, document, window }).start();
}

if (typeof document !== 'undefined') boot();
