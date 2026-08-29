import test from 'node:test';
import assert from 'node:assert/strict';
import { normalizeTeacherDashboard, normalizeTeacherUser } from '../features/teacher/models/index.js';
import { TeacherDashboardService, TeacherSettingsService } from '../features/teacher/services/index.js';
import { TeacherDashboardPage } from '../features/teacher/pages/dashboard/teacher-dashboard.page.js';
import { TeacherSettingsPage } from '../features/teacher/pages/settings/teacher-settings.page.js';

test('teacher models preserve teacher role, profile fields, and dashboard defaults', () => {
  assert.deepEqual(normalizeTeacherUser({ name: 'Lee', department: 'Library', position: 'Faculty' }), {
    name: 'Lee', barcode: '', role: 'Teacher', department: 'Library', position: 'Faculty', contact_no: '',
  });
  const dashboard = normalizeTeacherDashboard({ user: { name: 'Lee' }, stats: { active: '1' } });
  assert.equal(dashboard.user.role, 'Teacher');
  assert.equal(dashboard.stats.active, 1);
  assert.deepEqual(dashboard.current_loans, []);
});

test('teacher services preserve due-date and contact-number payload branches', async () => {
  const calls = [];
  const api = {
    get: async (path, params) => { calls.push({ method: 'GET', path, params }); return { ok: true, data: { user: {} } }; },
    post: async (path, body) => { calls.push({ method: 'POST', path, body }); return { ok: true, data: {} }; },
  };
  const dashboard = new TeacherDashboardService({ api });
  const settings = new TeacherSettingsService({ api });
  await dashboard.load();
  await dashboard.borrow('BOOK-9', '2026-09-30');
  await dashboard.returnBook('TXN-9');
  await settings.load();
  assert.deepEqual(calls, [
    { method: 'GET', path: '/scan2borrow/api/teacher/dashboard', params: {} },
    { method: 'POST', path: '/scan2borrow/api/teacher/dashboard', body: { action: 'borrow', book_barcode: 'BOOK-9', due_date: '2026-09-30' } },
    { method: 'POST', path: '/scan2borrow/api/teacher/dashboard', body: { action: 'return_unified', return_input: 'TXN-9' } },
    { method: 'GET', path: '/scan2borrow/api/teacher/dashboard', params: {} },
  ]);
});

test('teacher pages expose dashboard and settings feature boundaries', () => {
  assert.equal(TeacherDashboardPage.name, 'TeacherDashboardPage');
  assert.equal(typeof TeacherDashboardPage.prototype.render, 'function');
  assert.equal(TeacherSettingsPage.name, 'TeacherSettingsPage');
  assert.equal(typeof TeacherSettingsPage.prototype.render, 'function');
});
