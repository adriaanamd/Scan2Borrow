export class StaffNotifyPage {
  constructor(root = globalThis.document, { borrowerService, notificationService, window = globalThis.window } = {}) {
    this.root = root;
    this.borrowerService = borrowerService;
    this.notificationService = notificationService;
    this.window = window;
  }

  start() { return this.load(); }

  async load() {
    const id = new URLSearchParams(this.window.location.search).get('id') || '';
    const response = await this.borrowerService?.details(id);
    if (response) this.render(response.data || {});
    return response;
  }

  render(data) {
    const borrower = data.borrower || {};
    this.text('#notify-name', `${borrower.name || ''} (${borrower.barcode || ''})`);
    this.text('#notify-email', borrower.email || 'No email on file');
    this.text('#notify-contact', borrower.contact_no || 'No contact number on file');
    this.text('#notify-loans', data.summary?.active || 0);
  }

  text(selector, value) { const node = this.root.querySelector?.(selector); if (node) node.textContent = value; }
}
