import { BarcodeScannerComponent } from '../../../../app/shared/components/barcode-scanner/barcode-scanner.component.js';

export class CirculationPage {
  constructor(root = globalThis.document, { service, document = root, window = globalThis.window } = {}) {
    this.root = root;
    this.document = document;
    this.window = window;
    this.service = service;
    this.mode = 'borrow';
    this.borrower = null;
    this.returnLoans = [];
    this.borrowCopies = [];
    this.requestedBooks = [];
    this.history = [];
    this.summary = {};
    this.historyFilter = 'all';
    this.selectedReturnIds = new Set();
    this.borrowerScanner = null;
    this.borrowScanner = null;
    this.returnScanner = null;
    this.boundClick = (event) => this.onClick(event);
    this.boundChange = (event) => this.onChange(event);
    this.boundBorrowerSubmit = (event) => this.onBorrowerSubmit(event);
    this.boundBorrowSubmit = (event) => this.onBorrowSubmit(event);
    this.boundReturnSubmit = (event) => this.onReturnSubmit(event);
    this.bound = false;
  }

  start() {
    if (!this.bound) {
      this.bound = true;
      this.root.addEventListener?.('click', this.boundClick);
      this.root.addEventListener?.('change', this.boundChange);
      this.document.getElementById('circulation-borrower-form')?.addEventListener('submit', this.boundBorrowerSubmit);
      this.document.getElementById('circulation-borrow-form')?.addEventListener('submit', this.boundBorrowSubmit);
      this.document.getElementById('circulation-return-form')?.addEventListener('submit', this.boundReturnSubmit);
    }
    this.createScanners();
    this.setMode(this.mode);
    this.renderBorrowCopies();
    this.renderReturnLoans();
    return this;
  }

  createScanners() {
    const create = (id, callback) => {
      const root = this.document.getElementById(id);
      if (!root) return null;
      return new BarcodeScannerComponent(root, {
        document: this.document,
        window: this.window,
        onDecoded: (value) => callback(value),
      }).start();
    };
    this.borrowerScanner = create('circulation-borrower-scanner', (value) => this.lookupStudent(value));
    this.borrowScanner = create('circulation-book-scanner', (value) => this.addBorrowCopy(value));
    this.returnScanner = create('circulation-return-scanner', (value) => this.addReturnCopy(value));
  }

  async onBorrowerSubmit(event) {
    event.preventDefault();
    await this.lookupStudent(this.document.getElementById('circulation-borrower-barcode')?.value || '');
  }

  onBorrowSubmit(event) {
    event.preventDefault();
    const input = this.document.getElementById('circulation-book-barcode');
    this.addBorrowCopy(input?.value || '');
    if (input) input.value = '';
  }

  onReturnSubmit(event) {
    event.preventDefault();
    const input = this.document.getElementById('circulation-return-book-barcode');
    this.addReturnCopy(input?.value || '');
    if (input) input.value = '';
  }

  onClick(event) {
    const modeButton = event.target?.closest?.('[data-circulation-mode]');
    if (modeButton) {
      event.preventDefault();
      this.setMode(modeButton.dataset.circulationMode);
      return;
    }
    const historyFilter = event.target?.closest?.('[data-circulation-history-filter]');
    if (historyFilter) {
      this.historyFilter = historyFilter.dataset.circulationHistoryFilter === 'returned' ? 'returned' : 'all';
      this.renderHistory();
      return;
    }
    const requestAction = event.target?.closest?.('[data-circulation-request-action]');
    if (requestAction) {
      const card = requestAction.closest?.('[data-circulation-request-card]');
      const note = card?.querySelector?.('[data-circulation-return-note]')?.value || '';
      void this.decideRequest(
        requestAction.dataset.circulationRequestType,
        requestAction.dataset.circulationRequestAction,
        requestAction.dataset.circulationRequestId,
        requestAction.dataset.circulationReturnType,
        note,
      );
      return;
    }
    const action = event.target?.closest?.('[data-circulation-action]')?.dataset?.circulationAction;
    if (action === 'review-borrow') this.reviewBorrow();
    if (action === 'approve-borrow') void this.approveBorrow();
    if (action === 'reject-borrow') this.rejectBorrow();
    if (action === 'clear-borrower') this.clearSession();
    if (action === 'submit-return') void this.submitReturn();
    const removeCopy = event.target?.closest?.('[data-circulation-remove-copy]');
    if (removeCopy) {
      const barcode = removeCopy.dataset.circulationRemoveCopy;
      this.borrowCopies = this.borrowCopies.filter((copy) => copy.barcode !== barcode);
      this.renderBorrowCopies();
    }
  }

