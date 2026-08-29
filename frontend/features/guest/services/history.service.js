export class GuestHistoryService {
  constructor({ api }) { this.api = api; }

  load(params = {}) { return this.api.get('/scan2borrow/api/guest/history', { ...params }); }
}
