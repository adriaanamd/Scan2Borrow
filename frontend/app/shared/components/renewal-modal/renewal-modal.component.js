export class RenewalModalComponent {
  constructor(
    root,
    {
      service,
      onChanged = () => {},
      onError = () => {},
      contentClass = "",
      headerClass = "",
    } = {},
  ) {
    this.root = root;
    this.service = service;
    this.onChanged = onChanged;
    this.onError = onError;
    this.contentClass = contentClass;
    this.headerClass = headerClass;
    this.instance = null;
    this.boundSubmit = (event) => this.handleSubmit(event);
    this.root?.addEventListener?.("submit", this.boundSubmit);
  }

  open(loan) {
    const loanId = loan?.id ?? loan?.loan_id ?? "";
    if (!this.root || !loanId) return;

    const title = this.escape(loan?.title || "this book");
    const dueDate = this.escape(loan?.due_date || "—");
    const safeLoanId = this.escape(loanId);
    const titleId = `renewal-modal-title-${safeLoanId}`;
    const reasonId = `renewal-reason-${safeLoanId}`;

    this.root.innerHTML = `
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content ${this.escape(this.contentClass)} renewal-modal">
          <form data-renewal-form>
            <div class="modal-header text-white ${this.escape(this.headerClass)}">
              <h5 class="modal-title" id="${titleId}">Renew book</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <p class="renewal-modal__loan"><strong>${title}</strong><span>Due ${dueDate}</span></p>
              <label class="form-label fw-semibold" for="${reasonId}">Reason (optional)</label>
              <textarea class="form-control" id="${reasonId}" name="reason" rows="4" maxlength="500" placeholder="Tell us why you need more time."></textarea>
              <div class="form-text">Requests add one standard seven-day period and require librarian approval.</div>
              <input type="hidden" name="loan_id" value="${safeLoanId}">
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
              <button type="submit" class="btn renewal-modal__submit" data-renewal-submit>Request +7 days</button>
            </div>
          </form>
        </div>
      </div>`;

    this.root.setAttribute?.("aria-labelledby", titleId);
    this.root.setAttribute?.("aria-hidden", "false");
    this.instance = globalThis.bootstrap?.Modal?.getOrCreateInstance?.(this.root) || null;
    this.instance?.show?.();
  }

  async handleSubmit(event) {
    const form = event.target?.closest?.("[data-renewal-form]");
    if (!form) return;
    event.preventDefault();

    const button = form.querySelector?.("[data-renewal-submit]");
    if (button) button.disabled = true;

    try {
      const loanId = form.elements?.loan_id?.value || "";
      const reason = form.elements?.reason?.value?.trim?.() || "";
      await this.service.request(loanId, reason);
      this.close();
      await this.onChanged();
    } catch (error) {
      if (button) button.disabled = false;
      this.onError(error?.message || "Unable to submit the renewal request.");
    }
  }

  close() {
    this.instance?.hide?.();
    this.root?.setAttribute?.("aria-hidden", "true");
  }

  escape(value) {
    return String(value ?? "").replace(/[&<>"']/g, (character) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
    })[character]);
  }

  destroy() {
    this.root?.removeEventListener?.("submit", this.boundSubmit);
  }
}
