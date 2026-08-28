class StaffApi {
  constructor() {
    this.csrf = document.querySelector('meta[name="csrf"]')?.content || "";
  }

  async get(path, params = {}) {
    const query = new URLSearchParams(params);
    const url = query.toString() ? `${path}?${query}` : path;
    const response = await fetch(url, {
      headers: { Accept: "application/json" },
    });
    return this.parse(response);
  }

  async post(path, values) {
    const body = new URLSearchParams({ ...values, csrf: this.csrf });
    const response = await fetch(path, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body,
    });
    return this.parse(response);
  }

  async parse(response) {
    const data = await response.json();
    if (!response.ok || data.ok === false || data.success === false) {
      throw new Error(data.message || data.errors?.[0] || "Request failed.");
    }
    return data;
  }
}

class StaffPageController {
  constructor(root) {
    this.root = root;
    this.api = new StaffApi();
  }

  start() {
    switch (this.root.dataset.page) {
      case "staff-dashboard":
        return this.dashboard();
      case "staff-students":
        return this.students();
      case "staff-overdue":
        return this.overdue();
      case "staff-reports":
        return this.reports();
      case "staff-guest-requests":
        return this.guestRequests();
      case "staff-admin-staff":
        return this.adminStaff();
      default:
        return Promise.resolve();
    }
  }

  async dashboard() {
    try {
      const response = await this.api.get("/scan2borrow/api/staff/dashboard");
      const data = response.data;
      const stats = data.stats || {};
      const values = [
        stats.total_books,
        stats.available_books,
        stats.borrowed_books,
        stats.borrowers,
        stats.active_loans,
        stats.overdue_loans,
      ];
      this.root.querySelectorAll(".stat-card .value").forEach((node, index) => {
        node.textContent = values[index] ?? 0;
      });
      this.renderRecent(data.recent || []);
      this.renderApprovals(data.pending || []);
      window.setInterval(() => this.refreshNotifications(), 5000);
    } catch (error) {
      this.showError(error);
    }
  }

  renderRecent(rows) {
    const body = this.root.querySelector("table tbody");
    if (!body) return;
    body.innerHTML = rows.length
      ? rows
          .map(
            (row) => `<tr>
              <td><code>${this.escape(row.transaction_code)}</code></td>
              <td>${this.escape(row.borrower)}</td>
              <td>${this.escape(row.title)}</td>
              <td>${this.escape(this.date(row.borrow_date))}</td>
              <td>${this.escape(this.date(row.due_date))}</td>
              <td><span class="badge bg-secondary">${this.escape(row.status)}</span></td>
            </tr>`,
          )
          .join("")
      : '<tr><td colspan="6" class="text-center text-muted">No transactions yet.</td></tr>';
  }

  renderApprovals(rows) {
    const list = document.getElementById("approvalList");
    if (!list) return;
    list.innerHTML = rows.length
      ? rows.map((row) => this.approvalCard(row)).join("")
      : `<div class="text-center text-muted py-5">
          <div style="font-size: 48px">&#9989;</div>
          <p class="mt-3">No pending approval requests at this time.</p>
        </div>`;
    this.root.querySelectorAll("#approvalModal .badge").forEach((badge) => {
      badge.textContent = rows.length;
    });
    this.root
      .querySelectorAll(
        '[data-staff-action="approve"], [data-staff-action="reject"]',
      )
      .forEach((form) => {
        form.addEventListener("submit", (event) => this.submitBorrowing(event));
      });
  }

