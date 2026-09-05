import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { BorrowerActivityPage } from '../app/shared/pages/borrower-activity.page.js';

const testsDirectory = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(testsDirectory, '..');

function read(relativePath) {
  return fs.readFileSync(path.join(root, relativePath), 'utf8');
}

test('borrower activity pages use the shared timeline renderer', () => {
  assert.match(read('app/shared/components/activity-timeline/activity-timeline.component.js'), /class ActivityTimelineComponent/);
  assert.match(read('app/shared/pages/borrower-activity.page.js'), /data\?\.activity/);
  assert.match(read('features/student/pages/activity/activity.html'), /data-app-page="student-activity"/);
  assert.match(read('features/teacher/pages/activity/activity.html'), /data-app-page="teacher-activity"/);
});

test('activity renderer exposes safe display and empty-state contracts', () => {
  const source = read('app/shared/components/activity-timeline/activity-timeline.component.js');
  for (const marker of ['render(', 'escapeHtml(', 'formatDate(', 'No account activity yet.', '__item', '__time']) {
    assert.match(source, new RegExp(marker.replace(/[()]/g, '\\$&')));
  }
  assert.match(source, /toLocaleString\(['"]en-US['"]/);
});

test('activity rows reuse the recommended-book presentation pattern', () => {
  const source = read('app/shared/components/activity-timeline/activity-timeline.component.js');
  for (const marker of ['className = `rec ', 'rec-cover', 'rec-t', 'rec-m', 'badge']) {
    assert.match(source, new RegExp(marker));
  }
});

test('both dashboards expose recent activity hooks', () => {
  for (const role of ['student', 'teacher']) {
    const html = read('features/' + role + '/pages/dashboard/dashboard.html');
    const source = read('features/' + role + '/pages/dashboard/' + role + '-dashboard.page.js');
    assert.match(html, /id="recent-activity"/);
    assert.match(html, new RegExp('/scan2borrow/' + role + '/activity'));
    assert.match(source, /renderRecentActivity/);
    assert.match(source, /recent_activity/);
  }
});

test('dashboard activity replaces achievements in the recommended-card row', () => {
  for (const role of ['student', 'teacher']) {
    const html = read('features/' + role + '/pages/dashboard/dashboard.html');
    const source = read('features/' + role + '/pages/dashboard/' + role + '-dashboard.page.js');
    assert.equal((html.match(/id="recent-activity"/g) || []).length, 1);
    assert.equal((html.match(/borrower-dashboard__recent-activity/g) || []).length, 0);
    assert.doesNotMatch(html, /Smart Insights/);
    if (role === 'student') {
      assert.doesNotMatch(html, /Achievements|achievement-count|id="achievements"/);
      assert.doesNotMatch(source, /renderAchievements|defaultAchievements|achievement-count|id="achievements"/);
      assert.match(html, /Recommended for You[\s\S]*Recent Activity/);
      assert.match(html, /student-dashboard__activity/);
    }
  }
});

test('activity page binds the browser fetch function to its window', async () => {
  const window = {
    fetch(url) {
      assert.equal(this, window);
      assert.equal(url, '/activity');
      return Promise.resolve({
        ok: true,
        json: async () => ({ ok: true, data: { activity: [] } }),
      });
    },
  };
  const page = new BorrowerActivityPage({
    api: '/activity',
    window,
    document: null,
  });

  const payload = await page.load();
  assert.deepEqual(payload.data.activity, []);
});

test('activity tab exposes ten-row paging and complete-history export controls', () => {
  for (const role of ['student', 'teacher']) {
    const html = read('features/' + role + '/pages/activity/activity.html');
    for (const marker of ['activity-pagination', 'activity-range', 'activity-previous', 'activity-next', 'export-activity-pdf']) {
      assert.match(html, new RegExp(marker));
    }
  }
  const source = read('app/shared/pages/borrower-activity.page.js');
  for (const marker of ['pageSize = 10', 'goToPage', 'exportPdf', 'window\.print', 'this\.rows']) {
    assert.match(source, new RegExp(marker));
  }
});

test('activity pagination renders ten rows and advances to the complete next page', () => {
  const page = new BorrowerActivityPage({ document: null, window: {}, fetchImpl: async () => null });
  const rendered = [];
  page.timeline = { render: (rows) => rendered.push(rows) };
  const rows = Array.from({ length: 11 }, (_, id) => ({ id }));

  page.render(rows);
  assert.equal(rendered.at(-1).length, 10);
  page.goToPage(2);
  assert.deepEqual(rendered.at(-1), [{ id: 10 }]);
});

test('activity PDF export prints the complete history and restores the current page', () => {
  const rendered = [];
  let printedRows = null;
  const rows = Array.from({ length: 11 }, (_, id) => ({ id }));
  const window = {
    print() {
      printedRows = rendered.at(-1);
    },
  };
  const page = new BorrowerActivityPage({ document: null, window, fetchImpl: async () => null });
  page.timeline = { render: (items) => rendered.push(items) };

  page.render(rows);
  page.goToPage(2);
  assert.equal(page.exportPdf(), true);
  assert.deepEqual(printedRows, rows);
  assert.deepEqual(rendered.at(-1), [{ id: 10 }]);
});

test('borrower navbar keeps history and adds role-specific activity paths', () => {
  const source = read('assets/js/core/app-navbar.js');
  assert.match(source, /student\/history/);
  assert.match(source, /teacher\/history/);
  assert.match(source, /student\/activity/);
  assert.match(source, /teacher\/activity/);
});

test('activity pages load the animated Bootstrap modal runtime for logout confirmation', () => {
  for (const role of ['student', 'teacher']) {
    const html = read('features/' + role + '/pages/activity/activity.html');
    assert.match(html, /bootstrap\.bundle\.min\.js/);
  }
});
