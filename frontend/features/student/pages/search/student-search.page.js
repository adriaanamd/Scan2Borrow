import { BulkBorrowCart } from "../../../../app/core/models/bulk-borrow-cart.js";

export class StudentSearchPage {
  constructor() {
    this.api = "/scan2borrow/api/student/books";
    this.csrf = document.querySelector('meta[name="csrf"]')?.content || "";
    this.form = document.getElementById("searchForm");
    this.results = document.getElementById("book-results");
    this.params = new URLSearchParams(window.location.search);
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
    const query = this.params.toString();
    fetch(this.api + (query ? `?${query}` : ""), {
      headers: { "X-Requested-With": "fetch" },
    })
      .then((response) => response.json())
      .then((response) => {
        if (!response.ok)
          throw new Error(response.message || "Unable to load books.");
        this.render(response.data || {});
      })
      .catch((error) => {
        this.results.innerHTML = `<div class="alert alert-danger">${this.escapeHtml(error.message)}</div>`;
      });
  }

  render(data) {
    const books = data.books || [];
    const total = Number(data.total || books.length);
    this.form.elements.search.value = this.params.get("search") || "";
    this.setOptions(
      this.form.elements.category_name,
      data.categories || [],
      "All Categories",
    );
    this.setOptions(
      this.form.elements.floor,
      data.floors || [],
      "All Floors",
      "Floor ",
    );
    ["category_name", "status", "floor", "sort"].forEach((name) => {
      if (this.params.has(name))
        this.form.elements[name].value = this.params.get(name);
    });
    document.getElementById("book-count").textContent = String(total);
    document.getElementById("book-count-label").textContent =
      `book${total === 1 ? "" : "s"} found`;
    document.getElementById("trending-label").hidden =
      this.params.toString() !== "";
    document.getElementById("availability-label").innerHTML =
      this.params.get("status") === "Available"
        ? '<span class="badge bg-success">&#10003; Available to Borrow</span>'
        : "";
    this.renderActiveFilters();
    this.results.replaceChildren();
    if (!books.length) {
      this.results.innerHTML =
        '<div class="text-center py-5"><div style="font-size:48px;margin-bottom:12px;">&#128233;</div><strong>No books found</strong><p class="text-muted small">Try adjusting your search or filters.</p></div>';
      return;
    }
    const grid = document.createElement("div");
    grid.className = "row g-4";
    books.forEach((book) => grid.appendChild(this.bookCard(book)));
    this.results.appendChild(grid);
  }

  setOptions(select, values, first, prefix = "") {
    const current = select.value;
    select.replaceChildren(new Option(first, ""));
    values.forEach((value) =>
      select.appendChild(new Option(prefix + value, value)),
    );
    if (current) select.value = current;
  }

  renderActiveFilters() {
    const host = document.getElementById("active-filters");
    host.replaceChildren();
    const labels = {
      search: "🔍 ",
      category_name: "📚 ",
      status: "",
      floor: "🏢 Floor ",
    };
    Object.keys(labels).forEach((name) => {
      const value = this.params.get(name);
      if (!value) return;
      const tag = document.createElement("span");
      tag.className = `badge ${name === "search" ? "bg-primary" : name === "category_name" ? "bg-info" : name === "status" ? "bg-secondary" : "bg-warning text-dark"}`;
      tag.textContent = labels[name] + value;
      host.appendChild(tag);
    });
  }

