export class StaffReservationService {
  constructor({ api }) {
    this.api = api;
  }

  list(status = '') {
    return this.api.get('/scan2borrow/api/staff/reservations', { status });
  }

  fulfil(holdId) {
    return this.api.post('/scan2borrow/api/staff/reservations/action', {
      hold_id: Number(holdId),
      action: 'fulfil',
    });
  }
}
