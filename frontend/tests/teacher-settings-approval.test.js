import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { TeacherSettingsPage } from '../features/teacher/pages/settings/teacher-settings.page.js';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', 'features', 'teacher', 'pages', 'settings');

test('teacher settings exposes editable request fields and a protected staff ID', () => {
  const source = fs.readFileSync(path.join(root, 'settings.html'), 'utf8');
  for (const field of ['firstname', 'middlename', 'lastname', 'email', 'contact_no', 'course', 'year_level', 'department', 'position']) assert.match(source, new RegExp(`name="${field}"`));
  assert.match(source, /id="teacher-photo"/);
  assert.match(source, /id="teacher-barcode" readonly/);
  assert.match(source, /teacher-request-status/);
});

test('teacher settings controller stays feature-owned and role-specific', () => {
  for (const method of ['load', 'render', 'renderRequestStatus', 'submit', 'readPhoto', 'escapeHtml']) assert.equal(typeof TeacherSettingsPage.prototype[method], 'function', method);
  const source = fs.readFileSync(path.join(root, 'teacher-settings.page.js'), 'utf8');
  assert.match(source, /TeacherProfileChangeService/);
});
