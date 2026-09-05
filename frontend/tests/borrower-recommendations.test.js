import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { BorrowerSearchPage } from '../app/shared/pages/borrower-search.page.js';

const root = path.resolve('frontend');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

test('student and teacher catalogs declare role-scoped recommendation APIs', () => {
  const student = read('features/student/pages/search/student-search.page.js');
  const teacher = read('features/teacher/pages/borrow/teacher-borrow.page.js');

  assert.match(student, /recommendationApi: ['"]\/scan2borrow\/api\/student\/recommendations['"]/);
  assert.match(student, /searchHistoryApi: ['"]\/scan2borrow\/api\/student\/search-history['"]/);
  assert.match(teacher, /recommendationApi: ['"]\/scan2borrow\/api\/teacher\/recommendations['"]/);
  assert.match(teacher, /searchHistoryApi: ['"]\/scan2borrow\/api\/teacher\/search-history['"]/);
});

test('recommendation panels expose supporting-copy mounts for personalized and fallback states', () => {
  for (const file of [
    'features/student/pages/search/search.html',
    'features/teacher/pages/borrow/borrow.html',
  ]) {
    assert.match(read(file), /id="recommendation-supporting-copy"/);
  }
  assert.equal(BorrowerSearchPage.prototype.recommendationCopy.call({}, true), 'Based on your searches.');
  assert.equal(BorrowerSearchPage.prototype.recommendationCopy.call({}, false), 'Newly added available books.');
});

test('recordSearch posts deliberate non-empty searches with the current CSRF token', async () => {
  const calls = [];
  const previousFetch = globalThis.fetch;
  globalThis.fetch = async (url, options) => {
    calls.push({ url, options });
    return { ok: true, json: async () => ({ ok: true }) };
  };

  try {
    await BorrowerSearchPage.prototype.recordSearch.call({
      searchHistoryApi: '/scan2borrow/api/student/search-history',
      csrf: 'csrf-token',
    }, '  Clean Code  ');
    await BorrowerSearchPage.prototype.recordSearch.call({
      searchHistoryApi: '/scan2borrow/api/student/search-history',
      csrf: 'csrf-token',
    }, '   ');
  } finally {
    globalThis.fetch = previousFetch;
  }

  assert.equal(calls.length, 1);
  assert.equal(calls[0].url, '/scan2borrow/api/student/search-history');
  assert.equal(calls[0].options.method, 'POST');
  assert.equal(calls[0].options.body.toString(), 'search=Clean+Code&csrf=csrf-token');
});

test('recommendation loader uses the personalized API without catalog query parameters', () => {
  const source = read('app/shared/pages/borrower-search.page.js');
  assert.match(source, /fetch\(this\.recommendationApi/);
  assert.match(source, /personalized/);
  assert.doesNotMatch(source, /recommendationQuery\(\)\.toString\(\)/);
  assert.match(source, /skipSearchTracking/);
});
