export class StudentSearchService {
  constructor({ api }) {
    this.api = api;
  }

  search(params = {}) {
    return this.api.get('/scan2borrow/api/student/books', { ...params });
  }

  borrow(bookBarcode, dueDate = '') {
    const body = { action: 'borrow', book_barcode: bookBarcode };
    if (dueDate) body.due_date = dueDate;
    return this.api.post('/scan2borrow/api/student/borrow', body);
  }
}
