import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import test from "node:test";
import { fileURLToPath } from "node:url";

const testDirectory = path.dirname(fileURLToPath(import.meta.url));
const repositoryRoot = path.resolve(testDirectory, "../..");

function read(relativePath) {
  return fs.readFileSync(path.join(repositoryRoot, relativePath), "utf8");
}

test("shared stylesheet permits a 290px document viewport", () => {
  const source = read("frontend/assets/css/style.css");

  assert.doesNotMatch(source, /html\s*\{[^}]*min-width\s*:\s*(?:29[1-9]|[3-9]\d{2,})px/s);
  assert.doesNotMatch(source, /body\s*\{[^}]*min-width\s*:\s*(?:29[1-9]|[3-9]\d{2,})px/s);
});

test("API documentation operations collapse cleanly at 290px", () => {
  const source = read("frontend/assets/css/api-docs.css");

  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.api-docs-tag-list\s*\{[^}]*grid-template-columns:\s*1fr;/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.api-docs-operation\s+summary\s*\{[^}]*flex-wrap:\s*wrap;/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.api-docs-operation-body\s*\{[^}]*padding:\s*12px\s+10px;/s);
});

test("shared shell flex children can shrink below their content width", () => {
  const source = read("frontend/assets/css/style.css");

  assert.match(source, /\.app\s*\{[^}]*min-width\s*:\s*0/s);
  assert.match(source, /\.topbar-title\s*\{[^}]*min-width\s*:\s*0/s);
  assert.match(source, /\.topbar-user\s*\{[^}]*min-width\s*:\s*0/s);
});

test("narrow content surfaces keep usable insets at 290px", () => {
  const source = read("frontend/assets/css/style.css");

  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.topbar\s*\{[^}]*padding-left:\s*10px;[^}]*padding-right:\s*10px;/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.content\s*\{[^}]*padding:\s*12px\s+10px\s+24px;/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.auth-wrap\s*\{[^}]*padding:\s*8px;/s);
});

test("page headers allow their text and actions to shrink", () => {
  const source = read("frontend/assets/css/style.css");

  assert.match(source, /\.page-head\s*>\s*\*\s*\{[^}]*min-width\s*:\s*0/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.page-head\s*\{[^}]*gap:\s*8px;[^}]*padding-left:\s*10px;/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.page-head\s+\.btn\s*\{[^}]*white-space:\s*normal;/s);
});

test("shared cards and stat cards do not preserve wide child minimums", () => {
  const source = read("frontend/assets/css/style.css");

  assert.match(source, /\.card,\s*\.soft-card,\s*\.table-card\s*\{[^}]*min-width\s*:\s*0/s);
  assert.match(source, /\.card\s*>\s*\*,\s*\.soft-card\s*>\s*\*,\s*\.table-card\s*>\s*\*\s*\{[^}]*min-width\s*:\s*0/s);
  assert.match(source, /\.stat-card\s*>\s*\*\s*\{[^}]*min-width\s*:\s*0/s);
});

test("shared form controls can shrink inside narrow rows", () => {
  const source = read("frontend/assets/css/style.css");

  assert.match(source, /\.input-group\s*\{[^}]*min-width\s*:\s*0/s);
  assert.match(source, /\.input-group\s*>\s*\.form-control,\s*\.input-group\s*>\s*\.form-select\s*\{[^}]*min-width\s*:\s*0/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.form-control,\s*\.form-select,\s*\.btn\s*\{[^}]*max-width:\s*100%;/s);
});

test("modal surfaces keep their content inside the narrow viewport", () => {
  const source = read("frontend/assets/css/style.css");

  assert.match(source, /\.modal-content,\s*\.offcanvas\s*\{[^}]*min-width\s*:\s*0/s);
  assert.match(source, /\.modal-header\s*>\s*\*,\s*\.modal-body\s*>\s*\*,\s*\.modal-footer\s*>\s*\*\s*\{[^}]*min-width\s*:\s*0/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.modal-dialog\s*\{[^}]*margin:\s*\.5rem;/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.modal-header,\s*\.modal-body,\s*\.modal-footer\s*\{[^}]*padding-left:\s*10px;[^}]*padding-right:\s*10px;/s);
});

test("authentication panels retain usable proportions at 290px", () => {
  const source = read("frontend/assets/css/style.css");

  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.auth-brand-panel\s*\{[^}]*padding:\s*20px\s+12px;/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.auth-brand-logo\s*\{[^}]*height:\s*88px;[^}]*width:\s*88px;/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.auth-panel\s*\{[^}]*padding:\s*18px\s+12px\s+22px;/s);
});

test("dense tables scroll inside their existing surfaces", () => {
  const source = read("frontend/assets/css/style.css");

  assert.match(source, /\.table-card\s*>\s*\.table-responsive\s*\{[^}]*min-width\s*:\s*0;[^}]*max-width\s*:\s*100%;[^}]*overflow-x\s*:\s*auto/s);
  assert.match(source, /\.table-responsive\s*\{[^}]*max-width\s*:\s*100%;[^}]*overflow-x\s*:\s*auto/s);
});

