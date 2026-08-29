export class StaffOverdueService {
  constructor({ api }) { this.api = api; }

  load() { return this.api.get('/scan2borrow/api/staff/overdue', {}); }
}
