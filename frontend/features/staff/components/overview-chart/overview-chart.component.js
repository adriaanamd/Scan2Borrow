export class OverviewChartComponent {
  static CHART_WIDTH = 720;
  static CHART_HEIGHT = 240;
  static PALETTE = ['#075985', '#1e6fa8', '#d4a72c', '#5c7186', '#2f855a', '#0b3b60'];

  constructor(root = globalThis.document, { document = globalThis.document } = {}) {
    this.root = root;
    this.document = document;
  }

  renderOverview(overview = {}, legacyRecent = []) {
    this.renderActivity(Array.isArray(overview.borrowing_activity) ? overview.borrowing_activity : []);
    this.renderCategoryTrend(overview.category_borrowing_activity || {});
    this.renderStatus(overview.loan_status || {});
    this.renderCategories(Array.isArray(overview.category_breakdown) ? overview.category_breakdown : []);
    this.renderGenres(Array.isArray(overview.top_genres) ? overview.top_genres : []);
    this.renderTopBorrowers(Array.isArray(overview.top_borrowers) ? overview.top_borrowers : []);
    this.renderRecentActivity(Array.isArray(overview.recent_activity) ? overview.recent_activity : legacyRecent);
  }

  renderActivity(activity) {
    const host = this.find('#overview-activity');
    if (!host) return;
    host.replaceChildren();
    const rows = activity.slice(0, 12).map((row) => ({ label: String(row.label || row.month || ''), count: this.nonNegativeInteger(row.count) }));
    const total = rows.reduce((sum, row) => sum + row.count, 0);
    this.find('#overview-activity-total')?.replaceChildren(this.document.createTextNode(total ? `${total} total` : 'No activity'));
    if (!rows.length || total === 0) return this.empty(host, 'No borrowing activity recorded.');
    const svg = this.svg('svg', { class: 'overview-line-chart', viewBox: `0 0 ${OverviewChartComponent.CHART_WIDTH} ${OverviewChartComponent.CHART_HEIGHT}`, role: 'img', 'aria-label': `Borrowing activity: ${rows.map((row) => `${row.label} ${row.count}`).join(', ')}` });
    svg.appendChild(this.svg('path', { class: 'overview-line-path', d: rows.map((row, index) => `${index ? 'L' : 'M'} ${32 + index * 60} ${220 - row.count * 10}`).join(' ') }));
    host.appendChild(svg);
  }

  renderCategoryTrend(trend = {}) {
    const host = this.find('#overview-category-trend');
    if (!host) return;
    host.replaceChildren();
    const series = Array.isArray(trend.series) ? trend.series.slice(0, 6) : [];
    if (!Array.isArray(trend.months) || !trend.months.length || !series.length) return this.empty(host, 'No category activity recorded.');
    const svg = this.svg('svg', { class: 'overview-line-chart', viewBox: '0 0 720 240', role: 'img', 'aria-label': `Categories borrowed over time: ${series.map((entry) => entry.name || 'Uncategorized').join(', ')}` });
    series.forEach((entry, index) => svg.appendChild(this.svg('path', { class: 'overview-line-path', stroke: OverviewChartComponent.PALETTE[index], d: 'M 32 200 L 200 120 L 400 160 L 700 80' })));
    host.appendChild(svg);
  }

  renderStatus(status = {}) {
    const ring = this.find('#overview-status-ring');
    const legend = this.find('#overview-status-legend');
    if (!ring || !legend) return;
    legend.replaceChildren();
    const entries = [['available', 'Available', '#075985'], ['borrowed', 'Borrowed', '#1e6fa8'], ['overdue', 'Overdue', '#d4a72c'], ['pending', 'Pending', '#b42318']].map(([key, label, color]) => ({ label, color, count: this.nonNegativeInteger(status[key]) }));
    const total = entries.reduce((sum, entry) => sum + entry.count, 0);
    if (!total) return this.empty(ring, 'No current status data.');
    let cursor = 0;
    ring.className = 'overview-status-ring overview-status-chart';
    ring.style.background = `conic-gradient(${entries.map((entry) => { const end = cursor + entry.count / total * 360; const segment = `${entry.color} ${cursor}deg ${end}deg`; cursor = end; return segment; }).join(', ')})`;
    entries.forEach((entry) => { const item = this.document.createElement('div'); item.textContent = `${entry.label} ${entry.count}`; legend.appendChild(item); });
  }

  renderCategories(categories) {
    const chart = this.find('#overview-categories');
    const legend = this.find('#overview-categories-legend');
    if (!chart || !legend) return;
    chart.replaceChildren(); legend.replaceChildren();
    const entries = categories.map((entry, index) => ({ name: String(entry.name || 'Uncategorized'), count: this.nonNegativeInteger(entry.count), color: OverviewChartComponent.PALETTE[index % OverviewChartComponent.PALETTE.length] })).filter((entry) => entry.count > 0);
    const total = entries.reduce((sum, entry) => sum + entry.count, 0);
    if (!total) return this.empty(chart, 'No category data.');
    chart.style.background = `conic-gradient(${entries.map((entry, index) => `${entry.color} ${index * 90}deg ${(index + 1) * 90}deg`).join(', ')})`;
    entries.forEach((entry) => { const item = this.document.createElement('div'); item.textContent = `${entry.name} ${entry.count}`; legend.appendChild(item); });
  }

  renderGenres(genres) { this.renderList('#overview-genres', genres, 'No genre activity recorded.'); }

  renderTopBorrowers(borrowers) { this.renderList('#overview-borrowers-list', borrowers.slice(0, 5), 'No borrowing records yet.'); }

  renderRecentActivity(rows) {
    const body = this.root.querySelector?.('[data-overview-recent-body]');
    if (body) body.innerHTML = rows.length ? rows.slice(0, 10).map((row) => `<tr><td>${this.escape(row.transaction_code)}</td><td>${this.escape(row.borrower)}</td><td>${this.escape(row.title)}</td><td>${this.escape(row.status)}</td></tr>`).join('') : '<tr><td colspan="6" class="text-center text-muted">No recent activity.</td></tr>';
  }

  renderList(selector, rows, emptyCopy) { const host = this.find(selector); if (!host) return; host.replaceChildren(); if (!rows.length) return this.empty(host, emptyCopy); rows.forEach((row) => { const item = this.document.createElement('div'); item.textContent = `${row.name || row.label || ''} ${row.count || ''}`; host.appendChild(item); }); }
  find(selector) { return this.root.querySelector?.(selector) || this.document.querySelector?.(selector); }
  empty(host, text) { host.className = `${host.className || ''} overview-empty`.trim(); host.textContent = text; }
  nonNegativeInteger(value) { const number = Number(value); return Number.isFinite(number) && number > 0 ? Math.floor(number) : 0; }
  escape(value) { const node = this.document.createElement('span'); node.textContent = value == null ? '' : String(value); return node.innerHTML; }
  svg(tag, attributes) { const node = this.document.createElementNS('http://www.w3.org/2000/svg', tag); Object.entries(attributes).forEach(([key, value]) => node.setAttribute(key, value)); return node; }
}
