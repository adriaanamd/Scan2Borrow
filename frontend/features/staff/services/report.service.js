export class StaffReportService {
  constructor({ api }) { this.api = api; }

  load({ type = 'borrowed', from = '', to = '' } = {}) {
    return this.api.get('/scan2borrow/api/staff/reports', { type, from, to });
  }

  exportUrl({ type = 'borrowed', from = '', to = '' } = {}) {
    const query = new URLSearchParams({ type });
    if (from) query.set('from', from);
    if (to) query.set('to', to);
    return `/scan2borrow/api/staff/reports/export?${query}`;
  }

  printUrl({ type = 'borrowed', from = '', to = '' } = {}) {
    const query = new URLSearchParams({ type, print: '1' });
    if (from) query.set('from', from);
    if (to) query.set('to', to);
    return `/scan2borrow/staff/reports?${query}`;
  }
}
