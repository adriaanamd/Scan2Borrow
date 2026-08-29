import { BulkBorrowCart } from "../../../../app/core/models/bulk-borrow-cart.js";

export class TeacherDashboardPage {
  constructor() {
    this.api = "/scan2borrow/api/teacher/dashboard";
    this.borrowForm = document.getElementById("borrowForm");
    this.returnForm = document.getElementById("returnForm");
    this.csrf = document.querySelector('meta[name="csrf"]')?.content || "";
    this.cart = new BulkBorrowCart();
    this.bindEvents();
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
    this.renderBarcode(user.barcode || "");
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
  }

  renderCart() {
    const host = document.getElementById("bulkBorrowItems"); if (!host) return;
    host.replaceChildren();
    this.cart.linesForDisplay().forEach((line) => {
      const row = document.createElement("div"); row.className = "d-flex justify-content-between align-items-center border rounded p-2 mb-2";
      row.innerHTML = `<div><strong>${this.escapeHtml(line.title)}</strong><div class="small text-muted">${this.escapeHtml(line.author)} · ${line.quantity} copy/copies</div></div><div class="btn-group btn-group-sm"><button type="button" data-cart-action="decrease" data-title-id="${line.title_id}" class="btn btn-outline-secondary">−</button><span class="btn btn-light">${line.quantity}</span><button type="button" data-cart-action="increase" data-title-id="${line.title_id}" class="btn btn-outline-secondary">+</button><button type="button" data-cart-action="remove" data-title-id="${line.title_id}" class="btn btn-outline-danger">×</button></div>`;
      host.appendChild(row);
    });
    const count = document.getElementById("bulkBorrowCount"); if (count) count.textContent = String(this.cart.totalQuantity());
  }

  lookupAndAdd(barcode) {
    if (!barcode) return;
    fetch(`/scan2borrow/api/teacher/borrow/lookup?barcode=${encodeURIComponent(barcode)}`, { headers: { "X-Requested-With": "fetch" } })
      .then((response) => response.json().then((payload) => ({ ok: response.ok && payload.ok, payload })))
      .then(({ ok, payload }) => { if (!ok) throw new Error(payload.message || "Book copy not found."); this.cart.addTitle(payload.data, 1, barcode); this.renderCart(); })
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
