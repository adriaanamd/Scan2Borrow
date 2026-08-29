import test from 'node:test';
import assert from 'node:assert/strict';
import { guestPageNames } from '../app/bootstrap/guest-page.js';
import { GuestDashboardPage } from '../features/guest/pages/dashboard/guest-dashboard.page.js';
import { GuestProfilePage } from '../features/guest/pages/profile/guest-profile.page.js';
import { GuestBrowsePage } from '../features/guest/pages/browse/guest-browse.page.js';
import { GuestBorrowedPage } from '../features/guest/pages/borrowed/guest-borrowed.page.js';
import { GuestHistoryPage } from '../features/guest/pages/history/guest-history.page.js';
import { GuestBorrowRequestPage } from '../features/guest/pages/borrow-request/guest-borrow-request.page.js';
import { GuestReturnPage } from '../features/guest/pages/return/guest-return.page.js';
import { GuestPassPage } from '../features/guest/pages/pass/guest-pass.page.js';
import { GuestReceiptPage } from '../features/guest/pages/receipt/guest-receipt.page.js';

test('guest bootstrap registers every guest route and excludes borrower/staff auth pages', () => {
  assert.deepEqual(guestPageNames, [
    'guest-registration',
    'guest-otp',
    'profile-otp',
    'guest-dashboard',
    'guest-profile',
    'guest-browse',
    'guest-borrowed',
    'guest-history',
    'guest-borrow-request',
    'guest-return',
    'guest-pass',
    'guest-receipt',
  ]);
  assert.equal(guestPageNames.includes('login'), false);
  assert.equal(guestPageNames.includes('staff-dashboard'), false);
});

test('guest dashboard and profile expose feature-owned page controllers', () => {
  assert.equal(GuestDashboardPage.name, 'GuestDashboardPage');
  assert.equal(typeof GuestDashboardPage.prototype.render, 'function');
  assert.equal(typeof GuestDashboardPage.prototype.renderSecurity, 'function');
  assert.equal(GuestProfilePage.name, 'GuestProfilePage');
  assert.equal(typeof GuestProfilePage.prototype.load, 'function');
  assert.equal(typeof GuestProfilePage.prototype.submit, 'function');
});

test('guest browse and borrowed pages expose catalog boundaries', () => {
  assert.equal(GuestBrowsePage.name, 'GuestBrowsePage');
  assert.equal(typeof GuestBrowsePage.prototype.render, 'function');
  assert.equal(typeof GuestBrowsePage.prototype.load, 'function');
  assert.equal(GuestBorrowedPage.name, 'GuestBorrowedPage');
  assert.equal(typeof GuestBorrowedPage.prototype.render, 'function');
});

test('guest history and borrow request expose protected interaction boundaries', () => {
  assert.equal(GuestHistoryPage.name, 'GuestHistoryPage');
  assert.equal(typeof GuestHistoryPage.prototype.render, 'function');
  assert.equal(GuestBorrowRequestPage.name, 'GuestBorrowRequestPage');
  assert.equal(typeof GuestBorrowRequestPage.prototype.submit, 'function');
});

test('guest return and pass expose photo verification and print boundaries', () => {
  assert.equal(GuestReturnPage.name, 'GuestReturnPage');
  assert.equal(typeof GuestReturnPage.prototype.submit, 'function');
  assert.equal(GuestPassPage.name, 'GuestPassPage');
  assert.equal(typeof GuestPassPage.prototype.load, 'function');
});

test('guest receipt exposes a service-backed printable page boundary', () => {
  assert.equal(GuestReceiptPage.name, 'GuestReceiptPage');
  assert.equal(typeof GuestReceiptPage.prototype.load, 'function');
  assert.equal(typeof GuestReceiptPage.prototype.render, 'function');
});
