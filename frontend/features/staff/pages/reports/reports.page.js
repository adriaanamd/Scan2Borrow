export class ReportsPage {
  constructor(root = globalThis.document, { service, window = globalThis.window, document = globalThis.document } = {}) {
    this.root = root;
    this.service = service;
    this.window = window;
    this.document = document;
    this.pageSize = 10;
    this.currentPage = 1;
    this.report = { label: 'Library Report', headers: [], data: [] };
    this.filters = { type: 'borrowed', from: '', to: '' };
    this.isPrintMode = false;
    this.reportReady = false;
    this.eventsBound = false;
  }

  start() {
    this.bindEvents();
    return this.load();
  }

  async load() {
    const query = new URLSearchParams(this.window?.location?.search || '');
    const filters = { type: query.get('type') || 'borrowed', from: query.get('from') || '', to: query.get('to') || '' };
    this.filters = filters;
    this.currentPage = 1;
    this.isPrintMode = query.has('print');
    this.reportReady = false;
    this.root.querySelector?.('#staff-report-document')?.dataset && (this.root.querySelector('#staff-report-document').dataset.reportReady = 'false');
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

    try {
      const response = await this.service?.load(filters);
      if (!response) return response;

      this.report = response.data?.report || {};
      this.render(this.report, filters.from, filters.to);
      const exportLink = this.root.querySelector?.('#export-report-link');
      if (exportLink && this.service?.exportUrl) exportLink.href = this.service.exportUrl(filters);
      const printLink = this.root.querySelector?.('#generate-report-link');
      if (printLink && this.service?.printUrl) printLink.href = this.service.printUrl(filters);
      this.setPdfAvailability(true);
      if (query.has('print')) {
        this.root.classList?.add('report-print-mode');
        await this.printReportWhenReady();
      }
      return response;
    } catch (error) {
      this.report = { label: 'Library Report', headers: [], data: [] };
      this.render(this.report, filters.from, filters.to);
      this.reportReady = false;
      const documentNode = this.root.querySelector?.('#staff-report-document');
      if (documentNode) documentNode.dataset.reportReady = 'false';
      this.setPdfAvailability(false);
      this.text('staff-report-status', error?.message || 'Unable to load report data.');
      return null;
    }
  }

  render(report, from = '', to = '') {
    const table = this.root.querySelector?.('#staff-report-table');
    const documentNode = this.root.querySelector?.('#staff-report-document');
    if (!table || !documentNode) return;

    const headers = Array.isArray(report.headers) ? report.headers : [];
    const rows = Array.isArray(report.data) ? report.data : [];
    this.report = { label: report.label || 'Library Report', headers, data: rows };
    this.renderPage();

    this.text('staff-report-title', this.report.label);
    this.text('staff-report-period', this.reportPeriod(from, to));
    this.text('staff-report-count', `${rows.length} record${rows.length === 1 ? '' : 's'}`);
    this.text('staff-report-generated', new Date().toLocaleString());
    this.text('staff-report-status', rows.length ? `${rows.length} records loaded` : 'No records found');
    documentNode.dataset.reportReady = 'true';
    this.reportReady = true;
  }

  renderPage() {
    const table = this.root.querySelector?.('#staff-report-table');
    if (!table) return;

    const headers = this.report.headers;
    const allRows = this.report.data;
    const quantityIndex = headers.findIndex((header) => String(header).toLowerCase() === 'quantity');
    const rows = this.isPrintMode
      ? allRows
      : allRows.slice((this.currentPage - 1) * this.pageSize, this.currentPage * this.pageSize);
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
        headers.forEach((_, index) => {
          const cell = this.document.createElement('td');
          const value = Array.isArray(values) ? values[index] : undefined;
          cell.textContent = String(value ?? (index === quantityIndex ? 1 : ''));
          row.appendChild(cell);
        });
        body.appendChild(row);
      });
    }

    this.updatePagination();
  }

  bindEvents() {
    if (this.eventsBound) return;
    this.eventsBound = true;
    this.root.querySelector?.('#staff-report-previous')?.addEventListener?.('click', () => this.goToPage(this.currentPage - 1));
    this.root.querySelector?.('#staff-report-next')?.addEventListener?.('click', () => this.goToPage(this.currentPage + 1));
    this.root.querySelector?.('#download-report-pdf')?.addEventListener?.('click', () => this.downloadPdf());
  }

  pageCount() {
    return Math.max(1, Math.ceil(this.report.data.length / this.pageSize));
  }

  goToPage(page) {
    const value = Number.isFinite(Number(page)) ? Math.trunc(Number(page)) : 1;
    this.currentPage = Math.min(Math.max(1, value), this.pageCount());
    this.renderPage();
  }

  updatePagination() {
    const pagination = this.root.querySelector?.('#staff-report-pagination');
    const range = this.root.querySelector?.('#staff-report-range');
    const previous = this.root.querySelector?.('#staff-report-previous');
    const next = this.root.querySelector?.('#staff-report-next');
    const total = this.report.data.length;
    const start = total ? (this.isPrintMode ? 1 : (this.currentPage - 1) * this.pageSize + 1) : 0;
    const end = total ? (this.isPrintMode ? total : Math.min(this.currentPage * this.pageSize, total)) : 0;
    if (range) range.textContent = `${start}–${end} of ${total}`;
    if (pagination) pagination.hidden = this.isPrintMode || total === 0;
    if (previous) previous.disabled = this.isPrintMode || this.currentPage <= 1;
    if (next) next.disabled = this.isPrintMode || this.currentPage >= this.pageCount();
  }

  setPdfAvailability(available) {
    const button = this.root.querySelector?.('#download-report-pdf');
    if (button) button.disabled = !available;
  }

  async downloadPdf() {
    if (!this.reportReady) return;
    const button = this.root.querySelector?.('#download-report-pdf');
    const JsPdf = this.window?.jspdf?.jsPDF;
    if (typeof JsPdf !== 'function') {
      this.text('staff-report-status', 'PDF export is unavailable.');
      return;
    }

    if (button) button.disabled = true;
    this.text('staff-report-status', 'Generating PDF...');
    try {
      const pdf = new JsPdf({ orientation: 'landscape', unit: 'pt', format: 'a4' });
      if (typeof pdf.autoTable !== 'function' || typeof pdf.save !== 'function') throw new Error('PDF export is unavailable.');
      pdf.setFontSize?.(16);
      pdf.text?.(this.report.label, 40, 36);
      pdf.setFontSize?.(10);
      pdf.text?.(this.reportPeriod(this.filters.from, this.filters.to), 40, 52);
      pdf.autoTable({
        startY: 68,
        head: [this.report.headers.map((header) => String(header))],
        body: this.report.data.map((values) => this.report.headers.map((_, index) => String((Array.isArray(values) ? values[index] : '') ?? ''))),
        styles: { fontSize: 8, cellPadding: 4 },
        headStyles: { fillColor: [7, 89, 133] },
      });
      pdf.save(this.pdfFilename());
      this.text('staff-report-status', `${this.report.data.length} records exported to PDF`);
    } catch (error) {
      this.text('staff-report-status', error?.message || 'Unable to export PDF.');
    } finally {
      if (button) button.disabled = false;
    }
  }

  pdfFilename() {
    const parts = [
      'scan2borrow',
      this.filters.type,
      this.filters.from || 'all-dates',
      this.filters.to ? `to-${this.filters.to}` : '',
    ].filter(Boolean).map((value) => String(value).replace(/[^a-z0-9]+/gi, '-').replace(/^-+|-+$/g, '').toLowerCase());
    return `${parts.join('-') || 'scan2borrow-report'}.pdf`;
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
