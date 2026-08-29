import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const testsDirectory = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(testsDirectory, '..');
const searchTemplate = path.join(root, 'features', 'student', 'pages', 'search', 'search.html');
const historyTemplate = path.join(root, 'features', 'student', 'pages', 'history', 'history.html');

function read(relativePath) {
  return fs.readFileSync(path.join(root, relativePath), 'utf8');
}

test('student library pages share the established visual surface boundary', () => {
  for (const templatePath of [searchTemplate, historyTemplate]) {
    const source = fs.readFileSync(templatePath, 'utf8');
    assert.match(source, /data-app-page="student-(?:search|history)"/);
    assert.match(source, /student-library-surfaces\.css/);
    assert.match(source, /student-library-page/);
    assert.match(source, /<aside class="sidebar" data-app-navbar data-navbar-role="session">/);
    assert.match(source, /app-navbar\.js/);
  }
});

test('student search and history retain their existing behavior boundaries', () => {
  const search = read('features/student/pages/search/search.html');
  const history = read('features/student/pages/history/history.html');
  assert.match(search, /id="searchForm"/);
  assert.match(search, /id="book-results"/);
  assert.match(search, /id="book-count"/);
  assert.match(search, /id="active-filters"/);
  assert.match(search, /id="borrowModal"/);
  assert.match(search, /student-search\.page\.js/);
  assert.match(history, /id="history-body"/);
  assert.match(history, /student-history\.page\.js/);
  assert.match(history, /<th>Code<\/th>[\s\S]*<th>Fine<\/th>/);
});

test('student search exposes catalog masthead, filter, result, and borrow boundaries', () => {
  const source = read('features/student/pages/search/search.html');
  assert.match(source, /student-library-masthead/);
  assert.match(source, /student-search-filters/);
  assert.match(source, /student-search-results/);
  assert.match(source, /student-search-results__header/);
  assert.match(source, /student-search-results__state/);
});

test('student surface styles remain scoped away from navigation', () => {
  const sources = [
    read('assets/css/student-library-surfaces.css'),
    read('assets/css/student-search.css'),
  ];
  for (const source of sources) {
    assert.doesNotMatch(source, /\.sidebar|\[data-app-navbar\]/);
  }
});

test('student search controller exposes styled empty, error, and card boundaries', () => {
  const source = read('features/student/pages/search/student-search.page.js');
  assert.match(source, /student-library-state/);
  assert.match(source, /student-search-filter-chip/);
  assert.match(source, /student-search-result/);
});

test('student search cards use the library surface motion and quantity hierarchy', () => {
  const source = read('assets/css/student-search.css');
  assert.match(source, /student-search-card/);
  assert.match(source, /student-search-result/);
  assert.match(source, /prefers-reduced-motion/);
  assert.match(source, /book-card:focus-visible/);
});

test('student history exposes a styled borrowing ledger boundary', () => {
  const template = read('features/student/pages/history/history.html');
  const source = read('features/student/pages/history/student-history.page.js');
  const styles = read('assets/css/student-history.css');
  assert.match(template, /student-history-masthead/);
  assert.match(template, /student-history-ledger/);
  assert.match(template, /student-history-table/);
  assert.match(source, /student-history-row/);
  assert.match(source, /student-history-status/);
  assert.match(styles, /student-history-ledger/);
  assert.match(styles, /student-history-row--overdue/);
});

test('student search keeps filter presentation in scoped classes', () => {
  const template = read('features/student/pages/search/search.html');
  const styles = read('assets/css/student-library-surfaces.css');
  assert.match(template, /student-search-filter-field/);
  assert.match(template, /student-search-clear/);
  assert.match(template, /student-library-state__icon/);
  assert.match(styles, /student-search-filter-field/);
  assert.match(styles, /student-search-clear/);
  assert.match(styles, /student-library-state__icon/);
});
