import { createPageContext } from '../../../../app/bootstrap/page-context.js';
import { StaffReservationService } from '../../services/reservation.service.js';
import { StaffReservationsPage } from './reservations.page.js';

export function boot(document = globalThis.document, window = globalThis.window) {
  const context = createPageContext({ document, window });
  return new StaffReservationsPage(document, { service: new StaffReservationService({ api: context.api }), window }).start();
}

if (typeof document !== 'undefined') boot();
