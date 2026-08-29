export class StudentHistoryPage {
  constructor() {
    this.body = document.getElementById("history-body");
    this.applyRole(this.cachedRole());
    this.resolveRole().then((role) => {
      this.applyRole(role);
      this.load();
    });
  }
  cachedRole() {
    try {
      return window.sessionStorage?.getItem("scan2borrow.nav.role") || "";
    } catch {
      return "";
    }
  }
  async resolveRole() {
    const cached = this.cachedRole();
    if (cached === "teacher" || cached === "student") return cached;
    try {
      const response = await window.fetch("/scan2borrow/api/auth/session", {
        headers: { Accept: "application/json" },
      });
      const payload = await response.json();
      return payload.ok === true && payload.data?.role === "teacher"
        ? "teacher"
        : "student";
    } catch {
      return "student";
    }
  }
  applyRole(role) {
    const teacher = role === "teacher";
    document.body.classList.toggle("teacher-history-page", teacher);
    document.body.classList.toggle("student-history-page", !teacher);
    const roleHost = document.getElementById("current-user-role");
    if (roleHost) roleHost.textContent = teacher ? "Teacher" : "Student";
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
    fetch("/scan2borrow/api/student/history", {
      headers: { "X-Requested-With": "fetch" },
    })
      .then((response) => response.json())
      .then((response) => {
        if (!response.ok) throw new Error(response.message);
        this.render(response.data?.history || []);
      })
      .catch((error) => {
        this.body.innerHTML = `<tr class="student-history-row student-history-row--error"><td colspan="8" class="student-library-state text-center text-danger">${this.escapeHtml(error.message || "Unable to load history.")}</td></tr>`;
      });
  }
  render(history) {
    this.body.replaceChildren();
    if (!history.length) {
      this.body.innerHTML =
        '<tr class="student-history-row student-history-row--empty"><td colspan="8" class="student-library-state text-center text-muted">No borrowing history yet.</td></tr>';
      return;
    }
    history.forEach((item) => {
      const row = document.createElement("tr");
      row.classList.add("student-history-row", "teacher-history-row");
      if (!item.return_date && item.status === "Overdue")
        row.classList.add("row-overdue", "student-history-row--overdue", "teacher-history-row--overdue");
      row.innerHTML = `<td><code>${this.escapeHtml(item.transaction_code)}</code></td><td>${this.escapeHtml(item.title)}<br><span class="text-muted small">${this.escapeHtml(item.author)}</span></td><td>${Number(item.quantity || 1)}</td><td>${this.date(item.borrow_date)}</td><td>${this.date(item.due_date)}</td><td>${item.return_date ? this.date(item.return_date) : '<span class="text-muted">&mdash;</span>'}</td><td><span class="badge bg-secondary">${this.escapeHtml(item.status)}</span></td><td>${Number(item.fine_amount || 0) > 0 ? `₱${Number(item.fine_amount).toFixed(2)}` : "&mdash;"}</td>`;
      row.querySelector("td:nth-child(7)")?.classList.add("student-history-status", "teacher-history-status");
      row.querySelector("td:nth-child(7) .badge")?.classList.add("student-history-status-badge", "teacher-history-status-badge", this.statusClass(item.status));
      if (Number(item.fine_amount || 0) > 0)
        row.querySelector("td:nth-child(8)")?.classList.add("student-history-fine", "teacher-history-fine");
      this.body.appendChild(row);
    });
  }
  date(value) {
    const date = new Date(value);
    return Number.isNaN(date.valueOf())
      ? this.escapeHtml(value)
      : date.toLocaleDateString("en-US", {
          month: "short",
          day: "2-digit",
          year: "numeric",
        });
  }

  statusClass(status) {
    return (
      {
        Returned: "student-history-status--returned",
        Borrowed: "student-history-status--borrowed",
        Overdue: "student-history-status--overdue",
      }[status] || "student-history-status--default"
    );
  }
}
if (typeof window !== "undefined") {
  window.addEventListener("DOMContentLoaded", () => new StudentHistoryPage());
}
