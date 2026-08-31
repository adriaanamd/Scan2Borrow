import { BarcodeScannerComponent } from '../../../../app/shared/components/barcode-scanner/barcode-scanner.component.js';

export class CopyHistoryPage {
  constructor(document = globalThis.document, { api, window = globalThis.window } = {}) {
    this.document = document;
    this.api = api;
    this.window = window;
    this.form = document.getElementById('copy-history-form');
    this.input = document.getElementById('copy-history-barcode');
    this.copy = document.getElementById('copy-history-copy');
    this.timeline = document.getElementById('copy-history-timeline');
    this.status = document.getElementById('copy-history-status');
    this.scanner = null;
    this.onSubmit = this.onSubmit.bind(this);
  }

  async start() {
    this.form?.addEventListener('submit', this.onSubmit);
    const scannerRoot = this.document.getElementById('copy-history-scanner');
    if (scannerRoot) {
      this.scanner = new BarcodeScannerComponent(scannerRoot, { document: this.document, window: this.window }).start();
    }
    const barcode = new URLSearchParams(this.window?.location?.search || '').get('barcode') || '';
    if (barcode) {
      this.input.value = barcode;
      await this.load(barcode);
    }
    return this;
  }

  async onSubmit(event) {
    event.preventDefault();
    await this.load(this.input?.value || '');
  }

  async load(barcode) {
    const value = String(barcode || '').trim();
    if (!value) {
      this.showStatus('Enter or scan a copy barcode.', 'error');
      return;
    }
    this.showStatus('Loading copy history...', 'loading');
    this.copy.hidden = true;
    this.timeline.replaceChildren();
    try {
      const payload = await this.api.get('/scan2borrow/api/staff/copy-history', { barcode: value });
      this.render(payload.data || payload);
      this.window?.history?.replaceState?.({}, '', `/scan2borrow/staff/copy-history?barcode=${encodeURIComponent(value)}`);
    } catch (error) {
      this.showStatus(error.message || 'Copy history could not be loaded.', 'error');
    }
  }

  render(payload) {
    const copy = payload?.copy || {};
    this.copy.hidden = false;
    this.text('copy-history-title', copy.title || '');
    this.text('copy-history-author', copy.author || '');
    this.text('copy-history-barcode-value', copy.barcode || '');
    this.text('copy-history-accession', copy.accession_no || '');
    this.text('copy-history-location', copy.location || '');
    this.text('copy-history-current-status', copy.status || '');
    const events = Array.isArray(payload?.events) ? payload.events : [];
    this.timeline.replaceChildren();
    if (!events.length) {
      this.showStatus('No recorded audit events for this copy.', 'empty');
      return;
    }
    events.forEach((event) => this.timeline.appendChild(this.eventElement(event)));
    this.showStatus(`${events.length} recorded event${events.length === 1 ? '' : 's'}.`, 'success');
  }

  eventElement(event) {
    const article = this.document.createElement('article');
    article.className = 'copy-history-event';
    const marker = this.document.createElement('span');
    marker.className = 'copy-history-event__marker';
    marker.setAttribute('aria-hidden', 'true');
    const content = this.document.createElement('div');
    content.className = 'copy-history-event__content';
    const heading = this.document.createElement('div');
    heading.className = 'copy-history-event__heading';
    const label = this.document.createElement('h3');
    label.textContent = event.label || event.type || 'Audit event';
    const time = this.document.createElement('time');
    time.textContent = event.occurred_at || '';
    heading.append(label, time);
    const actor = this.document.createElement('p');
    actor.className = 'copy-history-event__actor';
    actor.textContent = event.actor || 'Actor not recorded';
    content.append(heading, actor);
    if (event.from_status || event.to_status) {
      const transition = this.document.createElement('p');
      transition.className = 'copy-history-event__transition';
      transition.textContent = `${event.from_status || '—'} → ${event.to_status || '—'}`;
      content.appendChild(transition);
    }
    if (event.reason) {
      const reason = this.document.createElement('p');
      reason.className = 'copy-history-event__reason';
      reason.textContent = `Reason: ${event.reason}`;
      content.appendChild(reason);
    }
    article.append(marker, content);

    return article;
  }

  text(id, value) {
    const element = this.document.getElementById(id);
    if (element) element.textContent = String(value ?? '');
  }

  showStatus(message, state) {
    if (!this.status) return;
    this.status.textContent = message;
    this.status.dataset.state = state;
    this.status.hidden = !message;
  }

  destroy() {
    this.form?.removeEventListener('submit', this.onSubmit);
    return this.scanner?.destroy();
  }
}
