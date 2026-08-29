export class InventoryService {
  constructor({ api }) { this.api = api; }

  list(params = {}) { return this.api.get('/scan2borrow/api/books', { action: 'list', ...params }); }

  action(action, data = {}) { return this.api.post('/scan2borrow/api/books', { action, ...data }); }

  save(body) { return this.api.post('/scan2borrow/api/books', body); }
}
