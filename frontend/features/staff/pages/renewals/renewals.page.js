export class StaffRenewalsPage {
  constructor(root = globalThis.document, { service, window = globalThis.window } = {}) {
    this.root = root;
    this.service = service;
    this.window = window;
    this.boundClick = (event) => this.handleClick(event);
    this.root.addEventListener?.('click', this.boundClick);
  }
  async start() { return this.load(); }
  async load() {
    try { const response = await this.service.list(); this.render(response?.data?.renewals || []); return response; }
    catch (error) { this.renderError(error?.message || 'Unable to load renewal requests.'); return null; }
  }
  render(rows) {
    const body = this.root.querySelector?.('[data-renewal-body]');
    if (!body) return;
    body.innerHTML = rows.length ? rows.map((row) => `<tr><td>${this.escape(row.user_name || 'Borrower')}</td><td><strong>${this.escape(row.title)}</strong><small>${this.escape(row.transaction_code || '')}</small></td><td>${this.escape(row.original_due_date)}</td><td>${this.escape(row.requested_due_date)}</td><td>${this.escape(row.reason || 'No reason provided')}</td><td><button type="button" class="btn btn-sm btn-primary" data-renewal-action="approve" data-renewal-id="${this.escape(row.id)}">Approve</button> <button type="button" class="btn btn-sm btn-outline-danger" data-renewal-action="reject" data-renewal-id="${this.escape(row.id)}">Reject</button></td></tr>`).join('') : '<tr><td colspan="6" class="text-center text-muted py-4">No renewal requests are waiting for approval.</td></tr>';
  }
  renderError(message) { const body = this.root.querySelector?.('[data-renewal-body]'); if (body) body.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${this.escape(message)}</td></tr>`; }
  async handleClick(event) {
    const button = event.target?.closest?.('[data-renewal-action]');
    if (!button) return;
    button.disabled = true;
    try { await this.service.decide(button.dataset.renewalId, button.dataset.renewalAction); await this.load(); }
    catch { button.disabled = false; }
  }
  escape(value) { return String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]); }
}
