import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

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

test('borrower navbar keeps history and adds role-specific activity paths', () => {
  const source = read('assets/js/core/app-navbar.js');
  assert.match(source, /student\/history/);
  assert.match(source, /teacher\/history/);
  assert.match(source, /student\/activity/);
  assert.match(source, /teacher\/activity/);
});
