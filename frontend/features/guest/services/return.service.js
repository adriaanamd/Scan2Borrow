export class GuestReturnService {
  constructor({ api }) { this.api = api; }

  submit(body) { return this.api.post('/scan2borrow/api/guest/return', body); }
}
