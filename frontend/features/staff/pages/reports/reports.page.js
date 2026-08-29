export class ReportsPage {
  constructor(root = globalThis.document, { service, window = globalThis.window } = {}) {
    this.root = root;
    this.service = service;
    this.window = window;
  }

  start() { return this.load(); }

  async load() {
    const query = new URLSearchParams(this.window.location.search);
    const filters = { type: query.get('type') || 'borrowed', from: query.get('from') || '', to: query.get('to') || '' };
    const response = await this.service?.load(filters);
    if (response) this.render(response.data?.report || {});
    return response;
  }

  render(report) {
    const title = this.root.querySelector?.('#staff-report-title');
    if (title) title.textContent = report.title || '';
    const table = this.root.querySelector?.('#staff-report-table');
    if (table && report.html) table.innerHTML = report.html;
  }
}
