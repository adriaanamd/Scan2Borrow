import { ActivityTimelineComponent } from "../components/activity-timeline/activity-timeline.component.js";

export class BorrowerActivityPage {
  constructor({ api, role, title, description, classPrefix, document = globalThis.document, window = globalThis.window || globalThis, fetchImpl } = {}) {
    this.api = api;
    this.role = role;
    this.title = title;
    this.description = description;
    this.classPrefix = classPrefix || "borrower-activity";
    this.document = document;
    this.window = window;
    this.fetchImpl = fetchImpl || this.window?.fetch?.bind(this.window);
    this.pageSize = 10;
    this.rows = [];
    this.currentPage = 1;
    this.eventsBound = false;
    this.timeline = new ActivityTimelineComponent(
      this.document?.getElementById("activity-timeline"),
      { document: this.document, classPrefix: this.classPrefix },
    );
  }

  start() {
    this.document.title = `${this.title} | Scan2Borrow`;
    this.document.getElementById("activity-title")?.replaceChildren(this.document.createTextNode(this.title));
    this.document.getElementById("activity-description")?.replaceChildren(this.document.createTextNode(this.description));
    this.document.getElementById("current-user-role")?.replaceChildren(this.document.createTextNode(this.role));
    this.bindEvents();
    return this.load();
  }

  bindEvents() {
    if (this.eventsBound || !this.document) return;

    this.document.getElementById("activity-previous")?.addEventListener("click", () => {
      this.goToPage(this.currentPage - 1);
    });
    this.document.getElementById("activity-next")?.addEventListener("click", () => {
      this.goToPage(this.currentPage + 1);
    });
    this.document.getElementById("export-activity-pdf")?.addEventListener("click", () => {
      this.exportPdf();
    });
    this.eventsBound = true;
  }

  load() {
    return this.fetchImpl(this.api, { headers: { "X-Requested-With": "fetch" } })
      .then((response) => response.json().then((payload) => ({ ok: response.ok && payload.ok, payload })))
      .then(({ ok, payload }) => {
        if (!ok) throw new Error(payload.message || payload.errors?.[0] || "Unable to load activity.");
        this.currentPage = 1;
        this.render(payload.data?.activity || []);
        return payload;
      })
      .catch((error) => {
        this.timeline.renderError(error.message || "Unable to load activity.");
        return null;
      });
  }

  render(rows) {
    this.rows = Array.isArray(rows) ? rows : [];
    this.renderPage();
  }

  renderPage() {
    const pageCount = this.getPageCount();
    this.currentPage = Math.min(Math.max(this.currentPage, 1), pageCount);
    const start = (this.currentPage - 1) * this.pageSize;
    this.timeline.render(this.rows.slice(start, start + this.pageSize));
    this.updatePagination();
  }

  getPageCount() {
    return Math.max(1, Math.ceil(this.rows.length / this.pageSize));
  }

  goToPage(page) {
    this.currentPage = Math.min(Math.max(Number(page) || 1, 1), this.getPageCount());
    this.renderPage();
  }

  updatePagination() {
    const pagination = this.document?.getElementById("activity-pagination");
    const range = this.document?.getElementById("activity-range");
    const previous = this.document?.getElementById("activity-previous");
    const next = this.document?.getElementById("activity-next");
    const exportButton = this.document?.getElementById("export-activity-pdf");
    if (!pagination || !range || !previous || !next) return;

    const total = this.rows.length;
    const start = total ? ((this.currentPage - 1) * this.pageSize) + 1 : 0;
    const end = Math.min(this.currentPage * this.pageSize, total);
    range.textContent = total ? `${start}–${end} of ${total}` : "0 items";
    pagination.hidden = total <= this.pageSize;
    previous.disabled = this.currentPage <= 1;
    next.disabled = this.currentPage >= this.getPageCount();
    if (exportButton) exportButton.disabled = total === 0;
  }

  exportPdf() {
    if (!this.rows.length || typeof this.window?.print !== "function") return false;

    this.document?.body?.classList?.add("activity-print-mode");
    this.timeline.render(this.rows);
    try {
      this.window.print();
    } finally {
      this.document?.body?.classList?.remove("activity-print-mode");
      this.renderPage();
    }
    return true;
  }
}
