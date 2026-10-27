import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = (relativePath) => fs.readFileSync(path.join(root, relativePath), 'utf8');

const surfaces = [
  {
    name: 'student dashboard',
    template: 'features/student/pages/dashboard/dashboard.html',
    controller: 'features/student/pages/dashboard/student-dashboard.page.js',
    lookupApi: '/scan2borrow/api/student/borrow/lookup',
  },
  {
    name: 'student catalog',
    template: 'features/student/pages/search/search.html',
    controller: 'features/student/pages/search/student-search.page.js',
    lookupApi: '/scan2borrow/api/student/borrow/lookup',
  },
  {
    name: 'teacher dashboard',
    template: 'features/teacher/pages/dashboard/dashboard.html',
    controller: 'features/teacher/pages/dashboard/teacher-dashboard.page.js',
    lookupApi: '/scan2borrow/api/teacher/borrow/lookup',
  },
  {
    name: 'teacher catalog',
    template: 'features/teacher/pages/borrow/borrow.html',
    controller: 'features/teacher/pages/borrow/teacher-borrow.page.js',
    lookupApi: '/scan2borrow/api/teacher/borrow/lookup',
  },
];

function elementBlock(source, element, id) {
  const match = source.match(
    new RegExp(`<${element}\\b[^>]*\\bid="${id}"[^>]*>[\\s\\S]*?<\\/${element}>`),
  );
  assert.ok(match, `Expected ${element}#${id} to exist`);
  return match[0];
}

function elementTag(source, element, id) {
  const match = source.match(
    new RegExp(`<${element}\\b[^>]*\\bid="${id}"[^>]*>`),
  );
  assert.ok(match, `Expected ${element}#${id} to exist`);
  return match[0];
}

test('borrow barcode inputs remain typeable and use a separate camera action', () => {
  for (const surface of surfaces) {
    const source = read(surface.template);
    const input = elementTag(source, 'input', 'bulk-scan-barcode');
    const addButton = elementBlock(source, 'button', 'bulk-scan-add');
    const cameraButton = elementBlock(source, 'button', 'bulk-scan-camera');

    assert.doesNotMatch(input, /data-scan-target/, `${surface.name} input must remain typeable`);
    assert.doesNotMatch(addButton, /data-scan-target/, `${surface.name} manual action must not open the scanner`);
    assert.match(addButton, /Add copy/, `${surface.name} must expose a manual add action`);
    assert.match(cameraButton, /data-scan-target="bulk-scan-barcode"/);
    assert.match(cameraButton, /aria-label="Scan with camera"/);
    assert.match(cameraButton, /Scan with camera/);
  }
});

test('borrow controllers keep Enter handling and role-specific lookup APIs', () => {
  const sharedCatalogController = read('app/shared/pages/borrower-search.page.js');
  assert.match(sharedCatalogController, /event\.key === "Enter"/);
  assert.match(sharedCatalogController, /lookupAndAdd\(input\.value\.trim\(\)\)/);

  for (const surface of surfaces) {
    const controller = read(surface.controller);
    assert.ok(controller.includes(surface.lookupApi), surface.name);
  }

  for (const controllerPath of [
    'features/student/pages/dashboard/student-dashboard.page.js',
    'features/teacher/pages/dashboard/teacher-dashboard.page.js',
  ]) {
    const controller = read(controllerPath);
    assert.match(controller, /event\.key === "Enter"/);
    assert.match(controller, /lookupAndAdd\(input\.value\.trim\(\)\)/);
  }
});
