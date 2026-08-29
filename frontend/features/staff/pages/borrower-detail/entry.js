import { createPageContext } from '../../../../app/bootstrap/page-context.js';
import { BorrowerService } from '../../services/borrower.service.js';
import { BorrowerDetailPage } from './borrower-detail.page.js';

export function boot(document = globalThis.document, window = globalThis.window) {
  const context = createPageContext({ document, window });
  return new BorrowerDetailPage(document, { service: new BorrowerService({ api: context.api }), document, window }).start();
}

if (typeof document !== 'undefined') boot();
