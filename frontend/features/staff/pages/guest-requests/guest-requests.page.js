export class GuestRequestsPage {
  constructor(root = globalThis.document, { service } = {}) { this.root = root; this.service = service; }

  start() { return this.load(); }

  async load() {
    const response = await this.service?.load();
    if (response) this.render(response.data?.requests || []);
    return response;
  }

  render(rows) {
    const body = this.root.querySelector?.('table tbody');
    if (!body) return;
    body.innerHTML = rows.length ? rows.map((row) => `<tr><td>${this.escape(row.name)}</td><td>${this.escape(row.title)}</td><td>${this.escape(row.requested_at || row.created_at)}</td></tr>`).join('') : '<tr><td colspan="6" class="text-center text-muted py-4">No pending guest borrow requests.</td></tr>';
  }

  escape(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character])); }
}
