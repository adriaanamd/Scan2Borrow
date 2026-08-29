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

test('student surface styles remain scoped away from navigation', () => {
  const sources = [
    read('assets/css/student-library-surfaces.css'),
    read('assets/css/student-search.css'),
    read('assets/css/student-history.css'),
  ];
  for (const source of sources) {
    assert.doesNotMatch(source, /\.sidebar|\[data-app-navbar\]/);
  }
});
