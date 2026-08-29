import { createPageContext } from '../../../../app/bootstrap/page-context.js';
import { StaffReportService } from '../../services/report.service.js';
import { ReportsPage } from './reports.page.js';

export function boot(document = globalThis.document, window = globalThis.window) {
  const context = createPageContext({ document, window });
  return new ReportsPage(document, { service: new StaffReportService({ api: context.api }), window }).start();
}

if (typeof document !== 'undefined') boot();
