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

const stylesheet = fs.readFileSync(stylesheetPath, 'utf8');

test('borrower dashboard content layering excludes modal roots', () => {
  assert.match(
    stylesheet,
    /\.borrower-dashboard \.content > :not\(\.modal\)\s*\{\s*position:\s*relative;\s*z-index:\s*1;\s*\}/s,
  );
  assert.doesNotMatch(stylesheet, /\.borrower-dashboard \.content > \*\s*\{/s);
});
