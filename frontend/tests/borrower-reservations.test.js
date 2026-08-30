import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve('frontend');
const read = (relativePath) => fs.readFileSync(path.join(root, relativePath), 'utf8');

test('student and teacher dashboards expose the self-service reservation mount', () => {
  for (const page of [
    'features/student/pages/dashboard/dashboard.html',
    'features/teacher/pages/dashboard/dashboard.html',
  ]) {
    const source = read(page);
    assert.match(source, /id="reservationQueue"/);
    assert.match(source, /reservation-queue/);
  }
});

test('borrower dashboard controllers load the role-specific reservation queue', () => {
  const student = read('features/student/pages/dashboard/student-dashboard.page.js');
  const teacher = read('features/teacher/pages/dashboard/teacher-dashboard.page.js');
  for (const [source, role] of [[student, 'student'], [teacher, 'teacher']]) {
    assert.match(source, /ReservationQueueComponent/);
    assert.match(source, /ReservationService/);
    assert.match(source, new RegExp(`role: ['"]${role}['"]`));
    assert.match(source, /reservationQueue\.load\(\)/);
  }
});
