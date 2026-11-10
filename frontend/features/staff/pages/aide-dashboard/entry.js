import { createPageContext } from '../../../../app/bootstrap/page-context.js';
import { StaffDashboardService } from '../../services/dashboard.service.js';
import { StaffNotificationService } from '../../services/notification.service.js';
import { AideDashboardPage } from './aide-dashboard.page.js';

export function boot(document = globalThis.document, window = globalThis.window) {
  const context = createPageContext({ document, window });
  const dashboardService = new StaffDashboardService({ api: context.api, window });
  const notificationService = new StaffNotificationService({ api: context.api, window });
  const page = new AideDashboardPage(document, { dashboardService, notificationService, document, window });
  return page.start();
}

if (typeof document !== 'undefined') boot();
