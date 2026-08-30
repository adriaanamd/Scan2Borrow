export class StaffReservationsPage {
  constructor(root = globalThis.document, { service, window = globalThis.window } = {}) {
    this.root = root;
    this.service = service;
    this.window = window;
    this.boundClick = (event) => this.handleClick(event);
    this.root.addEventListener?.('click', this.boundClick);
  }

  async start() {
    return this.load();
  }

  async load() {
    const status = new URLSearchParams(this.window?.location?.search || '').get('status') || '';
    try {
      const response = await this.service.list(status);
      this.render(response?.data?.reservations || []);
      return response;
    } catch (error) {
      this.renderError(error?.message || 'Unable to load reservation queue.');
      return null;
    }
  }

  render(rows) {
    const body = this.root.querySelector?.('[data-reservation-body]');
    if (!body) return;
    body.innerHTML = rows.length ? rows.map((row) => `<tr><td>${this.escape(row.queue_position || '—')}</td><td>${this.escape(row.title)}</td><td>${this.escape(row.author)}</td><td>${this.escape(row.user_name || 'Borrower')}</td><td><span class="staff-reservations__status staff-reservations__status--${this.escape(row.status)}">${this.escape(row.status_label || row.status)}</span></td><td>${row.status === 'claimed' ? `<button type="button" class="btn btn-sm btn-primary" data-reservation-fulfil data-hold-id="${this.escape(row.id)}">Fulfil</button>` : '—'}</td></tr>`).join('') : '<tr><td colspan="6" class="text-center text-muted py-4">No reservations match this view.</td></tr>';
  }

  renderError(message) {
    const body = this.root.querySelector?.('[data-reservation-body]');
    if (body) body.innerHTML = `<tr><td colspan="6" class="text-center text-danger py-4">${this.escape(message)}</td></tr>`;
  }

  async handleClick(event) {
    const button = event.target?.closest?.('[data-reservation-fulfil]');
    if (!button) return;
    button.disabled = true;
    try {
      await this.service.fulfil(button.dataset.holdId);
      await this.load();
    } catch {
      button.disabled = false;
    }
  }

  escape(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[character]);
  }
}
