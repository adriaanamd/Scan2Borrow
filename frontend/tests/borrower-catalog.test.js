import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { BorrowerSearchPage } from '../app/shared/pages/borrower-search.page.js';

const root = path.resolve('frontend');
const read = (file) => fs.readFileSync(path.join(root, file), 'utf8');

test('borrower catalog templates expose recommendations and an all-books pager', () => {
  for (const file of [
    'features/student/pages/search/search.html',
    'features/teacher/pages/borrow/borrow.html',
  ]) {
    const source = read(file);
    assert.match(source, /id="recommendation-panel"/);
    assert.match(source, /id="book-recommendations"/);
    assert.match(source, /id="show-all-books"/);
    assert.match(source, /Show all books/);
    assert.match(source, /id="all-books-panel"/);
    assert.match(source, /id="catalog-range"/);
    assert.match(source, /id="book-pagination"/);
  }
});

test('shared catalog queries use five recommendations and ten catalog books', () => {
  const context = {
    params: new URLSearchParams('search=code&category_name=Programming'),
    pageSize: 10,
    recommendationSize: 5,
  };
  const recommendations = BorrowerSearchPage.prototype.recommendationQuery.call(context);
  const catalog = BorrowerSearchPage.prototype.catalogQuery.call(context, 3);

  assert.equal(recommendations.get('status'), 'Available');
  assert.equal(recommendations.get('page'), '1');
  assert.equal(recommendations.get('per_page'), '5');
  assert.equal(recommendations.get('sort'), 'created_at');
  assert.equal(recommendations.get('dir'), 'desc');
  assert.equal(catalog.get('search'), 'code');
  assert.equal(catalog.get('category_name'), 'Programming');
  assert.equal(catalog.get('page'), '3');
  assert.equal(catalog.get('per_page'), '10');
});

test('catalog range and pager state cover full, partial, and empty pages', () => {
  const context = { pageSize: 10 };
  const range = BorrowerSearchPage.prototype.rangeLabel.bind(context);
  const pager = BorrowerSearchPage.prototype.paginationState.bind(context);

  assert.equal(range(42, 1), '1-10 of 42');
  assert.equal(range(42, 5), '41-42 of 42');
  assert.equal(range(0, 1), '0-0 of 0');
  assert.deepEqual(pager(42, 1), { page: 1, pages: 5, previous: false, next: true });
  assert.deepEqual(pager(42, 5), { page: 5, pages: 5, previous: true, next: false });
});

test('catalog query detection distinguishes the landing view from filtered intent', () => {
  assert.equal(BorrowerSearchPage.prototype.hasCatalogQuery.call({ params: new URLSearchParams() }), false);
  assert.equal(BorrowerSearchPage.prototype.hasCatalogQuery.call({ params: new URLSearchParams('search=history') }), true);
  assert.equal(BorrowerSearchPage.prototype.hasCatalogQuery.call({ params: new URLSearchParams('status=Available') }), true);
});
