export class GuestDashboardService {
  constructor({ api }) { this.api = api; }

  load() { return this.api.get('/scan2borrow/api/guest/dashboard', {}); }
}
