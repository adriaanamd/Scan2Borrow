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
      case "staff-borrower":
        return this.borrowerDetails();
      case "staff-notify":
        return this.notify();
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
      this.renderApprovals(data.pending || []);
      this.renderOverview(data.overview || {}, data.recent || []);
      window.setInterval(() => this.refreshNotifications(), 5000);
    } catch (error) {
      this.showError(error);
    }
  }

  renderOverview(overview, legacyRecent = []) {
    const activity = Array.isArray(overview.borrowing_activity)
      ? overview.borrowing_activity
      : [];
    const status =
      overview.loan_status && typeof overview.loan_status === "object"
        ? overview.loan_status
        : {};
    const borrowers = Array.isArray(overview.top_borrowers)
      ? overview.top_borrowers
      : [];
    const categories = Array.isArray(overview.category_breakdown)
      ? overview.category_breakdown
      : [];
    const genres = Array.isArray(overview.top_genres)
      ? overview.top_genres
      : [];
    const recent = Array.isArray(overview.recent_activity)
      ? overview.recent_activity
      : legacyRecent;

    this.renderActivity(activity);
    this.renderStatus(status);
    this.renderCategories(categories);
    this.renderGenres(genres);
    this.renderTopBorrowers(borrowers);
    this.renderRecentActivity(recent);
  }

  renderActivity(activity) {
    const host = document.getElementById("overview-activity");
    if (!host) return;
    host.replaceChildren();

    const rows = activity.slice(0, 12).map((row) => ({
      label: String(row.label || row.month || ""),
      count: this.nonNegativeInteger(row.count),
    }));
    const total = rows.reduce((sum, row) => sum + row.count, 0);
    const totalHost = document.getElementById("overview-activity-total");
    if (totalHost) totalHost.textContent = total ? `${total} total` : "No activity";

    if (!rows.length || total === 0) {
      const empty = document.createElement("div");
      empty.className = "overview-empty";
      empty.textContent = "No borrowing activity recorded.";
      host.appendChild(empty);
      return;
    }

    const width = 720;
    const height = 240;
    const padding = { top: 18, right: 16, bottom: 34, left: 32 };
    const plotWidth = width - padding.left - padding.right;
    const plotHeight = height - padding.top - padding.bottom;
    const maximum = Math.max(1, ...rows.map((row) => row.count));
    const xStep = rows.length > 1 ? plotWidth / (rows.length - 1) : plotWidth;
    const points = rows.map((row, index) => ({
      ...row,
      x: padding.left + index * xStep,
      y: padding.top + plotHeight - (row.count / maximum) * plotHeight,
    }));
    const svg = this.svg("svg", {
      class: "overview-line-chart",
      viewBox: `0 0 ${width} ${height}`,
      role: "img",
      "aria-label": `Borrowing activity: ${rows.map((row) => `${row.label} ${row.count}`).join(", ")}`,
    });

    [0, 0.5, 1].forEach((ratio) => {
      const y = padding.top + plotHeight - ratio * plotHeight;
      const line = this.svg("line", {
        class: "overview-line-grid",
        x1: padding.left,
        x2: width - padding.right,
        y1: y,
        y2: y,
      });
      const label = this.svg("text", {
        class: "overview-line-label",
        x: padding.left - 8,
        y: y + 4,
        "text-anchor": "end",
      });
      label.textContent = String(Math.round(maximum * ratio));
      svg.append(line, label);
    });

    const axis = this.svg("line", {
      class: "overview-line-axis",
      x1: padding.left,
      x2: width - padding.right,
      y1: padding.top + plotHeight,
      y2: padding.top + plotHeight,
    });
    svg.appendChild(axis);

    const path = this.svg("path", {
      class: "overview-line-path",
      d: points.map((point, index) => `${index === 0 ? "M" : "L"} ${point.x} ${point.y}`).join(" "),
    });
    svg.appendChild(path);

    points.forEach((point) => {
      const circle = this.svg("circle", {
        class: "overview-line-point",
        cx: point.x,
        cy: point.y,
        r: 3.5,
      });
      const value = this.svg("text", {
        class: "overview-line-value",
        x: point.x,
        y: Math.max(12, point.y - 10),
        "text-anchor": "middle",
      });
      value.textContent = String(point.count);
      const label = this.svg("text", {
        class: "overview-line-label",
        x: point.x,
        y: height - 10,
        "text-anchor": "middle",
      });
      label.textContent = point.label;
      svg.append(circle, value, label);
    });
    host.appendChild(svg);
  }

  renderStatus(status) {
    const ring = document.getElementById("overview-status-ring");
    const legend = document.getElementById("overview-status-legend");
    if (!ring || !legend) return;
    legend.replaceChildren();

    const entries = [
      { key: "available", label: "Available", color: "#002fa7" },
      { key: "borrowed", label: "Borrowed", color: "#64748b" },
      { key: "overdue", label: "Overdue", color: "#b45309" },
      { key: "pending", label: "Pending", color: "#b91c1c" },
    ].map((entry) => ({
      ...entry,
      count: this.nonNegativeInteger(status[entry.key]),
    }));
    const total = entries.reduce((sum, entry) => sum + entry.count, 0);

    if (total === 0) {
      ring.className = "overview-status-ring overview-status-chart overview-empty";
      ring.style.background = "";
      ring.textContent = "No current status data.";
      return;
    }

    let cursor = 0;
    const segments = entries.map((entry) => {
      const end = cursor + (entry.count / total) * 360;
      const segment = `${entry.color} ${cursor}deg ${end}deg`;
      cursor = end;
      return segment;
    });
    ring.className = "overview-status-ring overview-status-chart";
    ring.textContent = "";
    ring.style.background = `conic-gradient(${segments.join(", ")})`;
    ring.setAttribute(
      "aria-label",
      `Current status: ${entries.map((entry) => `${entry.label} ${entry.count}`).join(", ")}`,
    );

    entries.forEach((entry) => {
      const item = document.createElement("div");
      item.className = "overview-status-item";

      const swatch = document.createElement("span");
      swatch.className = "overview-status-swatch";
      swatch.style.setProperty("--status-color", entry.color);
      swatch.setAttribute("aria-hidden", "true");

      const name = document.createElement("span");
      name.className = "overview-status-name";
      name.textContent = entry.label;

      const count = document.createElement("span");
      count.className = "overview-status-count";
      count.textContent = String(entry.count);

      item.append(swatch, name, count);
      legend.appendChild(item);
    });
  }

  renderCategories(categories) {
    const chart = document.getElementById("overview-categories");
    const legend = document.getElementById("overview-categories-legend");
    if (!chart || !legend) return;
    chart.replaceChildren();
    legend.replaceChildren();

    const palette = ["#002fa7", "#64748b", "#b45309", "#b91c1c", "#0f766e", "#7c3aed"];
    const entries = categories
      .map((entry, index) => ({
        name: String(entry.name || "Uncategorized"),
        count: this.nonNegativeInteger(entry.count),
        color: palette[index % palette.length],
      }))
      .filter((entry) => entry.count > 0);
    const total = entries.reduce((sum, entry) => sum + entry.count, 0);
    if (total === 0) {
      chart.className = "overview-categories-chart overview-empty";
      chart.style.background = "";
      chart.textContent = "No category data.";
      return;
    }

    let cursor = 0;
    const segments = entries.map((entry) => {
      const end = cursor + (entry.count / total) * 360;
      const segment = `${entry.color} ${cursor}deg ${end}deg`;
      cursor = end;
      return segment;
    });
    chart.className = "overview-categories-chart";
    chart.style.background = `conic-gradient(${segments.join(", ")})`;
    chart.setAttribute(
      "aria-label",
      `Book categories: ${entries.map((entry) => `${entry.name} ${entry.count}`).join(", ")}`,
    );

    entries.forEach((entry) => {
      const item = document.createElement("div");
      item.className = "overview-chart-legend-item";
      const swatch = document.createElement("span");
      swatch.className = "overview-chart-swatch";
      swatch.style.setProperty("--status-color", entry.color);
      swatch.setAttribute("aria-hidden", "true");
      const name = document.createElement("span");
      name.className = "overview-chart-name";
      name.textContent = entry.name;
      const count = document.createElement("span");
      count.className = "overview-chart-count";
      count.textContent = String(entry.count);
      item.append(swatch, name, count);
      legend.appendChild(item);
    });
  }

  renderGenres(genres) {
    const host = document.getElementById("overview-genres");
    if (!host) return;
    host.replaceChildren();
    const rows = genres
      .slice(0, 5)
      .map((entry) => ({
        name: String(entry.name || "Uncategorized"),
        count: this.nonNegativeInteger(entry.count),
      }))
      .filter((entry) => entry.count > 0);
    if (!rows.length) {
      const empty = document.createElement("div");
      empty.className = "overview-empty";
      empty.textContent = "No genre activity recorded.";
      host.appendChild(empty);
      return;
    }

    const maximum = Math.max(1, ...rows.map((row) => row.count));
    rows.forEach((row) => {
      const item = document.createElement("div");
      item.className = "overview-genre-row";
      const name = document.createElement("span");
      name.className = "overview-genre-name";
      name.textContent = row.name;
      const track = document.createElement("span");
      track.className = "overview-genre-track";
      const bar = document.createElement("span");
      bar.className = "overview-genre-bar";
      bar.style.width = `${(row.count / maximum) * 100}%`;
      track.appendChild(bar);
      const count = document.createElement("span");
      count.className = "overview-genre-count";
      count.textContent = String(row.count);
      item.append(name, track, count);
      host.appendChild(item);
    });
    host.setAttribute(
      "aria-label",
      `Top genres borrowed: ${rows.map((row) => `${row.name} ${row.count}`).join(", ")}`,
    );
  }

  renderTopBorrowers(borrowers) {
    const list = document.getElementById("overview-borrowers-list");
    const toggle = document.getElementById("overview-borrowers-toggle");
    if (!list) return;
    list.replaceChildren();

    const rows = borrowers.slice(0, 10);
    if (!rows.length) {
      if (toggle) toggle.hidden = true;
      const empty = document.createElement("li");
      empty.className = "overview-empty";
      empty.textContent = "No borrowing records yet.";
      list.appendChild(empty);
      return;
    }

    if (toggle) {
      toggle.hidden = rows.length <= 5;
      toggle.dataset.expanded = "false";
      toggle.textContent = rows.length > 5 ? "View all top 10 →" : "";
      toggle.onclick = () => {
        const expanded = toggle.dataset.expanded === "true";
        toggle.dataset.expanded = expanded ? "false" : "true";
        toggle.textContent = expanded ? "View all top 10 →" : "Show top 5 ↑";
        list.querySelectorAll(".overview-borrower-row").forEach((row, index) => {
          row.dataset.hidden = !expanded && index >= 5 ? "true" : "false";
        });
      };
    }

    rows.forEach((borrower, index) => {
      const row = document.createElement("li");
      row.className = "overview-borrower-row";

      const rank = document.createElement("span");
      rank.className = "overview-borrower-rank";
      rank.textContent = String(index + 1).padStart(2, "0");

      const identity = document.createElement("span");
      identity.className = "overview-borrower-name";
      identity.textContent = String(borrower.name || "");
      const barcode = document.createElement("span");
      barcode.className = "overview-borrower-barcode";
      barcode.textContent = String(borrower.barcode || "");
      identity.appendChild(barcode);

      const count = document.createElement("span");
      count.className = "overview-borrower-count";
      count.textContent = String(this.nonNegativeInteger(borrower.borrowing_count));

      row.dataset.hidden = index >= 5 ? "true" : "false";
      row.append(rank, identity, count);
      list.appendChild(row);
    });
  }

  renderRecent(rows) {
    this.renderRecentActivity(rows);
  }

  renderRecentActivity(rows) {
    const body = this.root.querySelector("[data-overview-recent-body]");
    if (!body) return;
    rows = Array.isArray(rows) ? rows.slice(0, 10) : [];
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
      : '<tr><td colspan="6" class="text-center text-muted">No recent activity.</td></tr>';
  }

  svg(tag, attributes = {}) {
    const node = document.createElementNS("http://www.w3.org/2000/svg", tag);
    Object.entries(attributes).forEach(([name, value]) => {
      node.setAttribute(name, String(value));
    });
    return node;
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
    const cover = this.media(row.cover_file || "");
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
            <td class="text-nowrap"><a href="/scan2borrow/staff/borrower?id=${row.id}" class="btn btn-primary btn-sm">View</a><a href="/scan2borrow/staff/notify?id=${row.id}" class="btn btn-warning btn-sm">Notify</a></td>
          </tr>`,
            )
            .join("")
        : '<tr><td colspan="10" class="text-center text-muted">No borrowers found.</td></tr>';
    } catch (error) {
      this.showError(error);
    }
  }

  async borrowerDetails() {
    const id = new URLSearchParams(window.location.search).get("id") || "";
    try {
      const response = await this.api.get("/scan2borrow/api/staff/borrower", {
        id,
      });
      const data = response.data;
      const borrower = data.borrower || {};
      const summary = data.summary || {};
      const initials =
        `${(borrower.firstname || "")[0] || ""}${(borrower.lastname || "")[0] || ""}`.toUpperCase();
      const avatar = document.getElementById("borrower-avatar");
      if (avatar) {
        avatar.textContent = initials || "--";
        if (borrower.photo) {
          avatar.innerHTML = `<img src="${this.escape(this.media(borrower.photo))}" alt="ID photo" style="width:100%;height:100%;object-fit:cover;border-radius:inherit" />`;
        }
      }
      this.text("borrower-name", borrower.name || "Borrower Details");
      this.text(
        "borrower-meta",
        `ID: ${borrower.barcode || ""} · ${(borrower.role || "").replace(/^./, (letter) => letter.toUpperCase())}${borrower.course ? ` · ${borrower.course}` : ""}${borrower.year_level ? ` · Year ${borrower.year_level}` : ""}`,
      );
      this.text(
        "borrower-contact",
        `${borrower.email || ""}${borrower.contact_no ? ` · ${borrower.contact_no}` : ""}`,
      );
      this.text("borrower-status", borrower.status || "");
      const overdue = document.getElementById("borrower-overdue");
      if (overdue) {
        overdue.textContent = `${summary.overdue || 0} Overdue`;
        overdue.classList.toggle("d-none", !Number(summary.overdue));
      }
      this.text("borrower-active", summary.active || 0);
      this.text("borrower-returned", summary.returned || 0);
      this.text("borrower-overdue-count", summary.overdue || 0);
      this.text(
        "borrower-fines",
        `₱${Number(summary.total_fine || 0).toFixed(2)}`,
      );
      this.text("history-count", `(${(data.history || []).length} records)`);
      this.renderBorrowerHistory(data.history || []);
      const notify = document.getElementById("notify-borrower");
      if (notify)
        notify.href = `/scan2borrow/staff/notify?id=${encodeURIComponent(borrower.id || id)}`;
      const changePhoto = document.getElementById("change-photo");
      if (changePhoto)
        changePhoto.classList.toggle("d-none", !data.can_edit_photo);
      this.bindPhotoForm(borrower.id || id, data.can_edit_photo);
    } catch (error) {
      this.showError(error);
    }
  }

  renderBorrowerHistory(rows) {
    const body = document.getElementById("borrower-history");
    if (!body) return;
    body.innerHTML = rows.length
      ? rows
          .map(
            (
              row,
            ) => `<tr class="${!row.return_date && row.status === "Overdue" ? "row-overdue" : ""}">
          <td><code>${this.escape(row.transaction_code)}</code></td>
          <td>${this.escape(row.title)}<br /><span class="text-muted small">${this.escape(row.author || "")}</span></td>
          <td>${this.escape(this.date(row.borrow_date))}</td>
          <td>${this.escape(this.date(row.due_date))}</td>
          <td>${row.return_date ? this.escape(this.date(row.return_date)) : '<span class="text-muted">—</span>'}</td>
          <td><span class="badge bg-secondary">${this.escape(row.status)}</span></td>
          <td>${Number(row.fine_amount || 0) > 0 ? `₱${Number(row.fine_amount).toFixed(2)}` : "—"}</td>
        </tr>`,
          )
          .join("")
      : '<tr><td colspan="7" class="text-center text-muted">No borrowing history found.</td></tr>';
  }

  bindPhotoForm(id, canEdit) {
    const form = document.getElementById("photo-form");
    const input = document.getElementById("photo-file");
    const preview = document.getElementById("photo-preview");
    if (!form || !input || !preview || !canEdit || form.dataset.bound) return;
    form.dataset.bound = "true";
    input.addEventListener("change", () => {
      const file = input.files?.[0];
      if (!file) return;
      preview.src = URL.createObjectURL(file);
      preview.classList.remove("d-none");
    });
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const file = input.files?.[0];
      if (!file || file.size > 4 * 1024 * 1024) {
        this.toast("Please choose a valid image file (max 4 MB).", "danger");
        return;
      }
      const reader = new FileReader();
      reader.onload = async () => {
        try {
          await this.api.post("/scan2borrow/api/staff/borrower/photo", {
            user_id: id,
            photo_data: reader.result,
          });
          window.location.reload();
        } catch (error) {
          this.toast(error.message, "danger");
        }
      };
      reader.readAsDataURL(file);
    });
  }

  async notify() {
    const id = new URLSearchParams(window.location.search).get("id") || "";
    try {
      const response = await this.api.get("/scan2borrow/api/staff/borrower", {
        id,
      });
      const data = response.data;
      const borrower = data.borrower || {};
      this.text(
        "notify-name",
        `${borrower.name || ""} (${borrower.barcode || ""})`,
      );
      this.text("notify-email", borrower.email || "No email on file");
      this.text(
        "notify-contact",
        borrower.contact_no || "No contact number on file",
      );
      this.text("notify-loans", (data.summary || {}).active || 0);
      const back = document.getElementById("notify-back");
      if (back)
        back.href = `/scan2borrow/staff/borrower?id=${encodeURIComponent(id)}`;
      document
        .getElementById("send-email")
        ?.addEventListener("click", () => this.sendNotification(id, "email"));
      document
        .getElementById("send-sms")
        ?.addEventListener("click", () => this.sendNotification(id, "sms"));
      if (!borrower.email)
        document
          .getElementById("send-email")
          ?.setAttribute("disabled", "disabled");
      if (!borrower.contact_no)
        document
          .getElementById("send-sms")
          ?.setAttribute("disabled", "disabled");
    } catch (error) {
      this.showError(error);
    }
  }

  async sendNotification(id, channel) {
    try {
      const response = await this.api.post("/scan2borrow/api/staff/notify", {
        user_id: id,
        channel,
      });
      const host = document.getElementById(
        channel === "email" ? "notify-email-alert" : "notify-sms-alert",
      );
      if (host)
        host.innerHTML = `<div class="alert alert-success">${this.escape(response.message || "Notification sent.")}</div>`;
    } catch (error) {
      const host = document.getElementById(
        channel === "email" ? "notify-email-alert" : "notify-sms-alert",
      );
      if (host)
        host.innerHTML = `<div class="alert alert-danger">${this.escape(error.message)}</div>`;
    }
  }

  text(id, value) {
    const node = document.getElementById(id);
    if (node) node.textContent = value ?? "";
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
        <td><a href="/scan2borrow/staff/notify?id=${row.user_id}" class="btn btn-warning btn-sm">Email Reminder</a></td>
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
              (row) => {
                const visitorPhoto = this.media(row.visitor_photo || "");
                const verificationPhoto = this.media(row.verification_photo || "");
                return `<tr>
        <td>${visitorPhoto ? `<img src="${this.escape(visitorPhoto)}" class="rounded-circle me-2" style="width:38px;height:38px;object-fit:cover" alt="Guest photo">` : ""}<strong>${this.escape(row.name)}</strong><br><span class="text-muted small">${this.escape(row.visitor_number || "—")} · ${this.escape(row.id_barcode)}</span></td>
        <td>${this.escape(row.title)}<br><span class="text-muted small">${this.escape(row.author || "—")}</span></td><td><code>${this.escape(row.accession)}</code></td>
        <td>${this.escape(row.requested_at || row.created_at || "")}</td><td>${verificationPhoto ? `<button class="btn btn-outline-primary btn-sm" data-photo="${this.escape(verificationPhoto)}" data-name="${this.escape(row.name)}" data-book="${this.escape(row.title)}" data-photo-view>View</button>` : '<span class="text-muted">—</span>'}</td>
        <td><button class="btn btn-success btn-sm" data-id="${row.id}" data-name="${this.escape(row.name)}" data-photo="${this.escape(visitorPhoto)}" data-visno="${this.escape(row.visitor_number || "")}" data-idbarcode="${this.escape(row.id_barcode || "")}" data-title="${this.escape(row.title)}" data-author="${this.escape(row.author || "")}" data-accession="${this.escape(row.accession)}" data-verif="${this.escape(verificationPhoto)}" data-review-request>Review</button></td>
      </tr>`;
              },
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
        document.getElementById("review-photo").src = this.media(
          button.dataset.photo || "",
        );
        document.getElementById("review-verif").src = this.media(
          button.dataset.verif || "",
        );
        bootstrap.Modal.getOrCreateInstance(modal).show();
      }),
    );
    this.root.querySelectorAll("[data-photo-view]").forEach((button) =>
      button.addEventListener("click", () => {
        document.getElementById("photoViewer").src = this.media(
          button.dataset.photo || "",
        );
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

  media(value) {
    return Scan2BorrowMedia.resolve(value);
  }

  nonNegativeInteger(value) {
    const number = Number(value);

    return Number.isFinite(number) && number > 0 ? Math.floor(number) : 0;
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
