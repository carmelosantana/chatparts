// Static checks on assets/chatparts.js: it must run under a strict CSP with htmx's
// allowEval:false, and stay inert when loaded outside a browser.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const path = new URL('../../assets/chatparts.js', import.meta.url);
const src = readFileSync(path, 'utf8');

test('no eval, Function constructor, string timers or inline handlers', () => {
  assert.doesNotMatch(src, /\beval\s*\(/);
  assert.doesNotMatch(src, /\bnew\s+Function\b|\bFunction\s*\(/);
  assert.doesNotMatch(src, /set(Timeout|Interval)\s*\(\s*['"`]/);
  assert.doesNotMatch(src, /setAttribute\(\s*['"]on[a-z]+['"]/i);
  assert.doesNotMatch(src, /\.innerHTML\s*=/);
  assert.doesNotMatch(src, /\.outerHTML\s*=/);
  assert.doesNotMatch(src, /insertAdjacentHTML/);
  assert.doesNotMatch(src, /document\.write/);
});

test('the textarea stays required: the script never changes it (text is mandatory, images or not)', () => {
  assert.doesNotMatch(src, /\.required\s*=/);
  assert.doesNotMatch(src, /(set|remove|toggle)Attribute\(\s*['"]required['"]/);
});

test('loads without a DOM and exports only functions', () => {
  const api = require(path.pathname);
  assert.ok(Object.keys(api).length > 0);
  for (const [k, v] of Object.entries(api)) assert.equal(typeof v, 'function', k);
});
