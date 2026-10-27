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