  approvalCard(row) {
    const cover = row.cover_file || "";
    return `<div class="approval-card mb-3">
      <div class="infos">
        <div class="image">${cover ? `<img src="${this.escape(cover)}" alt="Book cover" />` : '<div class="no-photo">No Photo</div>'}</div>
        <div class="info">
          <div><p class="name">${this.escape(row.title)}</p><p class="function">by ${this.escape(row.author || "—")} | Barcode: ${this.escape(row.book_barcode)}</p></div>
          <div class="details" style="font-size: .95rem; color: rgba(156, 163, 175, 1); margin-top: 6px">
            <p style="margin: 0"><strong>Student:</strong> ${this.escape(row.borrower)}</p>
            <p style="margin: 0"><strong>ID:</strong> ${this.escape(row.id_barcode)}</p>
            <p style="margin: 0"><strong>Due Date:</strong> ${this.escape(this.date(row.due_date))}</p>
            <small class="text-muted">Requested: ${this.escape(this.date(row.borrow_date))}</small>
          </div>
        </div>
      </div>
      <div class="approval-actions">
        <form method="POST" class="flex-fill" data-staff-action="approve">
          <input type="hidden" name="borrowing_id" value="${this.escape(row.id)}" />
          <button type="submit" class="request">Accept</button>
        </form>
        <form method="POST" class="flex-fill" data-staff-action="reject">
          <input type="hidden" name="borrowing_id" value="${this.escape(row.id)}" />
          <button type="submit" class="request">Reject</button>
        </form>
      </div>
    </div>`;
  }

  async refreshNotifications() {
    try {
      const response = await this.api.get(
        "/scan2borrow/api/staff/notifications",
        { action: "pending_approvals" },
      );
      this.renderApprovals(response.notifications || []);
    } catch {
      // Polling is best effort; the dashboard remains usable if it misses a tick.
    }
  }

  async submitBorrowing(event) {
    event.preventDefault();
    const form = event.currentTarget;
    try {
      const response = await this.api.post(
        "/scan2borrow/api/staff/borrowing-action",
        {
          action: form.dataset.staffAction,
          borrowing_id: form.elements.borrowing_id.value,
        },
      );
      this.toast(response.message || "Saved.", "success");
      await this.dashboard();
    } catch (error) {
      this.toast(error.message, "danger");
    }
  }

  async students() {
    const search =
      new URLSearchParams(window.location.search).get("search") || "";
    const input = this.root.querySelector('input[name="search"]');
    if (input) input.value = search;
    try {
      const response = await this.api.get("/scan2borrow/api/staff/borrowers", {
        search,
      });
      const body = this.root.querySelector("table tbody");
      if (!body) return;
      const rows = response.data.borrowers || [];
      body.innerHTML = rows.length
        ? rows
            .map(
              (row) => `<tr>
            <td>${this.escape(row.barcode)}</td><td>${this.escape(row.name)}</td>
            <td>${this.escape((row.role || "").replace(/^./, (letter) => letter.toUpperCase()))}</td>
            <td>${this.escape(row.department || "—")}</td><td>${this.escape(row.position || "—")}</td>
            <td>${this.escape(row.course || "")}</td><td>${this.escape(row.year_level || "")}</td>
            <td><span class="badge bg-primary">${row.active_loans || 0}</span>${Number(row.overdue_loans) ? ` <span class="badge bg-danger">${row.overdue_loans} overdue</span>` : ""}</td>
            <td>${this.escape(row.status || "")}</td>
            <td class="text-nowrap"><a href="/scan2borrow/staff/students?id=${row.id}" class="btn btn-primary btn-sm">View</a><a href="/scan2borrow/staff/students?id=${row.id}" class="btn btn-warning btn-sm">Notify</a></td>
          </tr>`,
            )
            .join("")
        : '<tr><td colspan="10" class="text-center text-muted">No borrowers found.</td></tr>';
    } catch (error) {
      this.showError(error);
    }
  }

