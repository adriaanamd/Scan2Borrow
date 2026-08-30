export class BorrowerHistoryPage {
  constructor({ historyApi, classPrefix, surfacePrefix, copy }) {
    this.body = document.getElementById("history-body");
    this.historyApi = historyApi;
    this.classPrefix = classPrefix;
    this.surfacePrefix = surfacePrefix;
    this.copy = copy;
    this.applyCopy();
    this.load();
  }

  applyCopy() {
    document.title = `${this.copy.topbar} | Scan2Borrow`;
    document.querySelector('[data-role-copy="history-topbar"]')?.replaceChildren(document.createTextNode(this.copy.topbar));
    document.querySelector('[data-role-copy="history-title"]')?.replaceChildren(document.createTextNode(this.copy.title));
    document.querySelector('[data-role-copy="history-description"]')?.replaceChildren(document.createTextNode(this.copy.description));
    document.getElementById("current-user-role")?.replaceChildren(document.createTextNode(this.copy.role));
  }

  escapeHtml(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, (character) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[character]);
  }

  load() {
    fetch(this.historyApi, { headers: { "X-Requested-With": "fetch" } })
      .then((response) => response.json())
      .then((response) => {
        if (!response.ok) throw new Error(response.message);
        this.render(response.data?.history || []);
      })
      .catch((error) => {
        this.body.innerHTML = `<tr class="${this.classPrefix}-row ${this.classPrefix}-row--error"><td colspan="8" class="${this.surfacePrefix}-library-state ${this.classPrefix}-state ${this.classPrefix}-state--error text-center text-danger">${this.escapeHtml(error.message || "Unable to load history.")}</td></tr>`;
      });
  }

  render(history) {
    this.body.replaceChildren();
    if (!history.length) {
      this.body.innerHTML = `<tr class="${this.classPrefix}-row ${this.classPrefix}-row--empty"><td colspan="8" class="${this.surfacePrefix}-library-state ${this.classPrefix}-state ${this.classPrefix}-state--empty text-center text-muted">No borrowing history yet.</td></tr>`;
      return;
    }
    history.forEach((item) => {
      const row = document.createElement("tr");
      row.classList.add(`${this.classPrefix}-row`);
      if (!item.return_date && item.status === "Overdue") row.classList.add("row-overdue", `${this.classPrefix}-row--overdue`);
      row.innerHTML = `<td><code>${this.escapeHtml(item.transaction_code)}</code></td><td>${this.escapeHtml(item.title)}<br><span class="text-muted small">${this.escapeHtml(item.author)}</span></td><td>${Number(item.quantity || 1)}</td><td>${this.date(item.borrow_date)}</td><td>${this.date(item.due_date)}</td><td>${item.return_date ? this.date(item.return_date) : '<span class="text-muted">&mdash;</span>'}</td><td><span class="badge bg-secondary">${this.escapeHtml(item.status)}</span></td><td>${Number(item.fine_amount || 0) > 0 ? `₱${Number(item.fine_amount).toFixed(2)}` : "&mdash;"}</td>`;
      row.querySelector("td:nth-child(7)")?.classList.add(`${this.classPrefix}-status`);
      row.querySelector("td:nth-child(7) .badge")?.classList.add(`${this.classPrefix}-status-badge`, this.statusClass(item.status));
      if (Number(item.fine_amount || 0) > 0) row.querySelector("td:nth-child(8)")?.classList.add(`${this.classPrefix}-fine`);
      this.body.appendChild(row);
    });
  }

  date(value) {
    const date = new Date(value);
    return Number.isNaN(date.valueOf()) ? this.escapeHtml(value) : date.toLocaleDateString("en-US", { month: "short", day: "2-digit", year: "numeric" });
  }

  statusClass(status) {
    return `${this.classPrefix}-status--${String(status || "default").toLowerCase()}`;
  }
}
