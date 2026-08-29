export class GuestCatalogService {
  constructor({ api }) { this.api = api; }

  browse(params = {}) { return this.api.get('/scan2borrow/api/guest/books', { ...params }); }
}