test("borrower dashboard grids collapse at 290px", () => {
  const source = read("frontend/assets/css/borrower-dashboards.css");

  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.borrower-dashboard__stats,[\s\S]*grid-template-columns:\s*minmax\(0,\s*1fr\);/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.borrower-dashboard__work-grid,[\s\S]*grid-template-columns:\s*minmax\(0,\s*1fr\);/s);
});

test("borrower dashboard hero actions stay inside narrow cards", () => {
  const source = read("frontend/assets/css/borrower-dashboards.css");

  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.borrower-dashboard--student \.student-dashboard__hero,\s*\.borrower-dashboard--teacher \.teacher-dashboard__hero\s*\{[^}]*padding:\s*1rem;/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.borrower-dashboard__hero-title\s*\{[^}]*overflow-wrap:\s*anywhere;/s);
});

test("borrower dashboard data rails contain wide internals", () => {
  const source = read("frontend/assets/css/borrower-dashboards.css");

  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.borrower-dashboard__table\s*\{[^}]*max-width:\s*100%;[^}]*min-width:\s*0;[^}]*overflow-x:\s*auto;/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.borrower-dashboard__loan-actions\s*\{[^}]*min-width:\s*0;/s);
});

test("student history keeps its wide ledger inside a scroll boundary", () => {
  const source = read("frontend/assets/css/student-history.css");

  assert.match(source, /\.student-history-ledger__table\s*\{[^}]*max-width:\s*100%;[^}]*overflow-x:\s*auto;/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.student-history-ledger__header\s*\{[^}]*align-items:\s*flex-start;[^}]*flex-direction:\s*column;/s);
});

test("teacher history keeps its wide ledger inside a scroll boundary", () => {
  const source = read("frontend/assets/css/teacher-history.css");

  assert.match(source, /\.teacher-history-ledger__table\s*\{[^}]*max-width:\s*100%;[^}]*overflow-x:\s*auto;/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.teacher-history-ledger__header\s*\{[^}]*align-items:\s*flex-start;[^}]*flex-direction:\s*column;/s);
});

test("student search headers and pagination wrap at 290px", () => {
  const source = read("frontend/assets/css/student-search.css");

  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.student-search-page \.student-search-results__range\s*\{[^}]*overflow-wrap:\s*anywhere;[^}]*white-space:\s*normal;/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.student-search-page \.borrower-catalog__pagination-controls\s*\{[^}]*flex-wrap:\s*wrap;/s);
});

test("student search result cards cannot widen their result grid", () => {
  const source = read("frontend/assets/css/student-search.css");

  assert.match(source, /\.student-search-page \.student-search-recommendations__state\s*>\s*\.row\s*>\s*\*,\s*\.student-search-page \.student-search-results__state\s*>\s*\.row\s*>\s*\*\s*\{[^}]*min-width:\s*0;/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.student-search-page \.student-search-card,\s*\.student-search-page \.student-recommended-card\s*\{[^}]*min-width:\s*0;/s);
});

test("teacher search headers and result cards wrap at 290px", () => {
  const source = read("frontend/assets/css/teacher-search.css");

  assert.match(source, /\.teacher-search-page \.teacher-search-recommendations__state\s*>\s*\.row\s*>\s*\*,\s*\.teacher-search-page \.teacher-search-results__state\s*>\s*\.row\s*>\s*\*\s*\{[^}]*min-width:\s*0;/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.teacher-search-page \.teacher-search-results__range\s*\{[^}]*overflow-wrap:\s*anywhere;[^}]*white-space:\s*normal;/s);
});

test("teacher borrow modal content wraps at 290px", () => {
  const source = read("frontend/assets/css/teacher-search.css");

  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.teacher-search-page \.teacher-borrow-modal \.modal-header,\s*\.teacher-search-page \.teacher-borrow-modal \.modal-body,\s*\.teacher-search-page \.teacher-borrow-modal \.modal-footer\s*\{[^}]*padding-left:\s*10px;[^}]*padding-right:\s*10px;/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.teacher-search-page \.teacher-borrow-modal__cart-heading\s*\{[^}]*align-items:\s*flex-start;[^}]*flex-direction:\s*column;/s);
});

test("teacher library filters fit within a 290px panel", () => {
  const source = read("frontend/assets/css/teacher-library-surfaces.css");

  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.teacher-library-masthead\s*\{[^}]*padding:\s*1rem;/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.teacher-search-filters__grid\s*>\s*div\s*\{[^}]*min-width:\s*0;[^}]*width:\s*100%;/s);
});

test("staff management controls fit inside narrow sections", () => {
  const source = read("frontend/assets/css/staff-management.css");

  assert.match(source, /\.staff-management__section\s*\{[^}]*min-width:\s*0;/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.staff-management__search\s*\{[^}]*max-width:\s*none;/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.staff-management__actions \.btn\s*\{[^}]*white-space:\s*normal;/s);
});

test("guest request sections contain narrow table surfaces", () => {
  const source = read("frontend/assets/css/guest-requests.css");

  assert.match(source, /\.guest-requests__section\s*\{[^}]*min-width:\s*0;/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.guest-requests__section-heading\s*\{[^}]*padding:\s*\.85rem\s+\.75rem;/s);
  assert.match(source, /@media\s*\(max-width:\s*320px\)[\s\S]*\.guest-requests__table\s*\{[^}]*min-width:\s*640px;/s);
});