  onChange(event) {
    const checkbox = event.target?.closest?.('[data-return-loan]');
    if (!checkbox) return;
    this.selectReturnLoan(checkbox.dataset.returnLoan, checkbox.checked);
  }

  setMode(mode) {
    this.mode = mode === 'return' ? 'return' : 'borrow';
    this.document.querySelectorAll?.('[data-circulation-mode]').forEach((button) => {
      const active = button.dataset.circulationMode === this.mode;
      button.classList?.toggle('is-active', active);
      button.setAttribute?.('aria-selected', active ? 'true' : 'false');
    });
    const borrowPanel = this.document.getElementById('circulation-borrow-panel');
    const returnPanel = this.document.getElementById('circulation-return-panel');
    if (borrowPanel) borrowPanel.hidden = this.mode !== 'borrow';
    if (returnPanel) returnPanel.hidden = this.mode !== 'return';
  }

  async lookupStudent(barcode) {
    const value = String(barcode || '').trim();
    if (!value) {
      this.showStatus('Scan or enter a student ID barcode first.', 'error');
      return null;
    }
    this.showStatus('Loading borrower account...', 'loading');
    try {
      const response = await this.service.lookupStudent(value);
      const data = response?.data || response || {};
      this.borrower = data.borrower || null;
      this.returnLoans = Array.isArray(data.active_loans) ? data.active_loans : [];
      this.requestedBooks = Array.isArray(data.requested_books) ? data.requested_books : [];
      this.history = Array.isArray(data.history) ? data.history : [];
      this.summary = data.summary && typeof data.summary === 'object' ? data.summary : {};
      this.historyFilter = 'all';
      this.selectedReturnIds.clear();
      this.borrowCopies = [];
      this.renderBorrower();
      this.renderRequestedBooks();
      this.renderHistory();
      this.renderSummary();
      this.renderBorrowCopies();
      this.renderReturnLoans();
      this.showStatus(`${this.borrower?.name || 'Borrower'} is ready for circulation.`, 'success');
      return data;
    } catch (error) {
      this.borrower = null;
      this.returnLoans = [];
      this.requestedBooks = [];
      this.history = [];
      this.summary = {};
      this.selectedReturnIds.clear();
      this.renderBorrower();
      this.renderRequestedBooks();
      this.renderHistory();
      this.renderSummary();
      this.renderReturnLoans();
      this.showStatus(error?.message || 'Student account could not be loaded.', 'error');
      return null;
    }
  }

  addBorrowCopy(barcode) {
    const value = String(barcode || '').trim();
    if (!this.borrower) return this.showStatus('Scan a student ID before adding book copies.', 'error');
    if (!value) return this.showStatus('Enter or scan a book copy barcode.', 'error');
    if (this.borrowCopies.some((copy) => copy.barcode === value)) return this.showStatus('That copy is already in the borrowing draft.', 'error');
    this.borrowCopies.push({ barcode: value });
    this.renderBorrowCopies();
    this.showStatus(`${this.borrowCopies.length} copy${this.borrowCopies.length === 1 ? '' : 'ies'} ready for review.`, 'success');
  }

  addReturnCopy(barcode) {
    const value = String(barcode || '').trim();
    if (!this.borrower) return this.showStatus('Scan a student ID before selecting returns.', 'error');
    const loan = this.returnLoans.find((candidate) => String(candidate.copy_barcode || candidate.barcode || '') === value);
    if (!loan) return this.showStatus('That copy is not an active loan for this borrower.', 'error');
    this.selectedReturnIds.add(this.returnLoanKey(loan));
    this.renderReturnLoans();
    this.showStatus(`${this.selectedReturnIds.size} return${this.selectedReturnIds.size === 1 ? '' : 's'} selected.`, 'success');
  }

