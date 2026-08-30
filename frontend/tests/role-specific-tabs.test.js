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

test('student catalog and history templates are explicitly student-owned', () => {
  for (const relativePath of [
    'features/student/pages/search/search.html',
    'features/student/pages/history/history.html',
  ]) {
    const source = read(relativePath);
    assert.match(source, /data-navbar-role="student"/);
    assert.match(source, /data-app-page="student-(?:search|history)"/);
  }
});

test('borrower page controllers never use the cached role to cross role boundaries', () => {
  for (const relativePath of [
    'features/student/pages/search/student-search.page.js',
    'features/student/pages/history/student-history.page.js',
  ]) {
    const source = read(relativePath);
    assert.doesNotMatch(source, /roleFromPath\(\) \|\| this\.cachedRole\(\)/);
    assert.doesNotMatch(source, /sessionStorage\?\.getItem\("scan2borrow\.nav\.role"\)/);
  }
});

test('teacher catalog and history have independent templates and navigation ownership', () => {
  const catalog = read('features/teacher/pages/borrow/borrow.html');
  const history = read('features/teacher/pages/history/history.html');

  assert.match(catalog, /data-app-page="teacher-search"/);
  assert.match(catalog, /data-navbar-role="teacher"/);
  assert.match(history, /data-app-page="teacher-history"/);
  assert.match(history, /data-navbar-role="teacher"/);
});
