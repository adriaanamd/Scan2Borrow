export class StaffGuestRequestService {
  constructor({ api }) { this.api = api; }

  load() { return this.api.get('/scan2borrow/api/staff/guest-requests', {}); }

  review(id, action, notes = '') { return this.api.post('/scan2borrow/api/staff/guest-action', { id, action, notes }); }
}
