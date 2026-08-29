import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

test('architecture test runner uses native modules', () => {
  assert.equal(fs.existsSync('frontend'), true);
  const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
  assert.equal(packageJson.type, 'module');
});
