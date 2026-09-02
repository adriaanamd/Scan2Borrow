import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const testDirectory = path.dirname(fileURLToPath(import.meta.url));
const stylesheetPath = path.resolve(
  testDirectory,
  '..',
  'assets',
  'css',
  'borrower-dashboards.css',
);
const studentLibraryStylesheetPath = path.resolve(
  testDirectory,
  '..',
  'assets',
  'css',
  'student-library-surfaces.css',
);
const teacherLibraryStylesheetPath = path.resolve(
  testDirectory,
  '..',
  'assets',
  'css',
  'teacher-library-surfaces.css',
);

const stylesheet = fs.readFileSync(stylesheetPath, 'utf8');
const studentLibraryStylesheet = fs.readFileSync(studentLibraryStylesheetPath, 'utf8');
const teacherLibraryStylesheet = fs.readFileSync(teacherLibraryStylesheetPath, 'utf8');

test('borrower dashboard content layering excludes modal roots', () => {
  assert.match(
    stylesheet,
    /\.borrower-dashboard \.content > :not\(\.modal\)\s*\{\s*position:\s*relative;\s*z-index:\s*1;\s*\}/s,
  );
  assert.doesNotMatch(stylesheet, /\.borrower-dashboard \.content > \*\s*\{/s);
});

test('student and teacher library content layering excludes modal roots', () => {
  assert.match(
    studentLibraryStylesheet,
    /\.student-library-page > :not\(\.modal\)\s*\{\s*position:\s*relative;\s*z-index:\s*1;\s*\}/s,
  );
  assert.doesNotMatch(studentLibraryStylesheet, /\.student-library-page > \*\s*\{/s);

  assert.match(
    teacherLibraryStylesheet,
    /\.teacher-library-page > :not\(\.modal\)\s*\{\s*position:\s*relative;\s*z-index:\s*1;\s*\}/s,
  );
  assert.doesNotMatch(teacherLibraryStylesheet, /\.teacher-library-page > \*\s*\{/s);
});