  selectReturnLoan(loanId, selected) {
    const rawId = String(loanId || '').trim();
    const loan = this.returnLoans.find((candidate) => String(candidate.id || '').trim() === rawId);
    const id = loan ? this.returnLoanKey(loan) : rawId;
    if (!id) return;
    if (selected) this.selectedReturnIds.add(id);
    else this.selectedReturnIds.delete(id);
    this.renderReturnLoans();
  }

  reviewBorrow() {
    if (!this.borrower) return this.showStatus('Scan a student ID before reviewing a borrowing transaction.', 'error');
    if (!this.borrowCopies.length) return this.showStatus('Add at least one book copy before reviewing.', 'error');
    const modal = this.document.getElementById('circulation-review-modal');
    if (modal) modal.hidden = false;
    const list = this.document.getElementById('circulation-review-items');
    if (list) list.innerHTML = this.borrowCopies.map((copy) => `<li>${this.escape(copy.barcode)}</li>`).join('');
  }

  async approveBorrow() {
    if (!this.borrower || !this.borrowCopies.length) return;
    const proceed = () => this.submitBorrow();
    const confirm = this.window?.Scan2BorrowConfirmation?.confirm;
    if (typeof confirm === 'function') {
      await confirm({
        title: 'Approve borrowing transaction?',
        message: `Approve ${this.borrowCopies.length} scanned copy${this.borrowCopies.length === 1 ? '' : 'ies'} for ${this.borrower.name || 'this borrower'}?`,
        confirmLabel: 'Approve borrowing',
        confirmClass: 'btn-primary',
        onConfirm: proceed,
      });
      return;
    }
    await proceed();
  }

  async submitBorrow() {
    const dueDate = this.document.getElementById('circulation-due-date')?.value || '';
    try {
      const response = await this.service.borrow({
        borrowerBarcode: this.borrower.barcode,
        bookBarcodes: this.borrowCopies.map((copy) => copy.barcode),
        dueDate,
      });
      this.borrowCopies = [];
      const modal = this.document.getElementById('circulation-review-modal');
      if (modal) modal.hidden = true;
      this.renderBorrowCopies();
      this.showStatus(response?.message || response?.data?.message || 'Borrowing transaction approved.', 'success');
      return response;
    } catch (error) {
      this.showStatus(error?.message || 'Borrowing transaction could not be approved.', 'error');
      throw error;
    }
  }

  async decideBorrowing(action, borrowingId) {
    const value = String(action || '').trim();
    const id = Number(borrowingId) || 0;
    if (!this.borrower || !id || !['approve', 'reject'].includes(value)) return null;
    try {
      const response = await this.service.decideBorrowing(value, id);
      await this.lookupStudent(this.borrower.barcode);
      this.showStatus(response?.message || (value === 'approve' ? 'Borrow request approved.' : 'Borrow request rejected.'), 'success');
      return response;
    } catch (error) {
      this.showStatus(error?.message || 'Borrowing request could not be updated.', 'error');
      return null;
    }
  }

  async decideRequest(requestType, action, requestId, returnType = '', note = '') {
    if (requestType === 'return') {
      return this.decideReturnRequest(action, returnType, requestId, note);
    }

    return this.decideBorrowing(action, requestId);
  }

  async decideReturnRequest(action, returnType, returnId, note = '') {
    const value = String(action || '').trim();
    const type = String(returnType || '').trim();
    const id = Number(returnId) || 0;
    const decisionNote = String(note || '').trim();
    if (!this.borrower || !id || !['approve', 'reject'].includes(value) || !type) return null;
    if (value === 'reject' && !decisionNote) {
      this.showStatus('Enter a reason before rejecting the return request.', 'error');
      return null;
    }
    try {
      const response = await this.service.decideReturn(value, type, id, decisionNote);
      await this.lookupStudent(this.borrower.barcode);
      this.showStatus(response?.message || (value === 'approve' ? 'Return approved.' : 'Return rejected.'), 'success');
      return response;
    } catch (error) {
      this.showStatus(error?.message || 'Return request could not be updated.', 'error');
      return null;
    }
  }

  rejectBorrow() {
    this.borrowCopies = [];
    const modal = this.document.getElementById('circulation-review-modal');
    if (modal) modal.hidden = true;
    this.renderBorrowCopies();
    this.showStatus('Borrowing draft rejected and cleared.', 'info');
  }

