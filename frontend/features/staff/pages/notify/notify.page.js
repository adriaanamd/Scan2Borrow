export class StaffNotifyPage {
  constructor(root = globalThis.document, { borrowerService, notificationService, window = globalThis.window } = {}) {
    this.root = root;
    this.borrowerService = borrowerService;
    this.notificationService = notificationService;
    this.window = window;
    this.userId = '';
    this.channels = { email: false, sms: false };
  }

  start() {
    this.bindActions();
    return this.load();
  }

  async load() {
    const id = new URLSearchParams(this.window.location.search).get('id') || '';
    const response = await this.borrowerService?.details(id);
    if (response) this.render(response.data || {});
    return response;
  }

  render(data) {
    const borrower = data.borrower || {};
    this.userId = borrower.id ?? borrower.user_id ?? new URLSearchParams(this.window.location.search).get('id') ?? '';
    this.channels = {
      email: this.string(borrower.email) !== '',
      sms: this.string(borrower.contact_no) !== '',
    };
    this.text('#notify-name', `${borrower.name || ''} (${borrower.barcode || ''})`);
    this.text('#notify-email', borrower.email || 'No email on file');
    this.text('#notify-contact', borrower.contact_no || 'No contact number on file');
    this.text('#notify-loans', data.summary?.active || 0);
    this.setButtonAvailability('email');
    this.setButtonAvailability('sms');
  }

  bindActions() {
    for (const channel of ['email', 'sms']) {
      const button = this.root.querySelector?.(`#send-${channel}`);
      button?.addEventListener('click', () => this.send(channel));
    }
  }

  async send(channel) {
    const button = this.root.querySelector?.(`#send-${channel}`);
    if (!button || button.disabled || !this.userId || !this.channels[channel]) return null;

    button.disabled = true;
    this.text(`#notify-${channel}-alert`, 'Sending notification...');
    try {
      const result = await this.notificationService?.notify(this.userId, channel);
      const ok = result?.ok !== false;
      this.text(`#notify-${channel}-alert`, result?.message || (ok ? 'Notification sent.' : 'Could not send notification.'));
      return result;
    } catch (error) {
      this.text(`#notify-${channel}-alert`, error?.message || 'Could not send notification.');
      return { ok: false, message: error?.message || 'Could not send notification.' };
    } finally {
      this.setButtonAvailability(channel);
    }
  }

  setButtonAvailability(channel) {
    const button = this.root.querySelector?.(`#send-${channel}`);
    if (button) button.disabled = !this.channels[channel];
  }

  text(selector, value) { const node = this.root.querySelector?.(selector); if (node) node.textContent = value; }

  string(value) { return typeof value === 'string' ? value.trim() : ''; }
}
