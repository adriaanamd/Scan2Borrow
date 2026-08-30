export class StudentHistoryPage {
  constructor() {
    this.body = document.getElementById("history-body");
    const initialRole = this.roleFromPage() || this.roleFromPath() || "student";
    this.applyRole(initialRole || "student");
    this.load();
  }
  roleFromPage() {
    const page = document.body?.dataset.appPage || "";
    if (page === "teacher-history") return "teacher";
    if (page === "student-history") return "student";
    return "";
  }
  roleFromPath() {
    return window.location.pathname.includes("/teacher/") ? "teacher" : "";
  }
  applyRole(role) {
    const teacher = role === "teacher";
    this.role = teacher ? "teacher" : "student";
    this.historyApi = teacher
      ? "/scan2borrow/api/teacher/history"
      : "/scan2borrow/api/student/history";
    document.body.classList.toggle("teacher-history-page", teacher);
    document.body.classList.toggle("student-history-page", !teacher);
    const roleHost = document.getElementById("current-user-role");
    if (roleHost) roleHost.textContent = teacher ? "Teacher" : "Student";
    const copy = teacher
      ? {
          topbar: "Borrowing History",
          title: "Borrowing History",
          description: "Review your complete faculty borrowing record.",
        }
      : {
          topbar: "My History",
          title: "My History",
          description: "Your complete borrowing record.",
        };
    document.title = `${copy.topbar} | Scan2Borrow`;
    document.querySelector('[data-role-copy="history-topbar"]')?.replaceChildren(document.createTextNode(copy.topbar));
    document.querySelector('[data-role-copy="history-title"]')?.replaceChildren(document.createTextNode(copy.title));
    document.querySelector('[data-role-copy="history-description"]')?.replaceChildren(document.createTextNode(copy.description));
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
    fetch(this.historyApi, {
      headers: { "X-Requested-With": "fetch" },
    })
      .then((response) => response.json())
      .then((response) => {
        if (!response.ok) throw new Error(response.message);
        this.render(response.data?.history || []);
      })
      .catch((error) => {
        this.body.innerHTML = `<tr class="student-history-row student-history-row--error teacher-history-row--error"><td colspan="8" class="student-library-state teacher-history-state teacher-history-state--error text-center text-danger">${this.escapeHtml(error.message || "Unable to load history.")}</td></tr>`;
      });
  }
  render(history) {
    this.body.replaceChildren();
    if (!history.length) {
      this.body.innerHTML =
        '<tr class="student-history-row student-history-row--empty teacher-history-row--empty"><td colspan="8" class="student-library-state teacher-history-state teacher-history-state--empty text-center text-muted">No borrowing history yet.</td></tr>';
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
