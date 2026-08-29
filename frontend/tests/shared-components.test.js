import test from 'node:test';
import assert from 'node:assert/strict';
import { EmptyStateComponent } from '../app/shared/components/empty-state/empty-state.component.js';
import { LoadingStateComponent } from '../app/shared/components/loading-state/loading-state.component.js';
import { ToastHostComponent } from '../app/shared/components/toast-host/toast-host.component.js';
import { DataTableComponent } from '../app/shared/components/data-table/data-table.component.js';

test('loading and empty components control their bounded roots', () => {
  const loadingRoot = { hidden: true, textContent: '' };
  const loading = new LoadingStateComponent(loadingRoot);
  loading.show('Loading books');
  assert.equal(loadingRoot.hidden, false);
  assert.equal(loadingRoot.textContent, 'Loading books');
  loading.hide();
  assert.equal(loadingRoot.hidden, true);

  const emptyRoot = { hidden: false, textContent: '' };
  const empty = new EmptyStateComponent(emptyRoot);
  empty.show('No books');
  assert.equal(emptyRoot.hidden, false);
  assert.equal(emptyRoot.textContent, 'No books');
  empty.clear();
  assert.equal(emptyRoot.hidden, true);
});

test('toast host delegates messages to ToastService', () => {
  const calls = [];
  const host = new ToastHostComponent({}, {
    toastService: { show: (message, type) => calls.push({ message, type }) },
  });
  host.show('Saved', 'success');
  assert.deepEqual(calls, [{ message: 'Saved', type: 'success' }]);
});

test('data table renders rows and a bounded empty state', () => {
  const body = {
    children: [],
    replaceChildren() { this.children = []; },
    appendChild(row) { this.children.push(row); },
  };
  const root = { querySelector: () => body };
  const document = { createElement: () => ({ className: '', textContent: '' }) };
  const table = new DataTableComponent(root, {
    columns: ['title'],
    renderRow: (row) => ({ title: row.title }),
    emptyMessage: 'No books',
    document,
  });

  table.render([{ title: 'Clean Code' }]);
  assert.deepEqual(body.children, [{ title: 'Clean Code' }]);
  table.render([]);
  assert.equal(body.children[0].textContent, 'No books');
});
