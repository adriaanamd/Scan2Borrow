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