  bookCard(book) {
    const column = document.createElement("div");
    column.className = "col-xl-4 col-lg-6 col-md-6";
    const cover = Scan2BorrowMedia.resolve(
      book.cover_file || book.cover_image || "",
    );
    const title = this.escapeHtml(book.title || "");
    const author = this.escapeHtml(book.author || "Unknown Author");
    const status = this.escapeHtml(book.status || "");
    const totalQuantity = Number(book.quantity ?? 1);
    const availableQuantity = Number(book.available_quantity ?? (book.status === "Available" ? 1 : 0));
    const available = Number(book.available_quantity ?? (book.status === "Available" ? 1 : 0)) > 0;
    const borrowed = Boolean(book.already_borrowed);
    const coverMarkup = cover
      ? `<img src="${this.escapeHtml(cover)}" alt="${title}" class="book-cover-img">`
      : "";
    const action = borrowed
      ? '<span class="badge bg-info w-100 py-2">&#128214; You have this</span>'
      : available
        ? `<button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#borrowModal" data-title-id="${this.escapeHtml(book.title_id ?? book.id)}" data-title="${title}" data-author="${author}" data-available-quantity="${this.escapeHtml(book.available_quantity ?? 1)}" data-book-barcode="${this.escapeHtml(book.barcode || "")}" title="Add this title">Add to Borrow Cart</button>`
        : '<button class="btn btn-outline-secondary w-100" disabled>Unavailable</button>';
    column.innerHTML = `<div class="book-card-shell"><div class="book-card"><div class="book-face book-face-front"><div class="book-cover${cover ? "" : " book-cover-fallback"}">${coverMarkup}<div class="book-cover-content"><span class="badge bg-light text-dark mb-3">${this.escapeHtml(book.category_name || "Library")}</span><h4 class="fw-bold text-white mb-2">${title}</h4><p class="text-white-50 small mb-0">${author}</p></div></div></div><div class="book-face book-face-back"><div class="book-back-content"><div class="d-flex justify-content-between align-items-start mb-3"><div><h5 class="fw-bold mb-1">${title}</h5><p class="text-muted small mb-0">${author}</p></div>${this.badge(status)}</div><p class="text-muted small mb-3">${this.escapeHtml(book.description || "No description available")}</p><div class="small text-muted mb-3"><div><strong>Publisher:</strong> ${this.escapeHtml(book.publisher || "N/A")}</div><div><strong>Location:</strong> Floor ${this.escapeHtml(book.floor_no)} · Shelf ${this.escapeHtml(book.shelf_no)} · Row ${this.escapeHtml(book.row_no)}</div></div>${action}</div></div></div></div>`;
    const details = column.querySelector(".book-back-content .small.text-muted.mb-3");
    details?.insertAdjacentHTML("afterbegin", `<div><strong>Copies:</strong> ${totalQuantity} total · ${availableQuantity} available</div>`);
    const image = column.querySelector("img");
    image?.addEventListener("error", () => {
      image.style.display = "none";
      image.closest(".book-cover").classList.add("book-cover-fallback");
    });
    return column;
  }

  badge(status) {
    const type =
      { Available: "success", Borrowed: "danger", Overdue: "danger" }[status] ||
      "secondary";
    return `<span class="badge bg-${type}">${status}</span>`;
  }

  bindEvents() {
    this.form.addEventListener("submit", (event) => {
      event.preventDefault();
      window.location.href =
        this.form.action +
        "?" +
        new URLSearchParams(new FormData(this.form)).toString();
    });
    ["category_name", "status", "floor", "sort"].forEach((name) =>
      this.form.elements[name].addEventListener("change", () =>
        this.form.requestSubmit(),
      ),
    );
    const modal = document.getElementById("borrowModal");
    modal.addEventListener("show.bs.modal", (event) => {
      const button = event.relatedTarget;
      if (button?.dataset.titleId) {
        this.cart.addTitle(
          { title_id: Number(button.dataset.titleId), title: button.dataset.title, author: button.dataset.author, available_quantity: Number(button.dataset.availableQuantity || 1) },
          1,
          button.dataset.bookBarcode || "",
        );
        this.renderCart();
      }
      document.getElementById("borrow-error").hidden = true;
    });
    modal.addEventListener("hidden.bs.modal", () =>
      document.getElementById("borrowFormModal").reset(),
    );
    document
      .getElementById("borrowFormModal")
      .addEventListener("submit", (event) => {
        event.preventDefault();
        this.submitCart();
      });
    document.getElementById("bulk-scan-add")?.addEventListener("click", () => {
      const input = document.getElementById("bulk-scan-barcode");
      this.lookupAndAdd(input.value.trim());
      input.value = "";
    });
    document.getElementById("bulk-scan-barcode")?.addEventListener("keydown", (event) => {
      if (event.key === "Enter") { event.preventDefault(); document.getElementById("bulk-scan-add").click(); }
    });
    document.getElementById("bulkBorrowItems")?.addEventListener("click", (event) => {
      const button = event.target.closest("button[data-cart-action]");
      if (!button) return;
      const id = Number(button.dataset.titleId);
      if (button.dataset.cartAction === "remove") this.cart.removeTitle(id);
      else this.cart.setQuantity(id, this.cart.lines.get(id).quantity + (button.dataset.cartAction === "increase" ? 1 : -1));
      this.renderCart();
    });
  }

