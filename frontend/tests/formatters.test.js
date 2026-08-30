import test from 'node:test';
import assert from 'node:assert/strict';
import { escapeHtml, safePath } from '../app/core/utils/security.js';
import { formatDate, formatPeso, statusClass } from '../app/core/utils/formatters.js';

test('shared utilities escape text and preserve internal paths', () => {
  assert.equal(escapeHtml(`<safe> & "quoted"`), '&lt;safe&gt; &amp; &quot;quoted&quot;');
  assert.equal(safePath('/scan2borrow/student/search'), '/scan2borrow/student/search');
  assert.equal(safePath('javascript:' + 'alert' + '(1)'), '#');
});

test('shared formatters preserve current display conventions', () => {
  assert.equal(formatPeso(12.5), '₱12.50');
  assert.equal(formatDate('not-a-date'), 'not-a-date');
  assert.equal(statusClass('Borrowed'), 'primary');
  assert.equal(statusClass('Overdue'), 'danger');
  assert.equal(statusClass('Pending'), 'warning text-dark');
  assert.equal(statusClass('Returned'), 'success');
});
