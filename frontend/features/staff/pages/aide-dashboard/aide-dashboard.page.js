import { normalizeStaffDashboard } from '../../models/dashboard.model.js';

export class AideDashboardPage {
  constructor(root = globalThis.document, { dashboardService, notificationService, document = root, window = globalThis.window } = {}) {
    this.root = root;
    this.dashboardService = dashboardService;
    this.notificationService = notificationService;
    this.document = document;
    this.window = window;
    this.dashboard = normalizeStaffDashboard();
    this.boundClick = (event) => this.onClick(event);
    this.bound = false;
  }

  start() {
    if (!this.bound) {
      this.root.addEventListener?.('click', this.boundClick);
      this.bound = true;
    }
    this.renderStats(this.dashboard.stats);
    this.renderActivity([]);
    const loaded = this.load();
    this.dashboardService?.startPolling?.((response) => this.renderActivity(this.activityRows(response)));
    this.notificationService?.start?.((response) => this.renderActivity(this.activityRows(response)));
    return loaded;
  }

  async load() {
    try {
      const response = await this.dashboardService.load();
      this.dashboard = normalizeStaffDashboard(response?.data || response || {});
      this.renderStats(this.dashboard.stats);
      this.renderActivity(this.dashboard.pending);
      this.hideError();
      const notifications = await this.notificationService.load();
      const rows = this.activityRows(notifications);
      if (Array.isArray(rows) && rows.length > 0) this.renderActivity(rows);
    } catch (error) {
      this.showError(error?.message || 'Desk activity is temporarily unavailable.');
      this.renderActivity([]);
    }

    return this.dashboard;
  }

  renderStats(stats = {}) {
    const values = {
      'available-copies': stats.available_books,
      'pending-requests': stats.pending_approvals,
      'active-borrowers': stats.borrowers,
      'overdue-items': stats.overdue_loans,
    };
    Object.entries(values).forEach(([key, value]) => {
      const node = this.root.querySelector?.(`[data-aide-stat="${key}"]`);
      if (node) node.textContent = this.displayNumber(value);
    });
  }

  renderActivity(rows = []) {
    const mount = this.root.querySelector?.('[data-aide-activity]');
    if (!mount) return;
    if (!Array.isArray(rows) || rows.length === 0) {
      mount.innerHTML = '<p class="aide-state">No pending activity.</p>';
      return;
    }
    mount.innerHTML = rows.slice(0, 6).map((row) => {
      const title = this.escape(row.borrower || row.user_name || row.title || row.message || 'Pending request');
      const meta = this.escape(row.book_title || row.created_at || row.status || 'Awaiting desk action');
      const tag = this.escape(row.request_type || row.type || 'Pending');
      return `<div class="aide-activity__row"><div><div class="aide-activity__title">${title}</div><div class="aide-activity__meta">${meta}</div></div><span class="aide-activity__tag">${tag}</span></div>`;
    }).join('');
  }

  onClick(event) {
    const next = event.target?.closest?.('[data-aide-scan-next]');
    if (!next) return;
    const borrower = this.root.querySelector?.('#aide-borrower-barcode')?.value?.trim() || '';
    const copies = (this.root.querySelector?.('#aide-copy-barcodes')?.value || '')
      .split(/\r?\n|,/)
      .map((value) => value.trim())
      .filter(Boolean);
    const status = this.root.querySelector?.('[data-aide-scan-status]');
    if (!borrower) {
      if (status) status.textContent = 'Scan or enter a borrower ID first.';
      return;
    }
    this.root.querySelectorAll?.('[data-aide-scan-step]').forEach((step) => step.classList.toggle('is-active', step.dataset.aideScanStep === 'copies'));
    this.renderScanReview(copies);
    if (status) status.textContent = 'Borrower staged. Add copy barcodes or continue at the circulation desk.';
  }

  activityRows(response = {}) {
    const data = response?.data || response || {};
    if (Array.isArray(response?.notifications)) return response.notifications;
    if (Array.isArray(data.notifications)) return data.notifications;
    if (Array.isArray(data.pending)) return data.pending;
    return [];
  }

  renderScanReview(copies = []) {
    const mount = this.root.querySelector?.('[data-aide-scan-review]');
    if (!mount) return;
    const items = copies.length === 0
      ? '<span class="aide-scan-review__empty">No copies staged yet.</span>'
      : copies.map((copy) => `<span class="aide-scan-review__item">${this.escape(copy)}</span>`).join('');
    mount.innerHTML = `<span class="aide-scan-review__label">Review list</span>${items}`;
  }

  showError(message) {
    const node = this.root.querySelector?.('[data-aide-error]');
    if (node) {
      node.hidden = false;
      node.textContent = message;
    }
  }

  hideError() {
    const node = this.root.querySelector?.('[data-aide-error]');
    if (node) node.hidden = true;
  }

  displayNumber(value) {
    const number = Number(value);
    return Number.isFinite(number) ? String(number) : '0';
  }

  escape(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[character]));
  }

  destroy() {
    this.root.removeEventListener?.('click', this.boundClick);
    this.bound = false;
    this.dashboardService?.stopPolling?.();
    this.notificationService?.stop?.();
  }
}
