import test from 'node:test';
import assert from 'node:assert/strict';
import {
  bootPage,
  pageNameFromDocument,
  registerPage,
} from '../app/bootstrap/page-registry.js';
import {
  clear,
  optionalElement,
  requiredElement,
  setText,
} from '../app/core/utils/dom.js';

test('page name comes from the body marker', () => {
  const document = { body: { dataset: { appPage: 'student-dashboard' } } };
  assert.equal(pageNameFromDocument(document), 'student-dashboard');
});

test('registered page factory starts with the supplied context', async () => {
  let received;
  registerPage('test-page', (context) => ({
    start() {
      received = context;
      return 'started';
    },
  }));

  assert.equal(await bootPage('test-page', { value: 1 }), 'started');
  assert.deepEqual(received, { value: 1 });
});

test('unknown page names fail clearly', async () => {
  await assert.rejects(() => bootPage('missing-page'), /Unknown frontend page/);
});

test('DOM helpers are bounded and text-safe', () => {
  const child = { textContent: '' };
  const root = {
    querySelector(selector) {
      return selector === '#child' ? child : null;
    },
  };

  assert.equal(requiredElement(root, '#child'), child);
  assert.equal(optionalElement(root, '#missing'), null);
  setText(child, '<safe>');
  assert.equal(child.textContent, '<safe>');
  clear({ children: [child], replaceChildren() { this.children = []; } });
});

test('requiredElement reports its missing selector', () => {
  assert.throws(() => requiredElement({ querySelector: () => null }, '#missing'), /#missing/);
});
