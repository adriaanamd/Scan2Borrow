import { createPageContext } from '../../../../app/bootstrap/page-context.js';
import { CopyHistoryPage } from './copy-history.page.js';

export function boot(document = globalThis.document, window = globalThis.window) {
  const context = createPageContext({ document, window });
  return new CopyHistoryPage(document, { api: context.api, window }).start();
}

if (typeof document !== 'undefined') boot();