  renderCart() {
    const host = document.getElementById("bulkBorrowItems");
    if (!host) return;
    host.replaceChildren();
    this.cart.linesForDisplay().forEach((line) => {
      const row = document.createElement("div");
      row.className = "d-flex justify-content-between align-items-center border rounded p-2 mb-2";
      row.innerHTML = `<div><strong>${this.escapeHtml(line.title)}</strong><div class="small text-muted">${this.escapeHtml(line.author)} · ${line.quantity} available copies requested</div></div><div class="btn-group btn-group-sm"><button type="button" data-cart-action="decrease" data-title-id="${line.title_id}" class="btn btn-outline-secondary">−</button><span class="btn btn-light">${line.quantity}</span><button type="button" data-cart-action="increase" data-title-id="${line.title_id}" class="btn btn-outline-secondary">+</button><button type="button" data-cart-action="remove" data-title-id="${line.title_id}" class="btn btn-outline-danger">×</button></div>`;
      host.appendChild(row);
    });
    const count = document.getElementById("bulkBorrowCount");
    if (count) count.textContent = String(this.cart.totalQuantity());
  }

  lookupAndAdd(barcode) {
    if (!barcode) return;
    fetch(`/scan2borrow/api/student/borrow/lookup?barcode=${encodeURIComponent(barcode)}`, { headers: { "X-Requested-With": "fetch" } })
      .then((response) => response.json().then((payload) => ({ ok: response.ok && payload.ok, payload })))
      .then(({ ok, payload }) => { if (!ok) throw new Error(payload.message || "Book copy not found."); this.cart.addTitle(payload.data, 1, barcode); this.renderCart(); })
      .catch((error) => { const host = document.getElementById("borrow-error"); host.textContent = error.message; host.hidden = false; });
  }

  submitCart() {
    if (this.cart.totalQuantity() === 0) { const host = document.getElementById("borrow-error"); host.textContent = "Add at least one book to your cart."; host.hidden = false; return; }
    const body = new FormData(); body.append("action", "borrow"); body.append("csrf", this.csrf);
    this.cart.items().forEach((item, index) => { body.append(`items[${index}][title_id]`, String(item.title_id)); body.append(`items[${index}][quantity]`, String(item.quantity)); item.barcodes.forEach((barcode) => body.append(`items[${index}][barcodes][]`, barcode)); });
    fetch("/scan2borrow/api/student/borrow", { method: "POST", headers: { "X-Requested-With": "fetch" }, body })
      .then((response) => response.json().then((payload) => ({ ok: response.ok && payload.ok, payload })))
      .then(({ ok, payload }) => { if (!ok) throw new Error(payload.errors?.[0] || payload.message || "Borrow request failed."); this.cart.clear(); window.location.href = "/scan2borrow/student/dashboard"; })
      .catch((error) => { const host = document.getElementById("borrow-error"); host.textContent = error.message; host.hidden = false; });
  }
}

if (typeof window !== "undefined") {
  window.addEventListener("DOMContentLoaded", () => new StudentSearchPage());
}
