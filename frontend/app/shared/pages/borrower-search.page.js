import { BulkBorrowCart } from "../../core/models/bulk-borrow-cart.js";
import { ApiClient } from "../../core/api/api-client.js";
import { ReservationService } from "../../core/services/reservation.service.js";
import { ToastService } from "../../core/services/toast.service.js";

export class BorrowerSearchPage {
  constructor({ api, recommendationApi, searchHistoryApi, lookupApi, borrowApi, dashboardPath, formAction, classPrefix, copy, role, reservationService, toastService, confirmation }) {
    this.api = api;
    this.recommendationApi = recommendationApi;
    this.searchHistoryApi = searchHistoryApi;
    this.lookupApi = lookupApi;
    this.borrowApi = borrowApi;
    this.dashboardPath = dashboardPath;
    this.formAction = formAction;
    this.classPrefix = classPrefix;
    this.copy = copy;
    this.csrf = document.querySelector('meta[name="csrf"]')?.content || "";
    this.role = role === "teacher" ? "teacher" : "student";
    this.reservationService = reservationService || new ReservationService({
      api: new ApiClient({ csrf: this.csrf, fetchImpl: window.fetch.bind(window) }),
      role: this.role,
    });
    this.toastService = toastService || new ToastService({ document });
    this.confirmation = confirmation || window.Scan2BorrowConfirmation;
    this.waitlistedTitleIds = new Set();
    this.form = document.getElementById("searchForm");
    this.results = document.getElementById("book-results");
    this.recommendationPanel = document.getElementById("recommendation-panel");
    this.recommendationResults = document.getElementById("book-recommendations");
    this.showAllBooksButton = document.getElementById("show-all-books");
    this.allBooksPanel = document.getElementById("all-books-panel");
    this.catalogRange = document.getElementById("catalog-range");
    this.pagination = document.getElementById("book-pagination");
    this.pageSize = 10;
    this.recommendationSize = 5;
    this.catalogPage = 1;
    this.catalogTotal = 0;
    this.catalogRequestId = 0;
    this.params = new URLSearchParams(window.location.search);
    this.cart = new BulkBorrowCart();
    this.applyCopy();
    this.bindEvents();
    this.load();
  }