  async submitReturn() {
    if (!this.borrower) return this.showStatus('Scan a student ID before processing a return.', 'error');
    const selectedLoans = this.returnLoans.filter((loan) => this.selectedReturnIds.has(this.returnLoanKey(loan)));
    if (!selectedLoans.length) return this.showStatus('Select or scan at least one active book to return.', 'error');
    try {
      const response = await this.service.returnBooks({
        borrowerId: this.borrower.id,
        loanIds: selectedLoans.map((loan) => Number(loan.id)),
        copyBarcodes: selectedLoans.map((loan) => String(loan.copy_barcode || loan.barcode || '')),
      });
      const selected = new Set(selectedLoans.map((loan) => this.returnLoanKey(loan)));
      this.returnLoans = this.returnLoans.filter((loan) => !selected.has(this.returnLoanKey(loan)));
      this.selectedReturnIds.clear();
      this.renderReturnLoans();
      this.showStatus(response?.message || response?.data?.message || 'Return processed successfully.', 'success');
      return response;
    } catch (error) {
      this.showStatus(error?.message || 'Return could not be processed.', 'error');
      return null;
    }
  }

  clearSession() {
    this.borrower = null;
    this.borrowCopies = [];
    this.returnLoans = [];
    this.requestedBooks = [];
    this.history = [];
    this.summary = {};
    this.historyFilter = 'all';
    this.selectedReturnIds.clear();
    this.renderBorrower();
    this.renderRequestedBooks();
    this.renderHistory();
    this.renderSummary();
    this.renderBorrowCopies();
    this.renderReturnLoans();
    this.showStatus('Circulation session cleared.', 'info');
  }

  renderBorrower() {
    const panel = this.document.getElementById('circulation-borrower');
    if (panel) panel.hidden = !this.borrower;
    const avatar = this.document.getElementById('circulation-borrower-photo');
    if (avatar) {
      const name = String(this.borrower?.name || '').trim();
      const nameParts = name.split(/\s+/).filter(Boolean);
      const initials = `${this.borrower?.firstname?.[0] || nameParts[0]?.[0] || ''}${this.borrower?.lastname?.[0] || nameParts.at(-1)?.[0] || ''}`.toUpperCase();
      const photo = this.media(this.borrower?.photo || '');
      avatar.innerHTML = photo
        ? `<img src="${this.escape(photo)}" alt="Borrower profile photo" />`
        : `<span aria-hidden="true">${this.escape(initials || '--')}</span>`;
    }
    this.text('circulation-borrower-name', this.borrower?.name || '');
    this.text('circulation-borrower-id', this.borrower?.barcode || '');
    this.text('circulation-borrower-role', this.borrower?.role || '');
    this.text('circulation-borrower-course', this.borrower?.course || this.borrower?.department || '');
    this.text('circulation-borrower-year', this.borrower?.year_level || this.borrower?.position || '');
    this.text('circulation-borrower-status', this.borrower?.status || '');
    this.text('circulation-borrower-active', this.summary.active ?? this.borrower?.active_loans ?? this.returnLoans.length);
    this.text('circulation-borrower-fine', this.money(this.summary.total_fine));
  }

