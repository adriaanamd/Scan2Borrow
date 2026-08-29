export class AdminStaffPage {
  constructor(root = globalThis.document, { service, window = globalThis.window } = {}) { this.root = root; this.service = service; this.window = window; }

  start() { return this.load(); }

  async load() {
    const search = new URLSearchParams(this.window.location.search).get('bsearch') || '';
    const response = await this.service?.list(search);
    if (response) this.render(response.data || {});
    return response;
  }

  render(data) {
    const tables = this.root.querySelectorAll?.('table tbody') || [];
    if (tables[0]) tables[0].innerHTML = this.rows(data.staff || [], 'No staff accounts.');
    if (tables[1]) tables[1].innerHTML = this.rows(data.borrowers || [], 'No borrowers found.');
  }

  rows(rows, empty) { return rows.length ? rows.map((row) => `<tr><td>${this.escape(row.barcode)}</td><td>${this.escape(row.name)}</td><td>${this.escape(row.role || '')}</td><td>${this.escape(row.status || '')}</td></tr>`).join('') : `<tr><td class="text-center text-muted">${empty}</td></tr>`; }
  escape(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character])); }
}
