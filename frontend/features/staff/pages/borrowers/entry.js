import { createPageContext } from '../../../../app/bootstrap/page-context.js';
import { BorrowerService } from '../../services/borrower.service.js';
import { BorrowersPage } from './borrowers.page.js';

export function boot(document = globalThis.document, window = globalThis.window) {
  const context = createPageContext({ document, window });
  return new BorrowersPage(document, { service: new BorrowerService({ api: context.api }), window }).start();
}

if (typeof document !== 'undefined') boot();
