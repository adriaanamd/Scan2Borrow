export class StaffReportService {
  constructor({ api }) { this.api = api; }

  load({ type = 'borrowed', from = '', to = '', category = '' } = {}) {
    return this.api.get('/scan2borrow/api/staff/reports', { type, from, to, category });
  }

  exportUrl({ type = 'borrowed', from = '', to = '', category = '' } = {}) {
    const query = new URLSearchParams({ type });
    if (from) query.set('from', from);
    if (to) query.set('to', to);
    if (category) query.set('category', category);
    return `/scan2borrow/api/staff/reports/export?${query}`;
  }

  printUrl({ type = 'borrowed', from = '', to = '', category = '' } = {}) {
    const query = new URLSearchParams({ type, print: '1' });
    if (from) query.set('from', from);
    if (to) query.set('to', to);
    if (category) query.set('category', category);
    return `/scan2borrow/staff/reports?${query}`;
  }
}
