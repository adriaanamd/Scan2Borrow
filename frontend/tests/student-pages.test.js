import test from 'node:test';
import assert from 'node:assert/strict';
import { StudentDashboardPage } from '../features/student/pages/dashboard/student-dashboard.page.js';
import { StudentSearchPage } from '../features/student/pages/search/student-search.page.js';

test('student dashboard exposes a feature-owned page controller with legacy render boundaries', () => {
  assert.equal(StudentDashboardPage.name, 'StudentDashboardPage');
  for (const method of ['load', 'render', 'renderLoans', 'submitForm', 'showBorrowSuccess']) {
    assert.equal(typeof StudentDashboardPage.prototype[method], 'function');
  }
});

test('student search exposes a feature-owned page controller with catalog rendering boundaries', () => {
  assert.equal(StudentSearchPage.name, 'StudentSearchPage');
  for (const method of ['load', 'render', 'bookCard', 'renderActiveFilters', 'bindEvents']) {
    assert.equal(typeof StudentSearchPage.prototype[method], 'function');
  }
});
