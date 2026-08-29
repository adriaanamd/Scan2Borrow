import test from 'node:test';
import assert from 'node:assert/strict';
import * as student from '../features/student/index.js';
import * as teacher from '../features/teacher/index.js';

test('student and teacher feature barrels expose only public models and services', () => {
  assert.deepEqual(Object.keys(student).sort(), [
    'StudentDashboardService', 'StudentSearchService', 'StudentSettingsService',
    'normalizeBook', 'normalizeDashboard', 'normalizeLoan', 'normalizeUser',
  ]);
  assert.deepEqual(Object.keys(teacher).sort(), [
    'TeacherDashboardService', 'TeacherSettingsService',
    'normalizeTeacherDashboard', 'normalizeTeacherUser',
  ]);
});
