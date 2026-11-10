import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const testDirectory = path.dirname(fileURLToPath(import.meta.url));
const navbarPath = path.resolve(testDirectory, '..', 'assets', 'js', 'core', 'app-navbar.js');
const navbarSource = fs.readFileSync(navbarPath, 'utf8');

function loadNavbar(window, document) {
  const context = vm.createContext({ document, window, fetch: window.fetch });
  vm.runInContext(`${navbarSource}\nwindow.AppNavbar = AppNavbar;`, context, { filename: navbarPath });
  return window.AppNavbar;
}

function createPanelFixture() {
  const topbar = {
    appended: [],
    appendChild(child) {
      this.appended.push(child);
      child.parentNode = this;
      return child;
    },
  };
  const app = { querySelector: (selector) => selector === '.topbar' ? topbar : null };
  const root = {
    dataset: { navbarRole: 'student' },
    closest: (selector) => selector === '.app' ? app : null,
    ownerDocument: null,
  };
  const document = {
    addEventListener() {},
    createElement: () => ({
      className: '',
      innerHTML: '',
      appendChild() {},
      addEventListener() {},
      querySelector() { return null; },
    }),
    querySelector: () => ({ content: 'csrf-token' }),
  };
  root.ownerDocument = document;
  const window = {
    location: { pathname: '/scan2borrow/student/dashboard' },
    fetch: async () => ({ json: async () => ({ ok: true, data: { notifications: [], unread_count: 0 } }) }),
  };

  return { document, root, topbar, window };
}

test('borrower navbar has role-specific notification panel contracts', () => {
  assert.match(navbarSource, /setupBorrowerNotifications\(role\)/);
  assert.match(navbarSource, /\/scan2borrow\/api\/.*notifications/);
  assert.match(navbarSource, /borrower-notifications__badge/);
  assert.match(navbarSource, /Mark all as read/);
  assert.match(navbarSource, /No notifications yet/);
  assert.match(navbarSource, /textContent/);
  assert.match(navbarSource, /read_all/);
});

test('borrower panel mounts only for student and teacher roles', () => {
  const fixture = createPanelFixture();
  const AppNavbar = loadNavbar(fixture.window, fixture.document);
  const navbar = new AppNavbar(fixture.root);

  navbar.setupBorrowerNotifications('student');

  assert.equal(fixture.topbar.appended.length, 1);
  assert.equal(fixture.topbar.appended[0].className, 'borrower-notifications');

  const teacherFixture = createPanelFixture();
  const TeacherNavbar = loadNavbar(teacherFixture.window, teacherFixture.document);
  const teacherNavbar = new TeacherNavbar(teacherFixture.root);
  teacherNavbar.setupBorrowerNotifications('teacher');
  assert.equal(teacherNavbar.notificationPath, '/scan2borrow/api/teacher/notifications');

  const staffFixture = createPanelFixture();
  const staffNavbar = new (loadNavbar(staffFixture.window, staffFixture.document))(staffFixture.root);
  staffNavbar.setupBorrowerNotifications('admin');
  assert.equal(staffFixture.topbar.appended.length, 0);
});

test('notification message rendering is protected from HTML injection', () => {
  assert.match(navbarSource, /message\.textContent/);
  assert.doesNotMatch(navbarSource, /notification\.message.*innerHTML/);
});
