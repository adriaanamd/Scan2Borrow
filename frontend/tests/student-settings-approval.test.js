import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { StudentSettingsPage } from '../features/student/pages/settings/student-settings.page.js';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', 'features', 'student', 'pages', 'settings');

test('student settings exposes editable request fields and a protected barcode', () => {
  const source = fs.readFileSync(path.join(root, 'settings.html'), 'utf8');
  for (const field of ['firstname', 'middlename', 'lastname', 'email', 'contact_no', 'course', 'year_level', 'department', 'position']) assert.match(source, new RegExp(`name="${field}"`));
  assert.match(source, /id="student-photo"/);
  assert.match(source, /id="student-barcode" readonly/);
  assert.match(source, /student-request-status/);
});

test('student settings controller exposes request and safe rendering boundaries', () => {
  for (const method of ['load', 'render', 'renderRequestStatus', 'submit', 'readPhoto', 'escapeHtml']) assert.equal(typeof StudentSettingsPage.prototype[method], 'function', method);
  const source = fs.readFileSync(path.join(root, 'student-settings.page.js'), 'utf8');
  assert.match(source, /profile-request/);
  assert.match(source, /safePhotoPath/);
});

test('student settings owns an Organic presentation without touching teacher settings', () => {
  const studentCss = fs.readFileSync(path.join(root, 'settings.css'), 'utf8');
  const teacherCss = fs.readFileSync(
    path.resolve(root, '../../../teacher/pages/settings/settings.css'),
    'utf8',
  );

  assert.match(studentCss, /\[data-page="student-settings"\][\s\S]*font-family:\s*Epilogue/);
  assert.match(studentCss, /\[data-page="student-settings"\][\s\S]*font-family:\s*Fraunces/);
  assert.match(studentCss, /\[data-page="student-settings"\][\s\S]*border-radius:\s*22px/);
  assert.match(studentCss, /@media\s*\(max-width:\s*320px\)/);
  assert.doesNotMatch(studentCss, /data-page="teacher-settings"/);
  assert.match(teacherCss, /data-page="teacher-settings"/);
});
