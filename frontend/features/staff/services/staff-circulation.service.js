export class StaffCirculationService {
  constructor({ api, formDataFactory = () => new FormData() } = {}) {
    this.api = api;
    this.formDataFactory = formDataFactory;
  }

  lookupStudent(barcode) {
    return this.api.get('/scan2borrow/api/staff/circulation/borrower', { barcode: String(barcode || '').trim() });
  }

  borrow({ borrowerBarcode, bookBarcodes = [], dueDate = '' }) {
    const body = this.formDataFactory();
    body.append('borrower_barcode', String(borrowerBarcode || '').trim());
    bookBarcodes.forEach((barcode) => body.append('book_barcodes[]', String(barcode || '').trim()));
    if (dueDate) body.append('due_date', String(dueDate));
    return this.api.post('/scan2borrow/api/staff/circulation/borrow', body);
  }

  returnBooks({ borrowerId, loanIds = [], copyBarcodes = [] }) {
    const body = this.formDataFactory();
    body.append('borrower_id', String(borrowerId || ''));
    loanIds.forEach((id) => body.append('loan_ids[]', String(id)));
    copyBarcodes.forEach((barcode) => body.append('copy_barcodes[]', String(barcode || '').trim()));
    return this.api.post('/scan2borrow/api/staff/circulation/return', body);
  }

  decideBorrowing(action, borrowingId) {
    return this.api.post('/scan2borrow/api/staff/borrowing-action', {
      action: String(action || '').trim(),
      borrowing_id: Number(borrowingId) || 0,
    });
  }

  decideReturn(action, type, returnId, note = '') {
    return this.api.post('/scan2borrow/api/staff/return-action', {
      action: String(action || '').trim(),
      type: String(type || '').trim(),
      id: Number(returnId) || 0,
      note: String(note || '').trim(),
    });
  }
}
