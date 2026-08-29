import test from 'node:test';
import assert from 'node:assert/strict';
import { guestPageNames } from '../app/bootstrap/guest-page.js';

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
