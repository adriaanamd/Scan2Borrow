export class BorrowerDetailPage {
  constructor(root = globalThis.document, { service, window = globalThis.window, document = globalThis.document } = {}) {
    this.root = root;
    this.service = service;
    this.window = window;
    this.document = document;
  }

  start() { return this.load(); }

  async load() {
    const id = new URLSearchParams(this.window.location.search).get('id') || '';
    const response = await this.service?.details(id);
    if (response) this.render(response.data || {});
    return response;
  }

  render(data) {
    const borrower = data.borrower || {};
    this.text('borrower-name', borrower.name || 'Borrower Details');
    this.text('borrower-status', borrower.status || '');
    this.text('borrower-active', data.summary?.active || 0);
    this.text('borrower-returned', data.summary?.returned || 0);
    this.text('borrower-overdue-count', data.summary?.overdue || 0);
    this.renderHistory(data.history || []);
    this.document.getElementById('change-photo')?.classList.toggle('d-none', !data.can_edit_photo);
  }

  renderHistory(rows) {
    const body = this.document.getElementById('borrower-history');
    if (!body) return;
    body.innerHTML = rows.length ? rows.map((row) => `<tr><td><code>${this.escape(row.transaction_code)}</code></td><td>${this.escape(row.title)}</td><td>${this.escape(row.status)}</td></tr>`).join('') : '<tr><td colspan="7" class="text-center text-muted">No borrowing history found.</td></tr>';
  }

  text(id, value) { const node = this.document.getElementById(id); if (node) node.textContent = value ?? ''; }
  escape(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character])); }
}
