import { BorrowerLookupModal } from '../../components/borrower-lookup-modal/borrower-lookup-modal.component.js';

export class BorrowersPage {
  constructor(root = globalThis.document, {
    service,
    window = globalThis.window,
    lookupModalFactory = (modalRoot, options) => new BorrowerLookupModal(modalRoot, options),
  } = {}) {
    this.root = root;
    this.service = service;
    this.window = window;
    this.lookupModalFactory = lookupModalFactory;
    this.lookupModal = null;
  }

  start() {
    const modalRoot = this.root.getElementById?.('borrower-lookup-modal');
    if (modalRoot) {
      this.lookupModal = this.lookupModalFactory(modalRoot, {
        service: this.service,
        trigger: this.root.getElementById?.('borrower-lookup-trigger'),
        document: this.root,
        window: this.window,
      }).start();
    }
    return this.load();
  }

  async destroy() {
    await this.lookupModal?.destroy?.();
  }

  async load() {
    const search = new URLSearchParams(this.window.location.search).get('search') || '';
    const response = await this.service?.search(search);
    if (response) this.render(response.data?.borrowers || []);
    return response;
  }

  render(rows) {
    const body = this.root.querySelector?.('table tbody');
    if (!body) return;
    body.innerHTML = rows.length ? rows.map((row) => {
      const borrowerId = encodeURIComponent(String(row.id ?? ''));
      const role = String(row.role || '—').replace(/^./, (letter) => letter.toUpperCase());
      const activeLoans = row.active_loans || 0;
      const overdueLoans = Number(row.overdue_loans) || 0;
      return `<tr>
        <td>${this.escape(row.barcode)}</td>
        <td>${this.escape(row.name)}</td>
        <td>${this.escape(role)}</td>
        <td>${this.escape(row.department || '—')}</td>
        <td>${this.escape(row.position || '—')}</td>
        <td>${this.escape(row.course)}</td>
        <td>${this.escape(row.year_level)}</td>
        <td><span class="badge bg-primary">${this.escape(activeLoans)}</span>${overdueLoans ? ` <span class="badge bg-danger">${this.escape(overdueLoans)} overdue</span>` : ''}</td>
        <td>${this.escape(row.status || '—')}</td>
        <td class="text-nowrap"><a href="/scan2borrow/staff/borrower?id=${borrowerId}" class="btn btn-primary btn-sm">View</a> <a href="/scan2borrow/staff/notify?id=${borrowerId}" class="btn btn-warning btn-sm">Notify</a></td>
      </tr>`;
    }).join('') : '<tr><td colspan="10" class="text-center text-muted">No borrowers found.</td></tr>';
  }

  escape(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character])); }
}
