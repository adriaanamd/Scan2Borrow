export class GuestProfileService {
  constructor({ api }) { this.api = api; }

  load() { return this.api.get('/scan2borrow/api/guest/profile', {}); }

  update(body) { return this.api.post('/scan2borrow/api/guest/profile', body); }
}
