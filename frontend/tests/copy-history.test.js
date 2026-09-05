import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { CopyHistoryPage } from '../features/staff/pages/copy-history/copy-history.page.js';

class Element {
  constructor() {
    this.children = [];
    this.hidden = false;
    this.dataset = {};
    this.value = '';
    this.textContent = '';
    this.className = '';
  }

  append(...children) { this.children.push(...children); }
  appendChild(child) { this.children.push(child); return child; }
  replaceChildren(...children) { this.children = children; }
  addEventListener() {}
  removeEventListener() {}
  setAttribute() {}
}

function fixtureDocument() {
  const ids = ['copy-history-form', 'copy-history-barcode', 'copy-history-copy', 'copy-history-timeline', 'copy-history-status', 'copy-history-title', 'copy-history-author', 'copy-history-barcode-value', 'copy-history-accession', 'copy-history-location', 'copy-history-current-status'];
  const elements = new Map(ids.map((id) => [id, new Element()]));
  return {
    getElementById(id) { return elements.get(id) || null; },
    createElement() { return new Element(); },
    elements,
  };
}

test('copy history page requests a barcode and renders safe event text', async () => {
  const document = fixtureDocument();
  const calls = [];
  const page = new CopyHistoryPage(document, {
    api: {
      get: async (pathName, params) => {
        calls.push({ path: pathName, params });
        return {
          ok: true,
          data: {
            copy: { title: 'Clean Code', barcode: 'BC-1', accession_no: 'ACC-1', status: 'Lost', location: 'Floor 2' },
            events: [{ label: 'Status changed', occurred_at: '2026-08-31 14:32:00', actor: 'Staff One', from_status: 'Available', to_status: 'Lost', reason: '<script>alert(1)</script>' }],
          },
        };
      },
    },
    window: { history: { replaceState() {} } },
  });

  await page.load('BC-1');

  assert.deepEqual(calls, [{ path: '/scan2borrow/api/staff/copy-history', params: { barcode: 'BC-1' } }]);
  assert.equal(document.elements.get('copy-history-title').textContent, 'Clean Code');
  assert.equal(document.elements.get('copy-history-timeline').children.length, 1);
  assert.equal(document.elements.get('copy-history-timeline').children[0].children[1].children[3].textContent, 'Reason: <script>alert(1)</script>');
  assert.equal(document.elements.get('copy-history-timeline').children[0].children[1].children[3].children.length, 0);
});

test('copy history page template and styles keep the dedicated staff surface contract', () => {
  const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
  const template = fs.readFileSync(path.join(root, 'features/staff/pages/copy-history/copy-history.html'), 'utf8');
  const styles = fs.readFileSync(path.join(root, 'features/staff/pages/copy-history/copy-history.css'), 'utf8');
  assert.match(template, /data-app-page="staff-copy-history"/);
  assert.match(template, /data-scan-target="copy-history-barcode"/);
  assert.match(template, /copy-history-timeline/);
  assert.match(template, /frontend\/assets\/js\/core\/icons\.js/);
  assert.match(styles, /#002fa7/i);
  assert.match(styles, /copy-history-event/);
});
