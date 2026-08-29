export class OverduePage {
  constructor(root = globalThis.document, { service } = {}) {
    this.root = root;
    this.service = service;
  }

  start() { return this.load(); }

  async load() {
    const response = await this.service?.load();
    if (response) this.render(response.data || {});
    return response;
  }

  render(data) {
    const body = this.root.querySelector?.('table tbody');
    if (!body) return;
    const rows = data.overdue || [];
    body.innerHTML = rows.length ? rows.map((row) => `<tr class="row-overdue"><td>${this.escape(row.borrower)}</td><td>${this.escape(row.title)}</td><td>${this.escape(row.due_date)}</td><td>${row.days_late || 0} day(s)</td></tr>`).join('') : '<tr><td colspan="6" class="text-center text-muted">No overdue books. 🎉</td></tr>';
  }

  escape(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character])); }
}
