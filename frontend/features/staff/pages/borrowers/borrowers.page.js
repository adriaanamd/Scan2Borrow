export class BorrowersPage {
  constructor(root = globalThis.document, { service, window = globalThis.window } = {}) {
    this.root = root;
    this.service = service;
    this.window = window;
  }

  start() { return this.load(); }

  async load() {
    const search = new URLSearchParams(this.window.location.search).get('search') || '';
    const response = await this.service?.search(search);
    if (response) this.render(response.data?.borrowers || []);
    return response;
  }

  render(rows) {
    const body = this.root.querySelector?.('table tbody');
    if (!body) return;
    body.innerHTML = rows.length ? rows.map((row) => `<tr><td>${this.escape(row.barcode)}</td><td>${this.escape(row.name)}</td><td>${this.escape(row.role)}</td><td>${this.escape(row.status)}</td></tr>`).join('') : '<tr><td colspan="10" class="text-center text-muted">No borrowers found.</td></tr>';
  }

  escape(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character])); }
}
