export class TeacherDashboardService {
  constructor({ api }) {
    this.api = api;
  }

  load() {
    return this.api.get('/scan2borrow/api/teacher/dashboard', {});
  }

  borrow(bookBarcode, dueDate = '') {
    const body = { action: 'borrow', book_barcode: bookBarcode };
    if (dueDate) body.due_date = dueDate;
    return this.api.post('/scan2borrow/api/teacher/dashboard', body);
  }

  returnBook(returnInput) {
    return this.api.post('/scan2borrow/api/teacher/dashboard', {
      action: 'return_unified',
      return_input: returnInput,
    });
  }
}
