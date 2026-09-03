import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { BorrowerSearchPage } from '../app/shared/pages/borrower-search.page.js';

const root = path.resolve('frontend');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

test('borrower catalog templates expose recommendations and an all-books pager', () => {
  for (const file of [
    'features/student/pages/search/search.html',
    'features/teacher/pages/borrow/borrow.html',
  ]) {
    const source = read(file);
    assert.match(source, /id="recommendation-panel"/);
    assert.match(source, /id="book-recommendations"/);
    assert.match(source, /id="show-all-books"/);
    assert.match(source, /Show all books/);
    assert.match(source, /id="all-books-panel"/);
    assert.match(source, /id="catalog-range"/);
    assert.match(source, /id="book-pagination"/);
  }
});

test('shared catalog queries use five recommendations and ten catalog books', () => {
  const context = {
    params: new URLSearchParams('search=code&category_name=Programming'),
    pageSize: 10,
    recommendationSize: 5,
  };
  const recommendations = BorrowerSearchPage.prototype.recommendationQuery.call(context);
  const catalog = BorrowerSearchPage.prototype.catalogQuery.call(context, 3);

  assert.equal(recommendations.get('status'), 'Available');
  assert.equal(recommendations.get('page'), '1');
  assert.equal(recommendations.get('per_page'), '5');
  assert.equal(recommendations.get('sort'), 'created_at');
  assert.equal(recommendations.get('dir'), 'desc');
  assert.equal(catalog.get('search'), 'code');
  assert.equal(catalog.get('category_name'), 'Programming');
  assert.equal(catalog.get('page'), '3');
  assert.equal(catalog.get('per_page'), '10');
});

test('catalog range and pager state cover full, partial, and empty pages', () => {
  const context = { pageSize: 10 };
  const range = BorrowerSearchPage.prototype.rangeLabel.bind(context);
  const pager = BorrowerSearchPage.prototype.paginationState.bind(context);

  assert.equal(range(42, 1), '1-10 of 42');
  assert.equal(range(42, 5), '41-42 of 42');
  assert.equal(range(0, 1), '0-0 of 0');
  assert.deepEqual(pager(42, 1), { page: 1, pages: 5, previous: false, next: true });
  assert.deepEqual(pager(42, 5), { page: 5, pages: 5, previous: true, next: false });
});

test('catalog query detection distinguishes the landing view from filtered intent', () => {
  assert.equal(BorrowerSearchPage.prototype.hasCatalogQuery.call({ params: new URLSearchParams() }), false);
  assert.equal(BorrowerSearchPage.prototype.hasCatalogQuery.call({ params: new URLSearchParams('search=history') }), true);
  assert.equal(BorrowerSearchPage.prototype.hasCatalogQuery.call({ params: new URLSearchParams('status=Available') }), true);
});

test('active waitlist state keeps only actionable hold statuses', () => {
  const holds = [
    { title_id: 11, status: 'queued' },
    { title_id: 12, status: 'offered' },
    { title_id: 13, status: 'claimed' },
    { title_id: 14, status: 'fulfilled' },
    { title_id: 15, status: 'cancelled' },
    { title_id: 'invalid', status: 'queued' },
  ];

  const ids = BorrowerSearchPage.prototype.activeWaitlistTitleIds.call({}, holds);

  assert.deepEqual([...ids], [11, 12, 13]);
});

test('unavailable catalog actions offer a safe waitlist button', () => {
  const context = {
    classPrefix: 'student',
    waitlistedTitleIds: new Set(),
    escapeHtml: BorrowerSearchPage.prototype.escapeHtml,
    waitlistTitleId: BorrowerSearchPage.prototype.waitlistTitleId,
  };
  const action = BorrowerSearchPage.prototype.waitlistAction.call(context, {
    id: 21,
    title: '<Clean Code>',
  });

  assert.match(action, /Join waitlist/);
  assert.match(action, /data-waitlist-title-id="21"/);
  assert.match(action, /data-waitlist-title="&lt;Clean Code&gt;"/);
  assert.doesNotMatch(action, />Unavailable</);
});

test('waitlisted catalog actions are disabled and cannot be joined again', () => {
  const context = {
    classPrefix: 'teacher',
    waitlistedTitleIds: new Set([21]),
    escapeHtml: BorrowerSearchPage.prototype.escapeHtml,
    waitlistTitleId: BorrowerSearchPage.prototype.waitlistTitleId,
  };
  const action = BorrowerSearchPage.prototype.waitlistAction.call(context, {
    id: 21,
    title: 'Clean Code',
  });

  assert.match(action, /disabled/);
  assert.match(action, /On waitlist/);
  assert.doesNotMatch(action, /Join waitlist/);
});

test('waitlist confirmation cancels without joining', async () => {
  const calls = [];
  const button = {
    dataset: { waitlistTitleId: '31', waitlistTitle: 'Clean Code' },
    disabled: false,
    textContent: 'Join waitlist',
  };
  const context = {
    waitlistedTitleIds: new Set(),
    confirmation: {
      confirm: async () => false,
    },
    reservationService: {
      join: async () => calls.push('join'),
    },
    notify: (...args) => calls.push(args),
  };

  const result = await BorrowerSearchPage.prototype.confirmWaitlist.call(context, button);

  assert.equal(result, false);
  assert.deepEqual(calls, []);
  assert.equal(button.disabled, false);
  assert.equal(button.textContent, 'Join waitlist');
});

test('waitlist confirmation joins after acceptance and marks the button', async () => {
  const calls = [];
  const button = {
    dataset: { waitlistTitleId: '32', waitlistTitle: 'Refactoring' },
    disabled: false,
    textContent: 'Join waitlist',
  };
  const context = {
    waitlistedTitleIds: new Set(),
    confirmation: {
      confirm: async (options) => {
        await options.onConfirm();
        return true;
      },
    },
    reservationService: {
      join: async (titleId) => {
        calls.push(['join', titleId]);
        return { data: { message: 'You joined the queue for "Refactoring".' } };
      },
    },
    markWaitlisted: BorrowerSearchPage.prototype.markWaitlisted,
    notify: (...args) => calls.push(args),
  };

  const result = await BorrowerSearchPage.prototype.confirmWaitlist.call(context, button);

  assert.equal(result, true);
  assert.deepEqual(calls, [
    ['join', 32],
    ['You joined the queue for "Refactoring".', 'success'],
  ]);
  assert.equal(button.disabled, true);
  assert.equal(button.textContent, 'On waitlist');
  assert.equal(context.waitlistedTitleIds.has(32), true);
});

test('catalog waitlist wiring preserves role endpoints and toast mounts', () => {
  const student = read('features/student/pages/search/student-search.page.js');
  const teacher = read('features/teacher/pages/borrow/teacher-borrow.page.js');
  const reservationService = read('app/core/services/reservation.service.js');
  const studentTemplate = read('features/student/pages/search/search.html');
  const teacherTemplate = read('features/teacher/pages/borrow/borrow.html');

  assert.match(student, /role: ['"]student['"]/);
  assert.match(teacher, /role: ['"]teacher['"]/);
  assert.match(reservationService, /\/scan2borrow\/api\/\$\{this\.role\}\/holds/);
  assert.match(studentTemplate, /id="toast-host"/);
  assert.match(teacherTemplate, /id="toast-host"/);
});
