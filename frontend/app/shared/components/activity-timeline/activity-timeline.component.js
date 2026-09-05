export class ActivityTimelineComponent {
  constructor(host, { document = globalThis.document, classPrefix = "borrower-activity" } = {}) {
    this.host = host;
    this.document = document;
    this.classPrefix = classPrefix;
  }

  render(rows = [], { compact = false } = {}) {
    if (!this.host || !this.document) return;
    this.host.replaceChildren();
    const items = Array.isArray(rows) ? rows : [];
    if (!items.length) {
      this.host.appendChild(this.message("No account activity yet."));
      return;
    }
    const list = this.document.createElement("div");
    list.className = `${this.classPrefix}__list${compact ? " is-compact" : ""}`;
    items.forEach((row) => list.appendChild(this.item(row)));
    this.host.appendChild(list);
  }

  renderError(message = "Unable to load activity.") {
    if (!this.host || !this.document) return;
    this.host.replaceChildren(this.message(message, true));
  }

  item(row = {}) {
    const item = this.document.createElement("article");
    item.className = `${this.classPrefix}__item`;

    const body = this.document.createElement("div");
    body.className = `${this.classPrefix}__body`;
    const label = this.document.createElement("strong");
    label.className = `${this.classPrefix}__label`;
    label.textContent = String(row.label || "Account activity");
    body.appendChild(label);

    const details = this.document.createElement("div");
    details.className = `${this.classPrefix}__details`;
    details.textContent = String(row.details || row.title || row.transaction_code || "");
    body.appendChild(details);

    if (row.status) {
      const status = this.document.createElement("span");
      status.className = `${this.classPrefix}__status`;
      status.textContent = String(row.status);
      body.appendChild(status);
    }
    item.appendChild(body);

    const time = this.document.createElement("time");
    time.className = `${this.classPrefix}__time`;
    time.dateTime = String(row.occurred_at || "");
    time.textContent = this.formatDate(row.occurred_at);
    item.appendChild(time);
    return item;
  }

  message(text, error = false) {
    const message = this.document.createElement("p");
    message.className = `${this.classPrefix}__state${error ? ` ${this.classPrefix}__state--error` : ""}`;
    message.textContent = text;
    return message;
  }

  escapeHtml(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, (character) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
    })[character]);
  }

  formatDate(value) {
    if (!value) return "";
    const date = new Date(value);
    return Number.isNaN(date.valueOf())
      ? String(value)
      : date.toLocaleString("en-US", { dateStyle: "medium", timeStyle: "short" });
  }
}