  renderRequestedBooks() {
    const section = this.document.getElementById('circulation-requested-section');
    if (section) section.hidden = !this.borrower;
    this.text('circulation-requested-count', this.requestedBooks.length);
    const list = this.document.getElementById('circulation-requested-books');
    if (!list) return;
    list.innerHTML = this.requestedBooks.length
      ? this.requestedBooks.map((request) => {
        const id = this.escape(request.id);
        const requestType = request.request_type === 'return' ? 'return' : 'borrow';
        const isReturnRequest = requestType === 'return';
        const title = this.escape(request.title || request.book_title || 'Requested book');
        const author = this.escape(request.author || '');
        const requestedAt = this.escape(request.requested_at || request.requested_date || '');
        const dueDate = this.escape(request.due_date || 'Not set');
        const returnDate = this.escape(request.return_date || (isReturnRequest ? 'Pending verification' : 'Not returned'));
        const status = isReturnRequest ? 'Return verification pending' : 'Pending borrow request';
        const note = isReturnRequest
          ? '<label class="circulation-request-card__note"><span>Decision note</span><input type="text" maxlength="500" placeholder="Required when rejecting" data-circulation-return-note="' + id + '" /></label>'
          : '';
        const approveLabel = isReturnRequest ? 'Approve return' : 'Approve';
        const rejectLabel = isReturnRequest ? 'Reject return' : 'Reject';
        const returnType = this.escape(request.type || '');
        return '<article class="circulation-request-card" data-circulation-request-card>' +
          '<div class="circulation-book-mark" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 5.5A2.5 2.5 0 0 1 7.5 3H19v16H7.5A2.5 2.5 0 0 0 5 21.5zM5 5.5v16M9 8h6"/></svg></div>' +
          '<div class="circulation-request-card__body"><h3>' + title + '</h3><p>' + (author ? 'By ' + author : 'Author not provided') + '</p><small>' + (requestedAt ? 'Requested: ' + requestedAt : 'Request date not provided') + '</small><div class="circulation-request-card__dates"><span><strong>Due date:</strong> ' + dueDate + '</span><span><strong>Return date:</strong> ' + returnDate + '</span></div></div>' +
          '<span class="circulation-badge circulation-badge--pending">' + status + '</span>' +
          note +
          '<div class="circulation-request-card__actions"><button type="button" class="circulation-approve" data-circulation-request-action="approve" data-circulation-request-type="' + requestType + '" data-circulation-return-type="' + returnType + '" data-circulation-request-id="' + id + '">' + approveLabel + '</button><button type="button" class="circulation-reject" data-circulation-request-action="reject" data-circulation-request-type="' + requestType + '" data-circulation-return-type="' + returnType + '" data-circulation-request-id="' + id + '">' + rejectLabel + '</button></div>' +
          '</article>';
      }).join('')
      : '<p class="circulation-empty">No pending borrow or return requests for this borrower.</p>';
  }

  renderHistory() {
    const section = this.document.getElementById('circulation-history-section');
    if (section) section.hidden = !this.borrower;
    this.text('circulation-history-count', this.history.length);
    const filters = this.document.querySelectorAll?.('[data-circulation-history-filter]') || [];
    filters.forEach((button) => {
      const active = button.dataset.circulationHistoryFilter === this.historyFilter;
      button.classList?.toggle('is-active', active);
      button.setAttribute?.('aria-selected', active ? 'true' : 'false');
    });
    const list = this.document.getElementById('circulation-history-list');
    if (!list) return;
    const rows = this.historyFilter === 'returned'
      ? this.history.filter((row) => this.isReturned(row))
      : this.history;
    list.innerHTML = rows.length
      ? rows.map((row) => {
        const title = this.escape(row.title || row.book_title || 'Borrowed book');
        const author = this.escape(row.author || '');
        const borrowed = this.escape(row.borrow_date || '');
        const returned = this.escape(row.return_date || '');
        const due = this.escape(row.due_date || '');
        const status = this.escape(row.status || '');
        const meta = returned ? `Returned: ${returned}` : due ? `Due: ${due}` : 'Return date not provided';
        return `<article class="circulation-history-card"><div class="circulation-book-mark circulation-book-mark--history" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 5.5A2.5 2.5 0 0 1 7.5 3H19v16H7.5A2.5 2.5 0 0 0 5 21.5zM5 5.5v16M9 8h6"/></svg></div><div class="circulation-history-card__body"><h3>${title}</h3><p>${author ? `By ${author}` : ''}</p><small>${borrowed ? `Borrowed: ${borrowed}` : 'Borrow date not provided'}${meta ? ` · ${meta}` : ''}</small></div><span class="circulation-badge circulation-badge--${this.statusClass(row.status)}">${status || 'Recorded'}</span></article>`;
      }).join('')
      : `<p class="circulation-empty">No ${this.historyFilter === 'returned' ? 'returned books' : 'borrowed book history'} for this borrower.</p>`;
  }

  renderSummary() {
    const section = this.document.getElementById('circulation-summary-section');
    if (section) section.hidden = !this.borrower;
    this.text('circulation-summary-active', this.summary.active ?? 0);
    this.text('circulation-summary-returned', this.summary.returned ?? 0);
    this.text('circulation-summary-overdue', this.summary.overdue ?? 0);
    this.text('circulation-summary-fine', this.money(this.summary.total_fine));
  }

