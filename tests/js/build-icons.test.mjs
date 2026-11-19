// Tests for scripts/build-icons.ts. No network: every test hands run() a fake fetcher.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { mkdtempSync, writeFileSync, readFileSync, existsSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { run, sanitize, iconUrl } from '../../scripts/build-icons.ts';

const svg = (inner) =>
  `<svg\n  xmlns="http://www.w3.org/2000/svg"\n  width="24"\n  height="24"\n  viewBox="0 0 24 24"\n  fill="none"\n  stroke="currentColor"\n  stroke-width="2"\n  stroke-linecap="round"\n  stroke-linejoin="round"\n>\n${inner}\n</svg>\n`;
const sha = (s) => createHash('sha256').update(s).digest('hex');

const files = {
  check: svg('  <path d="M20 6 9 17l-5-5" />'),
  info: svg('  <circle cx="12" cy="12" r="10" />\n  <path d="M12 16v-4" />'),
  square: svg('  <rect width="18" height="18" x="3" y="3" rx="2" />'),
};

function setup(icons) {
  const dir = mkdtempSync(join(tmpdir(), 'cp-icons-'));
  const lockPath = join(dir, 'icons.lock');
  const spritePath = join(dir, 'icons.svg');
  writeFileSync(lockPath, JSON.stringify({ lucide: '1.0.0', icons }, null, 2) + '\n');
  const fetched = [];
  const fetchText = async (url) => {
    fetched.push(url);
    const name = url.split('/').pop().replace(/\.svg$/, '');
    if (!(name in files)) throw new Error(`404 ${url}`);
    return files[name];
  };
  return { dir, lockPath, spritePath, fetchText, fetched, done: () => rmSync(dir, { recursive: true }) };
}

test('checksum mismatch aborts', async () => {
  const t = setup({ check: sha(files.check), info: '0'.repeat(64) });
  await assert.rejects(run({ ...t, update: false }), /checksum mismatch for info/);
  assert.equal(existsSync(t.spritePath), false, 'no sprite is written');
  t.done();
});

test('sprite contains one <symbol id> per lock entry', async () => {
  const t = setup({ check: sha(files.check), info: sha(files.info), square: sha(files.square) });
  await run({ ...t, update: false });
  const sprite = readFileSync(t.spritePath, 'utf8');
  const ids = [...sprite.matchAll(/<symbol id="([^"]+)"/g)].map((m) => m[1]);
  assert.deepEqual(ids, ['check', 'info', 'square']);
  assert.match(
    sprite,
    /<symbol id="info" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"\/><path d="M12 16v-4"\/><\/symbol>/,
  );
  assert.deepEqual(t.fetched, [iconUrl('1.0.0', 'check'), iconUrl('1.0.0', 'info'), iconUrl('1.0.0', 'square')]);
  t.done();
});

test('--update writes checksums, then a normal run verifies them', async () => {
  const t = setup({ check: '', square: '' });
  await run({ ...t, update: true });
  const lock = JSON.parse(readFileSync(t.lockPath, 'utf8'));
  assert.deepEqual(lock, { lucide: '1.0.0', icons: { check: sha(files.check), square: sha(files.square) } });
  rmSync(t.spritePath);
  await run({ ...t, update: false });
  assert.equal(existsSync(t.spritePath), true);
  t.done();
});

test('icon URLs are raw GitHub files at the pinned tag', () => {
  assert.equal(iconUrl('1.74.0', 'bot'), 'https://raw.githubusercontent.com/lucide-icons/lucide/1.74.0/icons/bot.svg');
  assert.throws(() => iconUrl('1.74.0', '../x'), /icon name/);
  assert.throws(() => iconUrl('main/../x', 'bot'), /tag/);
});

test('sanitize keeps only geometry elements and attributes', () => {
  assert.equal(
    sanitize('x', svg('  <line x1="3" x2="21" y1="12" y2="12" />\n  <polyline points="1 2 3 4" />\n  <ellipse cx="1" cy="2" rx="3" ry="4" />\n  <polygon points="0,0 1,1" />')),
    '<line x1="3" x2="21" y1="12" y2="12"/><polyline points="1 2 3 4"/><ellipse cx="1" cy="2" rx="3" ry="4"/><polygon points="0,0 1,1"/>',
  );
});

for (const [label, bad] of [
  ['script', svg('  <script>alert(1)</script>')],
  ['style', svg('  <style>*{}</style>')],
  ['foreignObject', svg('  <foreignObject><div/></foreignObject>')],
  ['event handler', svg('  <path d="M0 0" onload="alert(1)" />')],
  ['href', svg('  <path d="M0 0" href="#x" />')],
  ['use element', svg('  <use href="#x" />')],
  ['non-numeric geometry', svg('  <circle cx="url(#x)" cy="1" r="1" />')],
  ['outer svg handler', svg('  <path d="M0 0" />').replace('<svg', '<svg onload="x"')],
  ['comment', svg('  <!-- hi --><path d="M0 0" />')],
  ['no svg root', '<path d="M0 0" />'],
]) {
  test(`sanitize rejects ${label}`, () => {
    assert.throws(() => sanitize('x', bad), /icon x/);
  });
}
