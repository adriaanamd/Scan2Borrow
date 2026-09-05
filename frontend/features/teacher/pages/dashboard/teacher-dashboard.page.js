import { BulkBorrowCart } from "../../../../app/core/models/bulk-borrow-cart.js";
import { ApiClient } from "../../../../app/core/api/api-client.js";
import { ReservationService } from "../../../../app/core/services/reservation.service.js";
import { ReservationQueueComponent } from "../../../../app/shared/components/reservation-queue/reservation-queue.component.js";
import { RenewalService } from "../../../../app/core/services/renewal.service.js";
import { RenewalModalComponent } from "../../../../app/shared/components/renewal-modal/renewal-modal.component.js";
import { installTeacherBorrowModal } from "../../components/teacher-borrow-modal.js";
import { ActivityTimelineComponent } from "../../../../app/shared/components/activity-timeline/activity-timeline.component.js";

export class TeacherDashboardPage {
  constructor() {
    this.api = "/scan2borrow/api/teacher/dashboard";
    this.borrowForm = document.getElementById("borrowForm");
    this.returnForm = document.getElementById("returnForm");
    this.csrf = document.querySelector('meta[name="csrf"]')?.content || "";
    this.activityTimeline = new ActivityTimelineComponent(document.getElementById("recent-activity"), {
      classPrefix: "borrower-activity",
    });
    this.cart = new BulkBorrowCart();
    this.reservationQueue = new ReservationQueueComponent(document.getElementById("reservationQueue"), {
      service: new ReservationService({
        api: new ApiClient({ csrf: this.csrf, fetchImpl: window.fetch.bind(window) }),
        role: "teacher",
      }),
    });
    this.renewalService = new RenewalService({
      api: new ApiClient({ csrf: this.csrf, fetchImpl: window.fetch.bind(window) }),
      role: "teacher",
    });
    this.currentLoans = [];
    this.renewals = new Map();
    this.renewalModal = new RenewalModalComponent(document.getElementById("renewalModal"), {
      service: this.renewalService,
      contentClass: "teacher-dashboard__modal",
      headerClass: "teacher-dashboard__modal-header",
      onChanged: () => this.load(),
      onError: (error) => this.toast(error?.message || "Renewal request failed."),
    });
    installTeacherBorrowModal();
    this.bindEvents();
    this.reservationQueue.load();
    this.load();
  }
  escapeHtml(value) {
    return String(value == null ? "" : value).replace(
      /[&<>"']/g,
      (character) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[character],
    );
  }
  load() {
    fetch(this.api, { headers: { "X-Requested-With": "fetch" } })
      .then((response) => response.json())
      .then((response) => {
        if (!response.ok) throw new Error(response.message);
        this.render(response.data || {});
      })
      .catch((error) =>
        this.toast(error.message || "Unable to load your dashboard."),
      );
  }
  render(data) {
    const user = data.user || {};
    const stats = data.stats || {};
    document.getElementById("teacher-name").textContent = user.name || "";
    document.getElementById("current-user-name").textContent = user.name || "";
    document.getElementById("teacher-meta").textContent = [
      user.barcode,
      user.department ? ` · ${user.department}` : "",
      user.position ? ` · ${user.position}` : "",
    ].join("");
    document.getElementById("teacher-barcode").textContent = user.barcode || "";
    document.getElementById("active-count").textContent = String(
      stats.active || 0,
    );
    document.getElementById("overdue-count").textContent = String(
      stats.overdue || 0,
    );
    document.getElementById("fine-total").textContent =
      `₱${Number(stats.fines || 0).toFixed(2)}`;
    document.getElementById("on-time-rate").textContent =
      `${Number(stats.on_time_rate ?? 100)}%`;
    this.currentLoans = data.current_loans || [];
    this.renderRecentActivity(data.recent_activity || []);
    this.renewals = new Map();
    this.renderLoans(this.currentLoans);
    this.loadRenewals();
    this.renderBarcode(user.barcode || "");
  }

  renderRecentActivity(rows) {
    this.activityTimeline.render(
      Array.isArray(rows) ? rows.slice(0, 5) : [],
      { compact: true },
    );
  }

  async loadRenewals() {
    try {
      const response = await this.renewalService.list();
      this.renewals = new Map();
      (response?.data?.renewals || []).forEach((renewal) => {
        const loanId = String(renewal.loan_id);
        if (!this.renewals.has(loanId)) this.renewals.set(loanId, renewal);
      });
    } catch {
      this.renewals = new Map();
    }
    this.renderLoans(this.currentLoans);
  }

  renderLoans(loans) {
    const body = document.getElementById("current-loans");
    if (!body) return;
    body.replaceChildren();
    if (!loans.length) {
      body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">You have no active borrowed books.</td></tr>';
      return;
    }
    loans.forEach((loan) => {
      const row = document.createElement("tr");
      row.innerHTML = `<td>${this.escapeHtml(loan.title)}<br><span class="text-muted small">${this.escapeHtml(loan.author || "")}</span></td><td>${Number(loan.quantity || 1)}</td><td>${this.escapeHtml(loan.borrow_date || "")}</td><td>${this.escapeHtml(loan.due_date || "")}</td><td>${this.badge(loan.status || "")}</td><td><div class="borrower-dashboard__loan-actions"><a href="/scan2borrow/receipt?code=${encodeURIComponent(loan.transaction_code || "")}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">View receipt</a>${this.renewalAction(loan)}</div></td>`;
      body.appendChild(row);
    });
  }

  renewalAction(loan) {
    const loanId = loan.id ?? loan.loan_id ?? "";
    const renewal = this.renewals.get(String(loanId));
    const renewalStatus = String(renewal?.status || "").toLowerCase();
    if (renewal && ["pending", "approved"].includes(renewalStatus)) {
      return `<span class="borrower-dashboard__renewal-status">${this.escapeHtml(renewal.status_label || renewal.status || "Renewal requested")}</span>`;
    }

    const status = String(loan.status || "").toLowerCase();
    if (status === "borrowed") {
      return `<button type="button" class="btn btn-sm borrower-dashboard__renew-action" data-renewal-open data-loan-id="${this.escapeHtml(loanId)}">Renew</button>`;
    }
    if (status === "pending") {
      return '<span class="borrower-dashboard__renewal-status borrower-dashboard__renewal-status--muted">Awaiting approval</span>';
    }
    if (status === "overdue") {
      return '<span class="borrower-dashboard__renewal-status borrower-dashboard__renewal-status--muted">Resolve overdue balance</span>';
    }
    return '<span class="borrower-dashboard__renewal-status borrower-dashboard__renewal-status--muted">Renewal unavailable</span>';
  }
  badge(status) {
    const type =
      {
        Borrowed: "primary",
        Overdue: "danger",
        Pending: "warning text-dark",
        Returned: "success",
      }[status] || "secondary";
    return `<span class="badge bg-${type} borrower-dashboard__status">${this.escapeHtml(status)}</span>`;
  }
  renderBarcode(value) {
    if (value && window.JsBarcode)
      window.JsBarcode("#lib-barcode", value, {
        format: "CODE128",
        width: 2,
        height: 40,
      });
  }
  submit(form, action) {
    const body = new FormData(form);
    body.append("action", action);
    fetch("/scan2borrow/api/teacher/dashboard", { method: "POST", body })
      .then((response) => response.json())
      .then((response) => {
        if (!response.ok) throw new Error(response.message);
        window.location.reload();
      })
      .catch((error) => this.toast(error.message || "Request failed."));
  }
  toast(message) {
    const host = document.getElementById("toast-host");
    if (!host) return;
    const element = document.createElement("div");
    element.className = "toast show text-white bg-danger border-0";
    element.innerHTML = `<div class="toast-body">${this.escapeHtml(message)}</div>`;
    host.appendChild(element);
    window.setTimeout(() => element.remove(), 3500);
  }
  bindEvents() {
    this.borrowForm?.addEventListener("submit", (event) => {
      event.preventDefault();
      this.submitCart();
    });
    this.returnForm?.addEventListener("submit", (event) => {
      event.preventDefault();
      this.submit(this.returnForm, "return_unified");
    });
    window.addEventListener("load", () =>
      this.renderBarcode(
        document.getElementById("teacher-barcode").textContent,
      ),
    );
    document.getElementById("bulk-scan-add")?.addEventListener("click", () => {
      const input = document.getElementById("bulk-scan-barcode");
      this.lookupAndAdd(input.value.trim()); input.value = "";
    });
    document.getElementById("bulk-scan-barcode")?.addEventListener("keydown", (event) => {
      if (event.key === "Enter") { event.preventDefault(); document.getElementById("bulk-scan-add").click(); }
    });
    document.getElementById("bulkBorrowItems")?.addEventListener("click", (event) => {
      const button = event.target.closest("button[data-cart-action]"); if (!button) return;
      const id = Number(button.dataset.titleId);
      if (button.dataset.cartAction === "remove") this.cart.removeTitle(id);
      else this.cart.setQuantity(id, this.cart.lines.get(id).quantity + (button.dataset.cartAction === "increase" ? 1 : -1));
      this.renderCart();
    });
    document.getElementById("current-loans")?.addEventListener("click", (event) => {
      const button = event.target.closest?.("[data-renewal-open]");
      if (!button) return;
      const loan = this.currentLoans.find(
        (item) => String(item.id ?? item.loan_id ?? "") === button.dataset.loanId,
      );
      if (loan) this.renewalModal.open(loan);
    });
  }

  renderCart() {
    const host = document.getElementById("bulkBorrowItems"); if (!host) return;
    host.replaceChildren();
    this.cart.linesForDisplay().forEach((line) => {
      const row = document.createElement("div"); row.className = "d-flex justify-content-between align-items-center border rounded p-2 mb-2 teacher-borrow-cart-row";
      row.innerHTML = `<div><strong>${this.escapeHtml(line.title)}</strong><div class="small text-muted">${this.escapeHtml(line.author)} · ${line.quantity} copy/copies</div></div><div class="btn-group btn-group-sm"><button type="button" data-cart-action="decrease" data-title-id="${line.title_id}" class="btn btn-outline-secondary">−</button><span class="btn btn-light">${line.quantity}</span><button type="button" data-cart-action="increase" data-title-id="${line.title_id}" class="btn btn-outline-secondary">+</button><button type="button" data-cart-action="remove" data-title-id="${line.title_id}" class="btn btn-outline-danger">×</button></div>`;
      row.querySelector(".btn-group")?.classList.add("teacher-borrow-cart-actions");
      host.appendChild(row);
    });
    const count = document.getElementById("bulkBorrowCount"); if (count) { count.classList.add("teacher-borrow-cart-count"); count.textContent = String(this.cart.totalQuantity()); }
  }

  lookupAndAdd(barcode) {
    if (!barcode) return;
    fetch(`/scan2borrow/api/teacher/borrow/lookup?barcode=${encodeURIComponent(barcode)}`, { headers: { "X-Requested-With": "fetch" } })
      .then((response) => response.json().then((payload) => ({ ok: response.ok && payload.ok, payload })))
      .then(({ ok, payload }) => { if (!ok) throw new Error(payload.message || "Book copy not found."); const copy = payload.data; this.cart.addTitle(copy, 1, copy.status === "Available" ? barcode : ""); this.renderCart(); })
      .catch((error) => this.toast(error.message));
  }

  submitCart() {
    if (this.cart.totalQuantity() === 0) { this.toast("Add at least one book to your cart."); return; }
    const body = new FormData(); body.append("action", "borrow"); body.append("csrf", this.csrf);
    const dueDate = this.borrowForm.elements.due_date?.value || ""; if (dueDate) body.append("due_date", dueDate);
    this.cart.items().forEach((item, index) => { body.append(`items[${index}][title_id]`, String(item.title_id)); body.append(`items[${index}][quantity]`, String(item.quantity)); item.barcodes.forEach((barcode) => body.append(`items[${index}][barcodes][]`, barcode)); });
    fetch(this.api, { method: "POST", headers: { "X-Requested-With": "fetch" }, body })
      .then((response) => response.json().then((payload) => ({ ok: response.ok && payload.ok, payload })))
      .then(({ ok, payload }) => { if (!ok) throw new Error(payload.errors?.[0] || payload.message || "Borrow request failed."); this.cart.clear(); window.location.reload(); })
      .catch((error) => this.toast(error.message));
  }
}
if (typeof window !== "undefined") {
  window.addEventListener("DOMContentLoaded", () => new TeacherDashboardPage());
}