  renderBorrowCopies() {
    const list = this.document.getElementById('circulation-borrow-copy-list');
    if (!list) return;
    list.innerHTML = this.borrowCopies.length
      ? this.borrowCopies.map((copy) => `<li><span>${this.escape(copy.barcode)}</span><button type="button" data-circulation-remove-copy="${this.escape(copy.barcode)}" aria-label="Remove ${this.escape(copy.barcode)}">Remove</button></li>`).join('')
      : '<li class="circulation-empty">No book copies scanned yet.</li>';
  }

  renderReturnLoans() {
    const list = this.document.getElementById('circulation-return-loans');
    if (!list) return;
    list.innerHTML = this.returnLoans.length
      ? this.returnLoans.map((loan) => {
        const key = this.returnLoanKey(loan);
        const id = this.escape(key);
        const checked = this.selectedReturnIds.has(key) ? ' checked' : '';
        return `<label class="circulation-loan"><input type="checkbox" data-return-loan="${id}"${checked}><span><strong>${this.escape(loan.title || 'Book copy')}</strong><small>${this.escape(loan.copy_barcode || loan.barcode || '')} · due ${this.escape(loan.due_date || '—')}</small></span></label>`;
      }).join('')
      : '<p class="circulation-empty">Scan a student ID to load active loans.</p>';
  }

  text(id, value) {
    const node = this.document.getElementById(id);
    if (node) node.textContent = String(value ?? '');
  }

  money(value) {
    const amount = Number(value);
    return Number.isFinite(amount) ? `₱${amount.toFixed(2)}` : '₱0.00';
  }

  isReturned(row) {
    return Boolean(row?.return_date) || String(row?.status || '').toLowerCase() === 'returned';
  }

  statusClass(status) {
    const value = String(status || '').toLowerCase();
    if (value.includes('overdue')) return 'overdue';
    if (value.includes('borrow')) return 'borrowed';
    if (value.includes('return')) return 'returned';
    return 'neutral';
  }

  returnLoanKey(loan) {
    const explicit = String(loan?.loan_key || '').trim();
    if (explicit) return explicit;
    return `${String(loan?.storage || 'legacy').trim()}:${String(loan?.id || '').trim()}`;
  }

  showStatus(message, state = 'info') {
    const node = this.document.getElementById('circulation-status');
    if (!node) return;
    node.textContent = String(message || '');
    node.dataset.state = state;
    node.hidden = !message;
  }

  media(value) {
    const resolver = this.window?.Scan2BorrowMedia?.resolve || globalThis.Scan2BorrowMedia?.resolve;
    if (typeof resolver === 'function') return resolver(value);

    const normalized = String(value ?? '').trim().replace(/\\/g, '/');
    if (!normalized) return '';
    if (/^(data:image\/|blob:|https?:\/\/|\/\/)/i.test(normalized)) return normalized;
    if (normalized.toLowerCase().startsWith('/scan2borrow/')) return normalized;
    if (normalized.toLowerCase().startsWith('/uploads/')) return `/scan2borrow${normalized}`;
    if (normalized.toLowerCase().startsWith('uploads/')) return `/scan2borrow/${normalized}`;
    return normalized.startsWith('/') ? normalized : `/scan2borrow/${normalized}`;
  }

  escape(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
  }

  destroy() {
    if (this.bound) {
      this.root.removeEventListener?.('click', this.boundClick);
      this.root.removeEventListener?.('change', this.boundChange);
      this.document.getElementById('circulation-borrower-form')?.removeEventListener?.('submit', this.boundBorrowerSubmit);
      this.document.getElementById('circulation-borrow-form')?.removeEventListener?.('submit', this.boundBorrowSubmit);
      this.document.getElementById('circulation-return-form')?.removeEventListener?.('submit', this.boundReturnSubmit);
      this.bound = false;
    }
    return Promise.all([
      this.borrowerScanner?.destroy?.(),
      this.borrowScanner?.destroy?.(),
      this.returnScanner?.destroy?.(),
    ].filter(Boolean));
  }
}
