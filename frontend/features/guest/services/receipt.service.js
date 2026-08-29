export class GuestReceiptService {
  constructor({ api }) { this.api = api; }

  load(id) { return this.api.get('/scan2borrow/api/guest/receipt', { id }); }
}
