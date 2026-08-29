import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const frontendRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

test('legacy page and page-controller directories are retired', () => {
  for (const relativePath of ['pages', 'assets/js/pages', 'assets/js/guest']) {
    assert.equal(
      fs.existsSync(path.join(frontendRoot, relativePath)),
      false,
      `Retired frontend directory still exists: frontend/${relativePath}`,
    );
  }
});

test('shared core helpers remain available after legacy cleanup', () => {
  for (const filename of ['app-navbar.js', 'auth-brand.js', 'icons.js', 'media.js', 'scanner.js']) {
    assert.equal(fs.existsSync(path.join(frontendRoot, 'assets/js/core', filename)), true, filename);
  }
});
