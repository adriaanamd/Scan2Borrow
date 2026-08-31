import test from 'node:test';
import assert from 'node:assert/strict';
import { normalizeStudentProfileChange } from '../features/student/models/profile-change.model.js';
import { StudentProfileChangeService } from '../features/student/services/profile-change.service.js';
import { normalizeTeacherProfileChange } from '../features/teacher/models/profile-change.model.js';
import { TeacherProfileChangeService } from '../features/teacher/services/profile-change.service.js';

test('profile clients preserve role-specific endpoints and FormData payloads', async () => {
  const calls = [];
  const api = {
    get: async (path, params) => { calls.push({ method: 'GET', path, params }); return { ok: true }; },
    post: async (path, body) => { calls.push({ method: 'POST', path, body }); return { ok: true }; },
  };
  const form = new FormData(); form.set('firstname', 'Grace');
  await new StudentProfileChangeService({ api }).load();
  await new StudentProfileChangeService({ api }).submit(form);
  await new TeacherProfileChangeService({ api }).load();
  await new TeacherProfileChangeService({ api }).submit(form);
  assert.equal(calls[0].path, '/scan2borrow/api/student/settings');
  assert.equal(calls[1].body, form);
  assert.equal(calls[2].path, '/scan2borrow/api/teacher/settings');
  assert.equal(calls[3].body, form);
});

test('profile models preserve pending status and default absent values', () => {
  const data = { profile: { firstname: 'Grace' }, pending_request: { id: '41', status: 'pending', requested_values: { firstname: 'Ada' } } };
  assert.equal(normalizeStudentProfileChange(data).profile.lastname, '');
  assert.equal(normalizeStudentProfileChange(data).pending_request.id, 41);
  assert.equal(normalizeTeacherProfileChange(data).pending_request.status, 'pending');
});
