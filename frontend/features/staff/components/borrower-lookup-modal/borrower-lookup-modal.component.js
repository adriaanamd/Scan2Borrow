import { BarcodeScannerComponent } from '../../../../app/shared/components/barcode-scanner/barcode-scanner.component.js';

export class BorrowerLookupModal {
  constructor(root, {
    service,
    trigger = null,
    document = root?.ownerDocument || globalThis.document,
    window = globalThis.window,
    scannerFactory = (scannerRoot, options) => new BarcodeScannerComponent(scannerRoot, options),
  } = {}) {
    this.root = root;
    this.service = service;
    this.trigger = trigger;
    this.document = document;
    this.window = window;
    this.scannerFactory = scannerFactory;
    this.scanner = null;
    this.form = null;
    this.boundSubmit = (event) => {
      event.preventDefault();
      void this.lookup(this.document.getElementById('borrower-lookup-barcode')?.value || '');
    };
    this.boundShown = () => this.onShown();
    this.boundHidden = () => { void this.onHidden(); };
  }

  start() {
    if (!this.root) return this;
    this.form = this.root.querySelector('#borrower-lookup-form');
    this.form?.addEventListener('submit', this.boundSubmit);
    this.root.addEventListener('shown.bs.modal', this.boundShown);
    this.root.addEventListener('hidden.bs.modal', this.boundHidden);
    this.clearResult();
    this.setStatus('Scan or enter a borrower ID to begin.', 'info');
    return this;
  }

  onShown() {
    const scannerRoot = this.root.querySelector('#borrower-lookup-scanner');
    if (!this.scanner && scannerRoot) {
      this.scanner = this.scannerFactory(scannerRoot, {
        document: this.document,
        window: this.window,
        onDecoded: (value) => this.lookup(value),
      });
      this.scanner.start();
    }
    this.document.getElementById('borrower-lookup-barcode')?.focus?.();
  }

  async onHidden() {
    const scanner = this.scanner;
    this.scanner = null;
    await scanner?.destroy?.();
    this.clearResult();
    this.setStatus('Scan or enter a borrower ID to begin.', 'info');
    this.trigger?.focus?.();
  }

  async lookup(barcode) {
    const value = String(barcode || '').trim();
    this.clearResult();
    if (!value) {
      this.setStatus('Scan or enter a borrower ID barcode.', 'error');
      return null;
    }

    this.setBusy(true);
    this.setStatus('Loading borrower account...', 'loading');
    try {
      const response = await this.service.lookupByBarcode(value);
      const data = response?.data || response || {};
      if (!data.borrower) throw new Error('Student account not found for that ID.');
      const loans = Array.isArray(data.active_loans) ? data.active_loans : [];
      this.renderResult(data.borrower, loans);
      this.setStatus('Borrower account loaded.', 'success');
      return data;
    } catch (error) {
      this.clearResult();
      this.setStatus(error?.message || 'Borrower lookup failed.', 'error');
      return null;
    } finally {
      this.setBusy(false);
    }
  }

  renderResult(borrower, loans) {
    this.text('borrower-lookup-name', borrower.name || [borrower.firstname, borrower.lastname].filter(Boolean).join(' ') || 'Borrower');
    this.text('borrower-lookup-id', borrower.barcode || '—');
    this.text('borrower-lookup-role', borrower.role || '—');
    this.text('borrower-lookup-status-value', borrower.status || '—');
    this.text('borrower-lookup-active', borrower.active_loans ?? loans.length);

    const details = this.document.getElementById('borrower-lookup-details');
    const borrowerId = String(borrower.id ?? '').trim();
    if (details) {
      details.href = borrowerId ? `/scan2borrow/staff/borrower?id=${encodeURIComponent(borrowerId)}` : '#';
      details.hidden = !borrowerId;
    }

    const list = this.document.getElementById('borrower-lookup-loans');
    if (list) {
      list.innerHTML = loans.length
        ? loans.map((loan) => `<li><strong>${this.escape(loan.title || '—')}</strong><span>${this.escape(loan.copy_barcode || loan.barcode || '—')}</span><time>Due ${this.escape(loan.due_date || '—')}</time></li>`).join('')
        : '<li class="borrower-lookup-empty">No active loans.</li>';
    }

    const result = this.document.getElementById('borrower-lookup-result');
    if (result) result.hidden = false;
  }

  clearResult() {
    const result = this.document.getElementById('borrower-lookup-result');
    if (result) result.hidden = true;
    const list = this.document.getElementById('borrower-lookup-loans');
    if (list) list.innerHTML = '';
    ['borrower-lookup-name', 'borrower-lookup-id', 'borrower-lookup-role', 'borrower-lookup-status-value', 'borrower-lookup-active']
      .forEach((id) => this.text(id, ''));
  }

  setBusy(busy) {
    this.root.querySelectorAll?.('#borrower-lookup-form button, #borrower-lookup-barcode')
      .forEach((node) => { node.disabled = busy; });
  }

  setStatus(message, state) {
    const node = this.document.getElementById('borrower-lookup-status');
    if (!node) return;
    node.textContent = String(message || '');
    node.dataset.state = state;
    node.hidden = !message;
  }

  text(id, value) {
    const node = this.document.getElementById(id);
    if (node) node.textContent = String(value ?? '');
  }

  escape(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    })[character]);
  }

  destroy() {
    this.form?.removeEventListener?.('submit', this.boundSubmit);
    this.root?.removeEventListener?.('shown.bs.modal', this.boundShown);
    this.root?.removeEventListener?.('hidden.bs.modal', this.boundHidden);
    return this.onHidden();
  }
}
