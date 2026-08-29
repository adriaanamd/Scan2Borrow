export class StudentDashboardService {
  constructor({ api }) {
    this.api = api;
  }

  load() {
    return this.api.get('/scan2borrow/api/student/dashboard', {});
  }

  borrow(bookBarcode, dueDate = '') {
    const body = { action: 'borrow', book_barcode: bookBarcode };
    if (dueDate) body.due_date = dueDate;
    return this.api.post('/scan2borrow/api/student/dashboard', body);
  }

  returnBook(returnInput) {
    return this.api.post('/scan2borrow/api/student/dashboard', {
      action: 'return_unified',
      return_input: returnInput,
    });
  }
}
