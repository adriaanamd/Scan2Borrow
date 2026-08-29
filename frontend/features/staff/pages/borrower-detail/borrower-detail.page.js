export class BorrowerDetailPage {
  constructor(root = globalThis.document, { service, window = globalThis.window, document = globalThis.document } = {}) {
    this.root = root;
    this.service = service;
    this.window = window;
    this.document = document;
  }

  start() { return this.load(); }

  async load() {
    const id = new URLSearchParams(this.window.location.search).get('id') || '';
    const response = await this.service?.details(id);
    if (response) this.render(response.data || {});
    return response;
  }

  render(data) {
    const borrower = data.borrower || {};
    const avatar = this.document.getElementById('borrower-avatar');
    if (avatar) {
      const initials = `${(borrower.firstname || '')[0] || ''}${(borrower.lastname || '')[0] || ''}`.toUpperCase();
      avatar.textContent = initials || '--';
      if (borrower.photo) avatar.innerHTML = `<img src="${this.escape(this.media(borrower.photo))}" alt="ID photo" style="width:100%;height:100%;object-fit:cover;border-radius:inherit" />`;
    }
    this.text('borrower-name', borrower.name || 'Borrower Details');
    this.text('borrower-meta', `ID: ${borrower.barcode || ''} · ${(borrower.role || '').replace(/^./, (letter) => letter.toUpperCase())}${borrower.course ? ` · ${borrower.course}` : ''}${borrower.year_level ? ` · Year ${borrower.year_level}` : ''}`);
    this.text('borrower-contact', `${borrower.email || ''}${borrower.contact_no ? ` · ${borrower.contact_no}` : ''}`);
    this.text('borrower-status', borrower.status || '');
    this.text('borrower-active', data.summary?.active || 0);
    this.text('borrower-returned', data.summary?.returned || 0);
    this.text('borrower-overdue-count', data.summary?.overdue || 0);
    this.text('borrower-fines', `₱${Number(data.summary?.total_fine || 0).toFixed(2)}`);
    this.text('history-count', `(${(data.history || []).length} records)`);
    this.renderHistory(data.history || []);
    const overdue = this.document.getElementById('borrower-overdue');
    if (overdue) {
      overdue.textContent = `${data.summary?.overdue || 0} Overdue`;
      overdue.classList.toggle('d-none', !Number(data.summary?.overdue));
    }
    const notify = this.document.getElementById('notify-borrower');
    if (notify) notify.href = `/scan2borrow/staff/notify?id=${encodeURIComponent(borrower.id || new URLSearchParams(this.window.location.search).get('id') || '')}`;
    this.document.getElementById('change-photo')?.classList.toggle('d-none', !data.can_edit_photo);
  }

  renderHistory(rows) {
    const body = this.document.getElementById('borrower-history');
    if (!body) return;
    body.innerHTML = rows.length ? rows.map((row) => `<tr><td><code>${this.escape(row.transaction_code)}</code></td><td>${this.escape(row.title)}<br><span class="text-muted small">${this.escape(row.author || '')}</span></td><td>${Number(row.quantity || 1)}</td><td>${this.escape(this.date(row.borrow_date))}</td><td>${this.escape(this.date(row.due_date))}</td><td>${row.return_date ? this.escape(this.date(row.return_date)) : '<span class="text-muted">—</span>'}</td><td><span class="badge bg-secondary">${this.escape(row.status)}</span></td><td>${Number(row.fine_amount || 0) > 0 ? `₱${Number(row.fine_amount).toFixed(2)}` : '—'}</td></tr>`).join('') : '<tr><td colspan="8" class="text-center text-muted">No borrowing history found.</td></tr>';
  }

  media(value) { return globalThis.Scan2BorrowMedia?.resolve(value || '') || value || ''; }
  date(value) { return value ? String(value).slice(0, 10) : ''; }
  text(id, value) { const node = this.document.getElementById(id); if (node) node.textContent = value ?? ''; }
  escape(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character])); }
}
