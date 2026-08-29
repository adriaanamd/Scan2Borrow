import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const testsDirectory = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(testsDirectory, '..');
const featureRoot = path.join(frontendRoot, 'features');

function featureTemplates(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const entryPath = path.join(directory, entry.name);
    if (entry.isDirectory()) return featureTemplates(entryPath);
    return entry.name.endsWith('.html') ? [entryPath] : [];
  });
}

test('every served feature template has one browser-loadable module entry', () => {
  const templates = featureTemplates(featureRoot);
  assert.ok(templates.length > 0);

  for (const template of templates) {
    const source = fs.readFileSync(template, 'utf8');
    const relativeTemplate = path.relative(frontendRoot, template);
    const scriptTags = [...source.matchAll(/<script\b[^>]*>/gi)].map(([tag]) => tag);
    const moduleTags = scriptTags.filter((tag) => /\btype=["']module["']/i.test(tag));

    assert.equal((source.match(/data-app-page=/g) || []).length, 1, relativeTemplate);
    assert.equal(moduleTags.length, 1, relativeTemplate);
    assert.doesNotMatch(source, /\/frontend\/assets\/js\/(?:pages|guest)\//, relativeTemplate);

    const moduleSource = moduleTags[0].match(/\bsrc=["']([^"']+)["']/i)?.[1] || '';
    assert.match(moduleSource, /^\/scan2borrow\/frontend\/(?:app|features)\/.+\.js$/i, relativeTemplate);

    const modulePath = path.join(frontendRoot, moduleSource.replace('/scan2borrow/frontend/', ''));
    assert.equal(fs.existsSync(modulePath), true, `${relativeTemplate} -> ${moduleSource}`);
  }
});
