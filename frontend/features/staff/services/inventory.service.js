export class InventoryService {
  constructor({ api }) { this.api = api; }

  listTitles(params = {}) { return this.api.get('/scan2borrow/api/books', { action: 'list', ...params }); }

  createTitle(data = {}) { return this.api.post('/scan2borrow/api/books', { action: 'create_title', ...data }); }

  updateTitle(data = {}) { return this.api.post('/scan2borrow/api/books', { action: 'update_title', ...data }); }

  listCopies(titleId) { return this.api.get('/scan2borrow/api/book-copies', { title_id: titleId }); }

  updateCopy(data = {}) { return this.api.post('/scan2borrow/api/book-copies', { action: 'update', ...data }); }

  copyAction(action, ids) { return this.api.post('/scan2borrow/api/book-copies', { action, ids }); }

  list(params = {}) { return this.listTitles(params); }

  action(action, data = {}) { return this.api.post('/scan2borrow/api/books', { action, ...data }); }

  save(body) { return this.api.post('/scan2borrow/api/books', body); }
}