  async overdue() {
    try {
      const response = await this.api.get("/scan2borrow/api/staff/overdue");
      const rows = response.data.overdue || [];
      const values = this.root.querySelectorAll(".stat-card .value");
      if (values[0]) values[0].textContent = rows.length;
      if (values[1])
        values[1].textContent = `₱${Number(response.data.total_fine || 0).toFixed(2)}`;
      const body = this.root.querySelector("table tbody");
      if (body)
        body.innerHTML = rows.length
          ? rows
              .map(
                (row) => `<tr class="row-overdue">
        <td>${this.escape(row.borrower)}<br><span class="text-muted small">${this.escape(row.id_barcode)}</span></td>
        <td>${this.escape(row.title)}</td><td>${this.escape(this.date(row.due_date))}</td>
        <td><span class="badge bg-danger">${row.days_late || 0} day(s)</span></td><td>₱${Number(row.fine_amount || 0).toFixed(2)}</td>
        <td><a href="/scan2borrow/staff/students?id=${row.user_id}" class="btn btn-warning btn-sm">Email Reminder</a></td>
      </tr>`,
              )
              .join("")
          : '<tr><td colspan="6" class="text-center text-muted">No overdue books. &#127881;</td></tr>';
    } catch (error) {
      this.showError(error);
    }
  }

  async reports() {
    const query = new URLSearchParams(window.location.search);
    const type = query.get("type") || "borrowed";
    const from = query.get("from") || "";
    const to = query.get("to") || "";
    const select = this.root.querySelector('select[name="type"]');
    if (select) {
      select.innerHTML = [
        "borrowed|Borrowed Books",
        "returned|Returned Books",
        "overdue|Overdue Books",
        "inventory|Inventory Status",
      ]
        .map((item) => {
          const [value, label] = item.split("|");
          return `<option value="${value}"${value === type ? " selected" : ""}>${label}</option>`;
        })
        .join("");
    }
    this.root.querySelector('input[name="from"]')?.setAttribute("value", from);
    this.root.querySelector('input[name="to"]')?.setAttribute("value", to);
    try {
      const response = await this.api.get("/scan2borrow/api/staff/reports", {
        type,
        from,
        to,
      });
      const report = response.data.report;
      const table = this.root.querySelector(".table-card:last-of-type table");
      if (table) {
        table.querySelector("thead").innerHTML =
          `<tr>${report.headers.map((header) => `<th>${this.escape(header)}</th>`).join("")}</tr>`;
        table.querySelector("tbody").innerHTML = report.data.length
          ? report.data
              .map(
                (row) =>
                  `<tr>${row.map((cell) => `<td>${this.escape(cell)}</td>`).join("")}</tr>`,
              )
              .join("")
          : `<tr><td colspan="${report.headers.length}" class="text-center text-muted">No records.</td></tr>`;
        const heading = table.closest(".table-card")?.querySelector("h5");
        if (heading)
          heading.innerHTML = `${this.escape(report.label)} <span class="text-muted fs-6">(${report.data.length} records)</span>`;
      }
      const exportLink = this.root.querySelector('a[href*="export=1"]');
      if (exportLink)
        exportLink.href = `/scan2borrow/api/staff/reports/export?type=${encodeURIComponent(type)}&from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`;
      if (query.has("print")) window.setTimeout(() => window.print(), 250);
    } catch (error) {
      this.showError(error);
    }
  }

