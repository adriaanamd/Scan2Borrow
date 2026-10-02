import test from 'node:test';
import assert from 'node:assert/strict';
import { BorrowerHistoryPage } from '../app/shared/pages/borrower-history.page.js';

test('normalizes multi-word history statuses into a valid CSS class token', () => {
  const page = Object.create(BorrowerHistoryPage.prototype);
  page.classPrefix = 'student-history';

  assert.equal(
    page.statusClass('return verification pending'),
    'student-history-status--return-verification-pending',
  );
});
