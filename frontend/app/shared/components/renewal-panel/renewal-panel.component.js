export class RenewalPanelComponent {
  constructor(root, { service, onChanged = () => {} } = {}) {
    this.root = root;
    this.service = service;
    this.onChanged = onChanged;
    this.boundSubmit = (event) => this.handleSubmit(event);
    this.root?.addEventListener?.('submit', this.boundSubmit);
  }

  async load(loans = []) {
    try {
      const response = await this.service.list();
      this.render(loans, response?.data?.renewals || []);
      return response;
    } catch {
      this.render(loans, []);
      return null;
    }
  }

  render(loans, renewals) {
    const activeLoans = loans.filter((loan) => loan.id || loan.loan_id);
    if (!activeLoans.length) {
      this.root.innerHTML = '';
      return;
    }
    const byLoan = new Map(renewals.map((renewal) => [String(renewal.loan_id), renewal]));
    const rows = activeLoans.map((loan) => {
      const loanId = loan.id || loan.loan_id;
      const renewal = byLoan.get(String(loanId));
      const state = renewal ? `<span class="renewal-panel__status">${this.escape(renewal.status_label || renewal.status)}</span>` : `<form class="renewal-panel__form" data-renewal-form><input type="hidden" name="loan_id" value="${this.escape(loanId)}"><input name="reason" maxlength="500" placeholder="Reason (optional)" aria-label="Reason for ${this.escape(loan.title)}"><button type="submit" class="renewal-panel__button">Request +7 days</button></form>`;
      return `<div class="renewal-panel__row" data-loan-id="${this.escape(loanId)}"><div class="renewal-panel__loan"><strong class="renewal-panel__title">${this.escape(loan.title)}</strong><span class="renewal-panel__due">Due ${this.escape(loan.due_date || '—')}</span></div><div class="renewal-panel__actions">${state}</div></div>`;
    }).join('');
    this.root.innerHTML = `<section class="renewal-panel"><div class="renewal-panel__header"><h2>Renewals</h2><p>Requests add one standard period and require librarian approval.</p></div>${rows}</section>`;
  }

  async handleSubmit(event) {
    const form = event.target?.closest?.('[data-renewal-form]');
    if (!form) return;
    event.preventDefault();
    const button = form.querySelector('button');
    if (button) button.disabled = true;
    try {
      await this.service.request(form.elements.loan_id.value, form.elements.reason.value);
      await this.load([]);
      this.onChanged();
    } catch {
      if (button) button.disabled = false;
    }
  }

  escape(value) { return String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]); }
  destroy() { this.root?.removeEventListener?.('submit', this.boundSubmit); }
}