  async guestRequests() {
    try {
      const response = await this.api.get(
        "/scan2borrow/api/staff/guest-requests",
      );
      const rows = response.data.requests || [];
      const badge = this.root.querySelector(".section-title .badge");
      if (badge) badge.textContent = rows.length;
      const body = this.root.querySelector("table tbody");
      if (!body) return;
      body.innerHTML = rows.length
        ? rows
            .map(
              (row) => `<tr>
        <td>${row.visitor_photo ? `<img src="${this.escape(row.visitor_photo)}" class="rounded-circle me-2" style="width:38px;height:38px;object-fit:cover" alt="Guest photo">` : ""}<strong>${this.escape(row.name)}</strong><br><span class="text-muted small">${this.escape(row.visitor_number || "—")} · ${this.escape(row.id_barcode)}</span></td>
        <td>${this.escape(row.title)}<br><span class="text-muted small">${this.escape(row.author || "—")}</span></td><td><code>${this.escape(row.accession)}</code></td>
        <td>${this.escape(row.requested_at || row.created_at || "")}</td><td>${row.verification_photo ? `<button class="btn btn-outline-primary btn-sm" data-photo="${this.escape(row.verification_photo)}" data-name="${this.escape(row.name)}" data-book="${this.escape(row.title)}" data-photo-view>View</button>` : '<span class="text-muted">—</span>'}</td>
        <td><button class="btn btn-success btn-sm" data-id="${row.id}" data-name="${this.escape(row.name)}" data-photo="${this.escape(row.visitor_photo || "")}" data-visno="${this.escape(row.visitor_number || "")}" data-idbarcode="${this.escape(row.id_barcode || "")}" data-title="${this.escape(row.title)}" data-author="${this.escape(row.author || "")}" data-accession="${this.escape(row.accession)}" data-verif="${this.escape(row.verification_photo || "")}" data-review-request>Review</button></td>
      </tr>`,
            )
            .join("")
        : '<tr><td colspan="6" class="text-center text-muted py-4">No pending guest borrow requests.</td></tr>';
      this.bindGuestReview();
    } catch (error) {
      this.showError(error);
    }
  }

  bindGuestReview() {
    this.root.querySelectorAll("[data-review-request]").forEach((button) =>
      button.addEventListener("click", () => {
        const modal = document.getElementById("reviewModal");
        Object.entries({
          "review-id": "id",
          "review-name": "name",
          "review-visno": "visno",
          "review-idbarcode": "idbarcode",
          "review-title": "title",
          "review-author": "author",
          "review-accession": "accession",
        }).forEach(([id, key]) => {
          const node = document.getElementById(id);
          if (node) node.textContent = button.dataset[key] || "";
        });
        document.getElementById("review-id").value = button.dataset.id || "";
        document.getElementById("review-photo").src =
          button.dataset.photo || "";
        document.getElementById("review-verif").src =
          button.dataset.verif || "";
        bootstrap.Modal.getOrCreateInstance(modal).show();
      }),
    );
    this.root.querySelectorAll("[data-photo-view]").forEach((button) =>
      button.addEventListener("click", () => {
        document.getElementById("photoViewer").src = button.dataset.photo || "";
        document.getElementById("photoCaption").textContent =
          `${button.dataset.name || ""} holding "${button.dataset.book || ""}"`;
        bootstrap.Modal.getOrCreateInstance(
          document.getElementById("viewPhotoModal"),
        ).show();
      }),
    );
    const form = document.querySelector("#reviewModal form");
    form?.addEventListener("submit", async (event) => {
      event.preventDefault();
      try {
        const response = await this.api.post(
          "/scan2borrow/api/staff/guest-action",
          {
            id: form.elements.id.value,
            action: event.submitter.value,
            notes: document.getElementById("review-notes").value,
          },
        );
        this.toast(response.message || "Saved.", "success");
        bootstrap.Modal.getInstance(
          document.getElementById("reviewModal"),
        )?.hide();
        await this.guestRequests();
      } catch (error) {
        this.toast(error.message, "danger");
      }
    });
  }

  async adminStaff() {
    const query = new URLSearchParams(window.location.search);
    try {
      const response = await this.api.get("/scan2borrow/api/admin/staff", {
        bsearch: query.get("bsearch") || "",
      });
      this.renderAdminStaff(
        response.data.staff || [],
        response.data.borrowers || [],
      );
    } catch (error) {
      this.showError(error);
    }
  }