  applyCopy() {
    document.title = `${this.copy.topbar} | Scan2Borrow`;
    document.querySelector('[data-role-copy="catalog-topbar"]')?.replaceChildren(document.createTextNode(this.copy.topbar));
    document.querySelector('[data-role-copy="catalog-eyebrow"]')?.replaceChildren(document.createTextNode(this.copy.eyebrow));
    document.querySelector('[data-role-copy="catalog-title"]')?.replaceChildren(document.createTextNode(this.copy.title));
    document.querySelector('[data-role-copy="catalog-description"]')?.replaceChildren(document.createTextNode(this.copy.description));
    document.getElementById("current-user-role")?.replaceChildren(document.createTextNode(this.copy.role));
    if (this.form) this.form.action = this.formAction;
    const clear = document.getElementById("search-clear");
    if (clear) clear.href = this.formAction;
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

  hasCatalogQuery() {
    return ["search", "category_name", "status", "floor", "sort"]
      .some((name) => (this.params.get(name) || "") !== "");
  }

  catalogQuery(page = 1) {
    const query = new URLSearchParams(this.params);
    query.set("page", String(Math.max(1, Number(page) || 1)));
    query.set("per_page", String(this.pageSize || 10));
    return query;
  }

  rangeLabel(total, page) {
    const count = Math.max(0, Number(total) || 0);
    if (count === 0) return "0-0 of 0";
    const currentPage = Math.max(1, Number(page) || 1);
    const start = ((currentPage - 1) * (this.pageSize || 10)) + 1;
    const end = Math.min(currentPage * (this.pageSize || 10), count);
    return `${start}-${end} of ${count}`;
  }

  paginationState(total, page) {
    const count = Math.max(0, Number(total) || 0);
    const pages = Math.max(1, Math.ceil(count / (this.pageSize || 10)));
    const currentPage = Math.min(Math.max(1, Number(page) || 1), pages);
    return {
      page: currentPage,
      pages,
      previous: currentPage > 1,
      next: currentPage < pages,
    };
  }

  activeWaitlistTitleIds(holds) {
    const activeStatuses = new Set(["queued", "offered", "claimed"]);
    return new Set(
      (Array.isArray(holds) ? holds : [])
        .filter((hold) => activeStatuses.has(hold?.status))
        .map((hold) => Number(hold?.title_id || 0))
        .filter((titleId) => Number.isInteger(titleId) && titleId > 0),
    );
  }

  loadWaitlist() {
    return this.reservationService.list()
      .then((response) => {
        this.waitlistedTitleIds = this.activeWaitlistTitleIds(response?.data?.holds || []);
      })
      .catch(() => {
        this.waitlistedTitleIds = new Set();
      });
  }

  load() {
    return this.loadWaitlist().then(() => {
      const filtered = this.hasCatalogQuery();
      this.setAllBooksVisible(filtered);
      this.recommendationPanel.hidden = filtered;
      if (filtered) {
        this.loadCatalog(Number(this.params.get("page") || 1));
        return;
      }
      this.renderRecommendationsLoading();
      this.loadRecommendations();
    });
  }

  loadRecommendations() {
    fetch(this.recommendationApi, { headers: { "X-Requested-With": "fetch" } })
      .then((response) => response.json().then((payload) => ({ ok: response.ok && payload.ok, payload })))
      .then(({ ok, payload }) => {
        if (!ok) throw new Error(payload.message || "Unable to load recommendations.");
        const data = payload.data || {};
        this.renderRecommendationCopy(Boolean(data.personalized));
        this.renderRecommendations(data.books || []);
      })
      .catch(() => this.renderRecommendationsError());
  }

  renderRecommendationCopy(personalized) {
    const host = document.getElementById("recommendation-supporting-copy");
    if (!host) return;
    host.textContent = this.recommendationCopy(personalized);
  }

  recommendationCopy(personalized) {
    return personalized ? "Based on your searches." : "Newly added available books.";
  }

  recordSearch(search) {
    const normalized = String(search || "").trim().replace(/\s+/g, " ");
    if (!normalized || !this.searchHistoryApi) return Promise.resolve();
    const body = new URLSearchParams({ search: normalized, csrf: this.csrf });
    return fetch(this.searchHistoryApi, {
      method: "POST",
      body,
      headers: {
        "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
        Accept: "application/json",
        "X-Requested-With": "fetch",
      },
      credentials: "same-origin",
    })
      .then((response) => response.json().then((payload) => ({ ok: response.ok && payload.ok, payload })))
      .then(({ ok, payload }) => {
        if (!ok) throw new Error(payload.message || payload.errors?.[0] || "Unable to record search.");
      })
      .catch(() => undefined);
  }

  loadCatalog(page = 1) {
    const requestId = ++this.catalogRequestId;
    this.catalogPage = Math.max(1, Number(page) || 1);
    this.setAllBooksVisible(true);
    this.renderCatalogLoading();
    const query = this.catalogQuery(this.catalogPage).toString();
    fetch(`${this.api}?${query}`, { headers: { "X-Requested-With": "fetch" } })
      .then((response) => response.json().then((payload) => ({ ok: response.ok && payload.ok, payload })))
      .then(({ ok, payload }) => {
        if (!ok) throw new Error(payload.message || "Unable to load books.");
        if (requestId !== this.catalogRequestId) return;
        const data = payload.data || {};
        const total = Number(data.total || 0);
        const lastPage = Math.max(1, Math.ceil(total / this.pageSize));
        if (total > 0 && this.catalogPage > lastPage) {
          this.loadCatalog(lastPage);
          return;
        }
        this.render(data);
      })
      .catch((error) => {
        if (requestId === this.catalogRequestId) this.renderCatalogError(error.message);
      });
  }

  renderRecommendationsLoading() {
    this.recommendationResults.innerHTML = `<div class="${this.classPrefix}-library-state"><strong>Loading recommendations...</strong></div>`;
  }

  renderRecommendationsError() {
    this.recommendationResults.innerHTML = `<div class="${this.classPrefix}-library-state ${this.classPrefix}-library-state--error"><strong>Recommendations are unavailable right now.</strong></div>`;
  }

  renderRecommendations(books) {
    this.recommendationResults.replaceChildren();
    if (!books.length) {
      this.recommendationResults.innerHTML = `<div class="${this.classPrefix}-library-state"><strong>No recommended books are available right now.</strong></div>`;
      return;
    }
    const grid = document.createElement("div");
    grid.className = `row g-4 ${this.classPrefix}-recommended-grid`;
    books.slice(0, this.recommendationSize).forEach((book) => {
      const card = this.bookCard(book);
      card.classList.add(`${this.classPrefix}-recommended-card`);
      grid.appendChild(card);
    });
    this.recommendationResults.appendChild(grid);
  }

  renderCatalogLoading() {
    this.catalogRange.textContent = "Loading...";
    this.pagination.replaceChildren();
    this.results.innerHTML = `<div class="${this.classPrefix}-library-state"><strong>Loading books...</strong></div>`;
  }

  renderCatalogError(message) {
    this.catalogRange.textContent = "0-0 of 0";
    this.pagination.replaceChildren();
    this.results.innerHTML = `<div class="${this.classPrefix}-library-state ${this.classPrefix}-library-state--error"><strong>We couldn't load the catalog</strong><p class="text-muted small mb-0">${this.escapeHtml(message)}</p></div>`;
  }

  renderCatalog(data) {
    const books = data.books || [];
    const total = Number(data.total || books.length);
    const state = this.paginationState(total, this.catalogPage);
    this.catalogTotal = total;
    this.catalogRange.textContent = this.rangeLabel(total, state.page);
    this.renderActiveFilters();
    this.results.replaceChildren();
    if (!books.length) {
      this.results.innerHTML = `<div class="${this.classPrefix}-library-state"><div class="${this.classPrefix}-library-state__icon" aria-hidden="true">&#128233;</div><strong>No books found</strong><p class="text-muted small mb-0">Try adjusting your search or filters.</p></div>`;
      this.renderPagination(state);
      return;
    }
    const grid = document.createElement("div");
    grid.className = "row g-4";
    books.forEach((book) => grid.appendChild(this.bookCard(book)));
    this.results.appendChild(grid);
    this.renderPagination(state);
  }

  renderPagination(state) {
    this.pagination.replaceChildren();
    if (state.pages <= 1) return;
    this.pagination.innerHTML = `<div class="borrower-catalog__pagination-controls"><button type="button" class="btn btn-outline-secondary btn-sm" data-catalog-page="${state.page - 1}" ${state.previous ? "" : "disabled"}>Previous</button><span aria-live="polite">Page ${state.page} of ${state.pages}</span><button type="button" class="btn btn-outline-secondary btn-sm" data-catalog-page="${state.page + 1}" ${state.next ? "" : "disabled"}>Next</button></div>`;
  }

  setAllBooksVisible(visible) {
    this.allBooksPanel.hidden = !visible;
    this.showAllBooksButton.setAttribute("aria-expanded", String(visible));
    this.showAllBooksButton.textContent = visible ? "Hide all books" : "Show all books";
  }

  render(data) {
    const books = data.books || [];
    const total = Number(data.total || books.length);
    this.form.elements.search.value = this.params.get("search") || "";
    this.setOptions(this.form.elements.category_name, data.categories || [], "All Categories");
    this.setOptions(this.form.elements.floor, data.floors || [], "All Floors", "Floor ");
    ["category_name", "status", "floor", "sort"].forEach((name) => {
      if (this.params.has(name)) this.form.elements[name].value = this.params.get(name);
    });
    document.getElementById("book-count").textContent = String(total);
    document.getElementById("book-count-label").textContent = `book${total === 1 ? "" : "s"} found`;
    document.getElementById("trending-label").hidden = this.params.toString() !== "";
    document.getElementById("availability-label").innerHTML =
      this.params.get("status") === "Available"
        ? '<span class="badge bg-success">&#10003; Available to Borrow</span>'
        : "";
    this.renderActiveFilters();
    this.catalogTotal = Number(data.total || 0);
    this.renderCatalog(data);
  }

  setOptions(select, values, first, prefix = "") {
    const current = select.value;
    select.replaceChildren(new Option(first, ""));
    values.forEach((value) => select.appendChild(new Option(prefix + value, value)));
    if (current) select.value = current;
  }

  renderActiveFilters() {
    const host = document.getElementById("active-filters");
    host.replaceChildren();
    const labels = { search: "", category_name: "", status: "", floor: "Floor " };
    Object.keys(labels).forEach((name) => {
      const value = this.params.get(name);
      if (!value) return;
      const tag = document.createElement("span");
      tag.className = `badge ${this.classPrefix}-search-filter-chip ${name === "search" ? "bg-primary" : name === "category_name" ? "bg-info" : name === "status" ? "bg-secondary" : "bg-warning text-dark"}`;
      tag.textContent = labels[name] + value;
      host.appendChild(tag);
    });
  }

  bookAction(book) {
    const availableQuantity = Number(book.available_quantity ?? (book.status === "Available" ? 1 : 0));
    const borrowed = Boolean(book.already_borrowed);
    return borrowed
      ? '<span class="badge bg-info w-100 py-2">&#128214; You have this</span>'
      : availableQuantity > 0
      ? `<button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#borrowModal" data-title-id="${this.escapeHtml(book.title_id ?? book.id)}" data-title="${this.escapeHtml(book.title || "")}" data-author="${this.escapeHtml(book.author || "Unknown Author")}" data-available-quantity="${this.escapeHtml(book.available_quantity ?? 1)}" data-book-barcode="${this.escapeHtml(book.barcode || "")}" title="Add this title">Add to Borrow Cart</button>`
        : this.waitlistAction(book);
  }

  waitlistTitleId(book) {
    const titleId = Number(book?.title_id ?? book?.id ?? 0);
    return Number.isInteger(titleId) && titleId > 0 ? titleId : 0;
  }

  waitlistAction(book) {
    const titleId = this.waitlistTitleId(book);
    if (this.waitlistedTitleIds.has(titleId)) {
      return '<button type="button" class="btn btn-outline-secondary w-100" disabled>On waitlist</button>';
    }
    if (!titleId) {
      return '<button type="button" class="btn btn-outline-secondary w-100" disabled>Waitlist unavailable</button>';
    }
    return `<button type="button" class="btn btn-outline-primary w-100" data-waitlist-title-id="${this.escapeHtml(titleId)}" data-waitlist-title="${this.escapeHtml(book.title || "")}">Join waitlist</button>`;
  }

  markWaitlisted(button) {
    button.disabled = true;
    button.textContent = "On waitlist";
    button.classList?.remove("btn-outline-primary");
    button.classList?.add("btn-outline-secondary");
  }

  async confirmWaitlist(button) {
    const titleId = Number(button.dataset.waitlistTitleId || 0);
    if (!titleId || this.waitlistedTitleIds.has(titleId)) return false;

    try {
      return await this.confirmation.confirm({
        title: "Join waitlist",
        message: `Join the waitlist for "${button.dataset.waitlistTitle || "this book"}"?`,
        confirmLabel: "Join waitlist",
        confirmClass: this.role === "teacher" ? "btn-accent" : "btn-primary",
        trigger: button,
        onConfirm: async () => {
          button.disabled = true;
          button.textContent = "Joining…";
          try {
            const response = await this.reservationService.join(titleId);
            this.waitlistedTitleIds.add(titleId);
            this.markWaitlisted(button);
            this.notify(response?.data?.message || "You joined the waitlist.", "success");
          } catch (error) {
            button.disabled = false;
            button.textContent = "Join waitlist";
            this.notify(error?.message || "Unable to join the waitlist.", "danger");
            throw error;
          }
        },
      });
    } catch {
      return false;
    }
  }

  notify(message, type = "info") {
    const toast = this.toastService.show(message, type);
    toast?.classList?.add("show");
    if (toast && typeof window.setTimeout === "function") {
      window.setTimeout(() => toast.remove(), 3500);
    }
  }

  handleWaitlistClick(event) {
    const button = event.target.closest?.("[data-waitlist-title-id]");
    if (!button) return;
    this.confirmWaitlist(button).catch(() => {});
  }

  bookCard(book) {
    const column = document.createElement("div");
    column.className = `col-xl-4 col-lg-6 col-md-6 ${this.classPrefix}-search-result`;
    const cover = Scan2BorrowMedia.resolve(book.cover_file || book.cover_image || "");
    const title = this.escapeHtml(book.title || "");
    const author = this.escapeHtml(book.author || "Unknown Author");
    const status = this.escapeHtml(book.status || "");
    const totalQuantity = Number(book.quantity ?? 1);
    const availableQuantity = Number(book.available_quantity ?? (book.status === "Available" ? 1 : 0));
    const coverMarkup = cover ? `<img src="${this.escapeHtml(cover)}" alt="${title}" class="book-cover-img">` : "";
    const action = this.bookAction(book);
    column.innerHTML = `<div class="book-card-shell"><div class="book-card"><div class="book-face book-face-front"><div class="book-cover${cover ? "" : " book-cover-fallback"}">${coverMarkup}<div class="book-cover-content"><span class="badge bg-light text-dark mb-3">${this.escapeHtml(book.category_name || "Library")}</span><h4 class="fw-bold text-white mb-2">${title}</h4><p class="text-white-50 small mb-0">${author}</p></div></div></div><div class="book-face book-face-back"><div class="book-back-content"><div class="d-flex justify-content-between align-items-start mb-3"><div><h5 class="fw-bold mb-1">${title}</h5><p class="text-muted small mb-0">${author}</p></div>${this.badge(status)}</div><p class="text-muted small mb-3">${this.escapeHtml(book.description || "No description available")}</p><div class="small text-muted mb-3"><div><strong>Copies:</strong> ${totalQuantity} total · ${availableQuantity} available</div><div><strong>Publisher:</strong> ${this.escapeHtml(book.publisher || "N/A")}</div><div><strong>Location:</strong> Floor ${this.escapeHtml(book.floor_no)} · Shelf ${this.escapeHtml(book.shelf_no)} · Row ${this.escapeHtml(book.row_no)}</div></div>${action}</div></div></div></div>`;
    const cardShell = column.querySelector(".book-card-shell");
    cardShell?.classList.add(`${this.classPrefix}-search-card`);
    cardShell?.querySelector(".book-card")?.setAttribute("tabindex", "0");
    const image = column.querySelector("img");
    image?.addEventListener("error", () => {
      image.style.display = "none";
      image.closest(".book-cover").classList.add("book-cover-fallback");
    });
    return column;
  }

  badge(status) {
    const type = { Available: "success", Borrowed: "danger", Overdue: "danger" }[status] || "secondary";
    return `<span class="badge bg-${type}">${status}</span>`;
  }

  bindEvents() {
    this.form.addEventListener("submit", (event) => {
      event.preventDefault();
      const skipTracking = this.skipSearchTracking === true;
      this.skipSearchTracking = false;
      const query = new URLSearchParams(new FormData(this.form));
      query.delete("page");
      const destination = this.form.action + "?" + query.toString();
      const tracking = skipTracking ? Promise.resolve() : this.recordSearch(query.get("search") || "");
      tracking.finally(() => {
        window.location.href = destination;
      });
    });
    ["category_name", "status", "floor", "sort"].forEach((name) => this.form.elements[name].addEventListener("change", () => {
      this.skipSearchTracking = true;
      this.form.requestSubmit();
    }));
    this.showAllBooksButton.addEventListener("click", () => {
      const visible = !this.allBooksPanel.hidden;
      this.setAllBooksVisible(!visible);
      if (!visible) this.loadCatalog(1);
    });
    this.pagination.addEventListener("click", (event) => {
      const button = event.target.closest?.("[data-catalog-page]");
      if (!button || button.disabled) return;
      this.loadCatalog(Number(button.dataset.catalogPage));
    });
    this.recommendationResults.addEventListener("click", (event) => this.handleWaitlistClick(event));
    this.results.addEventListener("click", (event) => this.handleWaitlistClick(event));
    const modal = document.getElementById("borrowModal");
    modal.addEventListener("show.bs.modal", (event) => {
      const button = event.relatedTarget;
      if (button?.dataset.titleId) {
        this.cart.addTitle({ title_id: Number(button.dataset.titleId), title: button.dataset.title, author: button.dataset.author, available_quantity: Number(button.dataset.availableQuantity || 1) }, 1, button.dataset.bookBarcode || "");
        this.renderCart();
      }
      document.getElementById("borrow-error").hidden = true;
    });
    modal.addEventListener("hidden.bs.modal", () => document.getElementById("borrowFormModal").reset());
    document.getElementById("borrowFormModal").addEventListener("submit", (event) => {
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
    fetch(`${this.lookupApi}?barcode=${encodeURIComponent(barcode)}`, { headers: { "X-Requested-With": "fetch" } })
      .then((response) => response.json().then((payload) => ({ ok: response.ok && payload.ok, payload })))
      .then(({ ok, payload }) => { if (!ok) throw new Error(payload.message || "Book copy not found."); const copy = payload.data; this.cart.addTitle(copy, 1, copy.status === "Available" ? barcode : ""); this.renderCart(); })
      .catch((error) => { const host = document.getElementById("borrow-error"); host.textContent = error.message; host.hidden = false; });
  }

  submitCart() {
    if (this.cart.totalQuantity() === 0) { const host = document.getElementById("borrow-error"); host.textContent = "Add at least one book to your cart."; host.hidden = false; return; }
    const body = new FormData();
    body.append("action", "borrow");
    body.append("csrf", this.csrf);
    this.cart.items().forEach((item, index) => {
      body.append(`items[${index}][title_id]`, String(item.title_id));
      body.append(`items[${index}][quantity]`, String(item.quantity));
      item.barcodes.forEach((barcode) => body.append(`items[${index}][barcodes][]`, barcode));
    });
    fetch(this.borrowApi, { method: "POST", headers: { "X-Requested-With": "fetch" }, body })
      .then((response) => response.json().then((payload) => ({ ok: response.ok && payload.ok, payload })))
      .then(({ ok, payload }) => { if (!ok) throw new Error(payload.errors?.[0] || payload.message || "Borrow request failed."); this.cart.clear(); window.location.href = this.dashboardPath; })
      .catch((error) => { const host = document.getElementById("borrow-error"); host.textContent = error.message; host.hidden = false; });
  }
}
