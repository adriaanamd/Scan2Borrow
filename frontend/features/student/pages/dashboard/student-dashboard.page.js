export class StudentDashboardPage {
  constructor() {
    this.api = "/scan2borrow/api/student/dashboard";
    this.csrf = document.querySelector('meta[name="csrf"]')?.content || "";
    this.$ = (id) => document.getElementById(id);
    this.borrowForm = this.$("borrowForm");
    this.returnForm = this.$("returnForm");
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

  request(path, options = {}) {
    const requestOptions = Object.assign(
      { headers: { "X-Requested-With": "fetch" } },
      options,
    );
    if (requestOptions.body && !(requestOptions.body instanceof FormData)) {
      requestOptions.headers["Content-Type"] = "application/json";
      requestOptions.body = JSON.stringify(requestOptions.body);
    }
    return fetch(path, requestOptions).then((response) => response.json());
  }

  load() {
    this.request(this.api)
      .then((response) => {
        if (!response.ok)
          throw new Error(response.message || "Unable to load your dashboard.");
        this.render(response.data || {});
      })
      .catch((error) => this.showToast(error.message, false));
  }

  render(data) {
    const user = data.user || {};
    const stats = data.stats || {};
    const active = Number(stats.active || 0);
    const overdue = Number(stats.overdue || 0);
    const limit = Number(data.max_books || 3);
    const fine = Number(stats.fines || 0);

    this.$("borrower-name").textContent = user.name || "";
    this.$("current-user-name").textContent = user.name || "";
    this.$("current-user-role").textContent = user.role || "Student";
    this.$("borrower-barcode").textContent = user.barcode || "";
    this.$("borrower-meta").textContent = [
      user.barcode,
      user.course ? ` · ${user.course}` : "",
      user.year_level ? ` · Year ${user.year_level}` : "",
    ].join("");

    this.$("active-count").textContent = String(active);
    this.$("overdue-count").textContent = String(overdue);
    this.$("fine-total").textContent = this.peso(fine);
    this.$("on-time-rate").textContent =
      `${Number(stats.on_time_rate ?? 100)}%`;
    this.renderCapacity(active, limit);
    this.renderOverdue(overdue);
    this.renderDueSoon(data.due_soon || []);
    this.renderRecommendations(
      data.recommended || [],
      data.favorite_category || "",
    );
    this.renderAchievements(data.achievements || this.defaultAchievements());
    this.renderLoans(data.current_loans || []);
    this.renderBarcode(user.barcode || "");
  }

  peso(value) {
    return `₱${value.toFixed(2)}`;
  }

  renderCapacity(active, limit) {
    const percent =
      limit > 0 ? Math.min(100, Math.round((active / limit) * 100)) : 0;
    const ring = this.$("capacity-ring");
    ring.style.setProperty("--val", percent);
    ring.style.setProperty(
      "--ring-color",
      active >= limit ? "var(--danger)" : "var(--primary)",
    );
    this.$("capacity-value").textContent = `${active}/${limit}`;
    this.$("capacity-remaining").textContent =
      `${Math.max(0, limit - active)} slot(s)`;
    this.$("capacity-limit").textContent = String(limit);
  }

  renderOverdue(count) {
    const alert = this.$("overdue-alert");
    alert.hidden = count === 0;
    this.$("overdue-alert-count").textContent = String(count);
  }

  renderDueSoon(items) {
    const host = this.$("due-soon");
    host.replaceChildren();
    if (!items.length) {
      host.className = "text-muted small";
      host.textContent =
        "Nothing due in the next 3 days. You're all caught up.";
      return;
    }
    host.className = "d-flex flex-column gap-2";
    items.forEach((item) => {
      const chip = document.createElement("span");
      const days = Number(item.days || 0);
      chip.className = `due-chip ${days <= 0 ? "is-today" : days >= 3 ? "is-ok" : ""}`;
      chip.innerHTML = `&#9200; <strong>${this.escapeHtml(item.title)}</strong> · ${days <= 0 ? "due today" : `in ${days} day${days === 1 ? "" : "s"}`}`;
      host.appendChild(chip);
    });
  }

  renderRecommendations(items, favoriteCategory) {
    const reason = this.$("recommendation-reason");
    const host = this.$("recommendations");
    reason.innerHTML = favoriteCategory
      ? `Because you enjoy <strong>${this.escapeHtml(favoriteCategory)}</strong>`
      : items.length
        ? "Based on your recent searches and interests"
        : "Popular available titles";
    host.replaceChildren();
    if (!items.length) {
      host.className = "text-muted small";
      host.textContent =
        "No available books to recommend right now. Try searching for something you like!";
      return;
    }
    items.forEach((book) => {
      const link = document.createElement("a");
      link.href =
        "/scan2borrow/student/search?search=" +
        encodeURIComponent(book.title || "");
      link.className = "rec";
      link.style.cssText = "text-decoration:none;color:inherit;";
      link.innerHTML = `<div class="rec-cover">${this.escapeHtml(
        String(book.title || "")
          .slice(0, 1)
          .toUpperCase(),
      )}</div><div class="flex-grow-1"><div class="rec-t">${this.escapeHtml(book.title)}</div><div class="rec-m">${this.escapeHtml(book.author)} · ${this.escapeHtml(book.category)}</div></div><span class="badge bg-light text-muted border">&#128205; Flr ${this.escapeHtml(book.floor_no)}</span>`;
      host.appendChild(link);
    });
    const catalog = document.createElement("a");
    catalog.href = "/scan2borrow/student/search";
    catalog.className = "btn btn-outline-primary btn-sm w-100 mt-3";
    catalog.textContent = "Browse full catalog";
    host.appendChild(catalog);
  }

  defaultAchievements() {
    return [
      ["First Chapter", "Borrowed your first book", "&#128075;", false],
      ["Bookworm", "Borrowed 5 or more books", "&#128027;", false],
      ["Explorer", "Read across 3+ categories", "&#129517;", false],
      ["On-Time Pro", "Returned books, never late", "&#9201;", false],
      ["Marathon Reader", "Returned 10 or more books", "&#127942;", false],
      ["Spotless", "Zero outstanding fines", "&#10024;", false],
    ];
  }

  renderAchievements(items) {
    const host = this.$("achievements");
    const unlocked = items.filter((item) => item[3]).length;
    this.$("achievement-count").textContent = `${unlocked}/${items.length}`;
    host.replaceChildren();
    items.forEach((item) => {
      const achievement = document.createElement("div");
      achievement.className = `ach ${item[3] ? "" : "locked"}`;
      achievement.title = item[1];
      achievement.innerHTML = `<div class="ach-ic">${item[3] ? item[2] : "&#128274;"}</div><div><div class="ach-t">${this.escapeHtml(item[0])}</div><div class="ach-d">${this.escapeHtml(item[1])}</div></div>`;
      host.appendChild(achievement);
    });
  }

  renderLoans(loans) {
    const body = this.$("current-loans");
    body.replaceChildren();
    if (!loans.length) {
      body.innerHTML =
        '<tr><td colspan="5" class="text-center text-muted py-4">You have no active borrowed. Tap <strong>Borrow a Book</strong> to get started.</td></tr>';
      return;
    }
    loans.forEach((loan) => {
      const row = document.createElement("tr");
      if (loan.status === "Overdue") row.className = "row-overdue";
      row.innerHTML = `<td>${this.escapeHtml(loan.title)}<br><span class="text-muted small">${this.escapeHtml(loan.author)}</span></td><td>${this.formatDate(loan.borrow_date)}</td><td>${this.formatDate(loan.due_date)}</td><td>${this.badge(loan.status)}</td><td><a href="/scan2borrow/receipt?code=${encodeURIComponent(loan.transaction_code || "")}" target="_blank" class="btn btn-outline-secondary btn-sm">View</a></td>`;
      body.appendChild(row);
    });
  }

  formatDate(value) {
    if (!value) return "";
    const date = new Date(value);
    return Number.isNaN(date.valueOf())
      ? String(value)
      : date.toLocaleDateString("en-US", {
          month: "short",
          day: "2-digit",
          year: "numeric",
        });
  }

  badge(status) {
    const type =
      {
        Borrowed: "primary",
        Overdue: "danger",
        Pending: "warning text-dark",
        Returned: "success",
      }[status] || "secondary";
    return `<span class="badge bg-${type}">${this.escapeHtml(status)}</span>`;
  }

  renderBarcode(barcode) {
    if (barcode && window.JsBarcode) {
      window.JsBarcode("#lib-barcode", barcode, {
        format: "CODE128",
        displayValue: false,
        margin: 0,
        height: 60,
        width: 2,
        lineColor: "#102f52",
      });
    }
  }

  submitForm(form, action, field, onSuccess) {
    const value = form.elements[field]?.value.trim() || "";
    const body = new FormData();
    body.append("action", action);
    body.append(field, value);
    body.append("csrf", this.csrf);
    this.request(this.api, { method: "POST", body })
      .then((response) => {
        if (!response.ok)
          throw new Error(response.errors?.[0] || response.message || "Request failed.");
        onSuccess(response.data || response);
        this.load();
      })
      .catch((error) => this.showToast(error.message, false));
  }

  showBorrowSuccess(data) {
    this.$("successMessage").textContent =
      `Successfully borrowed ${Number(data.book_count || 0)} book(s)!`;
    this.$("successTxnCode").textContent = data.transaction_code || "";
    this.$("successBookCount").textContent = String(data.book_count || 0);
    this.$("successReceiptLink").href =
      "/scan2borrow/receipt?code=" +
      encodeURIComponent(data.transaction_code || "");
    new bootstrap.Modal(this.$("successModal")).show();
  }

  showToast(message, ok) {
    const host = this.$("toast-host");
    if (!host) return;
    const toast = document.createElement("div");
    toast.className = `toast align-items-center text-white border-0 show mb-2 bg-${ok ? "success" : "danger"}`;
    toast.innerHTML = `<div class="d-flex"><div class="toast-body">${this.escapeHtml(message)}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
    host.appendChild(toast);
    window.setTimeout(() => toast.remove(), 3500);
  }

  bindEvents() {
    this.borrowForm?.addEventListener("submit", (event) => {
      event.preventDefault();
      this.submitForm(this.borrowForm, "borrow", "book_barcode", (data) =>
        this.showBorrowSuccess(data),
      );
    });
    this.returnForm?.addEventListener("submit", (event) => {
      event.preventDefault();
      this.submitForm(
        this.returnForm,
        "return_unified",
        "return_input",
        (data) => {
          this.$("return-message").hidden = false;
          this.$("return-message").textContent =
            data.message || "Book returned successfully.";
        },
      );
    });
    window.addEventListener("load", () =>
      this.renderBarcode(this.$("borrower-barcode").textContent),
    );
  }
}

if (typeof window !== "undefined") {
  window.addEventListener("DOMContentLoaded", () => new StudentDashboardPage());
}