  renderAdminStaff(staff, borrowers) {
    const tables = this.root.querySelectorAll("table tbody");
    if (tables[0])
      tables[0].innerHTML = staff.length
        ? staff
            .map(
              (row) =>
                `<tr><td>${this.escape(row.barcode)}</td><td>${this.escape(row.name)}</td><td><span class="badge bg-primary">${this.escape(row.role)}</span></td><td class="text-muted small">${this.escape(row.email || "")}</td><td>${this.escape(row.status || "")}</td><td class="text-nowrap"><button class="btn btn-outline-secondary btn-sm" data-reset-user="${row.id}" data-name="${this.escape(row.name)}">Reset Password</button><button class="btn btn-outline-warning btn-sm" data-toggle-user="${row.id}">Toggle Status</button><button class="btn btn-outline-danger btn-sm" data-demote-user="${row.id}">Demote</button></td></tr>`,
            )
            .join("")
        : '<tr><td colspan="6" class="text-center text-muted">No staff accounts.</td></tr>';
    if (tables[1])
      tables[1].innerHTML = borrowers.length
        ? borrowers
            .map(
              (row) =>
                `<tr><td>${this.escape(row.barcode)}</td><td>${this.escape(row.name)}</td><td class="text-muted">${this.escape(row.course || "")}</td><td><button class="btn btn-gradient btn-sm" data-promote-user="${row.id}" data-name="${this.escape(row.name)}">&#128081; Promote to Librarian</button></td></tr>`,
            )
            .join("")
        : '<tr><td colspan="4" class="text-center text-muted">No borrowers found.</td></tr>';
    this.bindAdminActions();
  }

  bindAdminActions() {
    this.root.querySelectorAll("[data-reset-user]").forEach((button) =>
      button.addEventListener("click", () => {
        document.getElementById("pw_uid").value = button.dataset.resetUser;
        document.getElementById("pw_name").textContent = button.dataset.name;
        bootstrap.Modal.getOrCreateInstance(
          document.getElementById("pwModal"),
        ).show();
      }),
    );
    this.root.querySelectorAll("[data-promote-user]").forEach((button) =>
      button.addEventListener("click", () => {
        document.getElementById("promote_uid").value =
          button.dataset.promoteUser;
        document.getElementById("promote_name").textContent =
          button.dataset.name;
        bootstrap.Modal.getOrCreateInstance(
          document.getElementById("promoteModal"),
        ).show();
      }),
    );
    this.root
      .querySelectorAll("[data-toggle-user], [data-demote-user]")
      .forEach((button) =>
        button.addEventListener("click", () =>
          this.adminAction(
            button.dataset.toggleUser ? "toggle_status" : "demote",
            button.dataset.toggleUser || button.dataset.demoteUser,
          ),
        ),
      );
    this.root
      .querySelectorAll("#promoteModal form, #pwModal form")
      .forEach((form) =>
        form.addEventListener("submit", (event) => {
          event.preventDefault();
          this.adminAction(
            form.elements.action.value,
            form.elements.user_id.value,
            Object.fromEntries(new FormData(form)),
          );
        }),
      );
  }

  async adminAction(action, userId, values = {}) {
    try {
      const response = await this.api.post(
        "/scan2borrow/api/admin/staff-action",
        { ...values, action, user_id: userId },
      );
      this.toast(response.message || "Saved.", "success");
      await this.adminStaff();
    } catch (error) {
      this.toast(error.message, "danger");
    }
  }

  date(value) {
    return value ? String(value).slice(0, 10) : "";
  }

  escape(value) {
    const node = document.createElement("span");
    node.textContent = value == null ? "" : String(value);
    return node.innerHTML;
  }

  showError(error) {
    this.toast(error.message || "Could not load staff data.", "danger");
  }

  toast(message, type) {
    const container =
      document.getElementById("toastContainer") ||
      this.root.querySelector(".content");
    if (!container) return;
    const node = document.createElement("div");
    node.className = `alert alert-${type} mt-3`;
    node.textContent = message;
    container.prepend(node);
    window.setTimeout(() => node.remove(), 5000);
  }
}

document.addEventListener("DOMContentLoaded", () => {
  const root = document.querySelector("[data-page^=staff-]");
  if (root) new StaffPageController(root).start();
});
