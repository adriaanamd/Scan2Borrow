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
