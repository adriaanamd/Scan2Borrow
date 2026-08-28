class GuestDashboardController {
  constructor() {
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
    fetch("/scan2borrow/api/guest/dashboard", {
      headers: { "X-Requested-With": "fetch" },
    })
      .then((response) => response.json())
      .then((response) => {
        if (!response.ok) throw new Error(response.errors?.[0]);
        this.render(response.data || {});
      })
      .catch(() => {});
  }
  render(data) {
    const visitor = data.visitor || {};
    const summary = data.summary || {};
    document.getElementById("visitor-name").textContent = visitor.name || "";
    document.getElementById("visitor-full-name").textContent =
      visitor.name || "";
    document.getElementById("visitor-number").textContent =
      visitor.visitor_number || "Pending";
    document.getElementById("account-status").textContent =
      visitor.account_status || "Active";
    document.getElementById("registration-expires").textContent =
      visitor.registration_expires_at || "—";
    ["active", "returned", "overdue"].forEach(
      (key) =>
        (document.getElementById(`${key}-count`).textContent = String(
          summary[key] || 0,
        )),
    );
    document.getElementById("days-remaining").textContent = String(
      data.days_remaining || 0,
    );
    document.getElementById("days-card").textContent = String(
      data.days_remaining || 0,
    );
    document.getElementById("total-borrowed").textContent = String(
      summary.total || 0,
    );
    document.getElementById("favorite-category").textContent =
      data.favorite_category || "No activity yet";
    document.getElementById("recent-book").textContent =
      data.recent_book || "No activity yet";
  }
}
window.addEventListener(
  "DOMContentLoaded",
  () => new GuestDashboardController(),
);
