import test from 'node:test';
import assert from 'node:assert/strict';
import { StudentDashboardPage } from '../features/student/pages/dashboard/student-dashboard.page.js';
import { StudentSearchPage } from '../features/student/pages/search/student-search.page.js';
import { StudentHistoryPage } from '../features/student/pages/history/student-history.page.js';
import { StudentReceiptPage } from '../features/student/pages/receipt/receipt.page.js';
import { StudentSettingsPage } from '../features/student/pages/settings/student-settings.page.js';

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

test('student history and receipt expose bounded page controllers', () => {
  assert.equal(StudentHistoryPage.name, 'StudentHistoryPage');
  assert.equal(typeof StudentHistoryPage.prototype.render, 'function');
  assert.equal(StudentReceiptPage.name, 'StudentReceiptPage');
  assert.equal(typeof StudentReceiptPage.prototype.render, 'function');
});

test('student settings exposes a feature-owned account page controller', () => {
  assert.equal(StudentSettingsPage.name, 'StudentSettingsPage');
  assert.equal(typeof StudentSettingsPage.prototype.load, 'function');
  assert.equal(typeof StudentSettingsPage.prototype.render, 'function');
});
