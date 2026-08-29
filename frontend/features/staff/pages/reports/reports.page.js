export class ReportsPage {
  constructor(root = globalThis.document, { service, window = globalThis.window, document = globalThis.document } = {}) {
    this.root = root;
    this.service = service;
    this.window = window;
    this.document = document;
  }

  start() { return this.load(); }

  async load() {
    const query = new URLSearchParams(this.window.location.search);
    const filters = { type: query.get('type') || 'borrowed', from: query.get('from') || '', to: query.get('to') || '' };
    const select = this.root.querySelector?.('select[name="type"]');
    if (select) {
      select.innerHTML = [
        ['borrowed', 'Borrowed Books'],
        ['returned', 'Returned Books'],
        ['overdue', 'Overdue Books'],
        ['inventory', 'Inventory Status'],
      ].map(([value, label]) => `<option value="${value}"${value === filters.type ? ' selected' : ''}>${label}</option>`).join('');
    }

    for (const field of ['from', 'to']) {
      const input = this.root.querySelector?.(`input[name="${field}"]`);
      if (input) {
        input.value = filters[field];
        input.setAttribute?.('value', filters[field]);
      }
    }

    const response = await this.service?.load(filters);
    if (response) {
      this.render(response.data?.report || {}, filters.from, filters.to);
      const exportLink = this.root.querySelector?.('#export-report-link');
      if (exportLink) exportLink.href = this.service.exportUrl(filters);
      const printLink = this.root.querySelector?.('#generate-report-link');
      if (printLink) printLink.href = this.service.printUrl(filters);
      if (query.has('print')) {
        this.root.classList?.add('report-print-mode');
        await this.printReportWhenReady();
      }
    }
    return response;
  }

  render(report, from = '', to = '') {
    const table = this.root.querySelector?.('#staff-report-table');
    const documentNode = this.root.querySelector?.('#staff-report-document');
    if (!table || !documentNode) return;

    const headers = Array.isArray(report.headers) ? report.headers : [];
    const rows = Array.isArray(report.data) ? report.data : [];
    const quantityIndex = headers.findIndex((header) => String(header).toLowerCase() === 'quantity');
    const head = table.querySelector?.('thead');
    const body = table.querySelector?.('tbody');
    if (!head || !body) return;

    head.replaceChildren?.();
    const headerRow = this.document.createElement('tr');
    headers.forEach((header) => {
      const cell = this.document.createElement('th');
      cell.scope = 'col';
      cell.textContent = String(header);
      headerRow.appendChild(cell);
    });
    head.appendChild(headerRow);

    body.replaceChildren?.();
    if (!rows.length) {
      const row = this.document.createElement('tr');
      const cell = this.document.createElement('td');
      cell.colSpan = Math.max(1, headers.length);
      cell.className = 'text-center text-muted';
      cell.textContent = 'No records.';
      row.appendChild(cell);
      body.appendChild(row);
    } else {
      rows.forEach((values) => {
        const row = this.document.createElement('tr');
        (Array.isArray(values) ? values : []).forEach((value, index) => {
          const cell = this.document.createElement('td');
          cell.textContent = String(value ?? (index === quantityIndex ? 1 : ''));
          row.appendChild(cell);
        });
        body.appendChild(row);
      });
    }

    this.text('staff-report-title', report.label || 'Library Report');
    this.text('staff-report-period', this.reportPeriod(from, to));
    this.text('staff-report-count', `${rows.length} record${rows.length === 1 ? '' : 's'}`);
    this.text('staff-report-generated', new Date().toLocaleString());
    this.text('staff-report-status', rows.length ? `${rows.length} records loaded` : 'No records found');
    documentNode.dataset.reportReady = 'true';
  }

  reportPeriod(from, to) {
    if (from && to) return `${this.date(from)} — ${this.date(to)}`;
    if (from) return `From ${this.date(from)}`;
    if (to) return `Through ${this.date(to)}`;
    return 'All available dates';
  }

  async printReportWhenReady() {
    const report = this.root.querySelector?.('#staff-report-document');
    if (!report || report.dataset.reportReady !== 'true') return;

    await new Promise((resolve) => {
      this.window.requestAnimationFrame(() => this.window.requestAnimationFrame(resolve));
    });
    this.window.print();
  }

  date(value) {
    return value ? String(value).slice(0, 10) : '';
  }

  text(id, value) {
    const node = this.root.querySelector?.(`#${id}`);
    if (node) node.textContent = String(value ?? '');
  }
}
