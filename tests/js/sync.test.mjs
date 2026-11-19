// Tests for scripts/checksums.sh and scripts/sync-into.sh. Every test works on temp dirs.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, existsSync, rmSync, cpSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const vendored = ['src', 'assets', 'autoload.php', 'LICENSE', 'THIRD_PARTY.md'];

const sh = (script, args = [], cwd = root) =>
  spawnSync('bash', [join(cwd, 'scripts', script), ...args], { cwd, encoding: 'utf8' });

function tempDir(prefix) {
  return mkdtempSync(join(tmpdir(), prefix));
}

test('sync copies and verifies', () => {
  const target = tempDir('cp-sync-');
  try {
    const r = sh('sync-into.sh', [target]);
    assert.equal(r.status, 0, r.stderr);
    const out = join(target, 'vendor-chatparts');
    for (const p of vendored) assert.ok(existsSync(join(out, p)), `${p} copied`);
    assert.ok(existsSync(join(out, 'CHECKSUMS')), 'CHECKSUMS copied');
    for (const p of ['tests', 'scripts', 'examples', 'README.md', 'phpunit.xml']) {
      assert.ok(!existsSync(join(out, p)), `${p} not copied`);
    }
    assert.equal(
      readFileSync(join(out, 'src/View/Hx.php'), 'utf8'),
      readFileSync(join(root, 'src/View/Hx.php'), 'utf8'),
    );
    const check = spawnSync('sha256sum', ['--quiet', '-c', 'CHECKSUMS'], { cwd: out, encoding: 'utf8' });
    assert.equal(check.status, 0, check.stdout + check.stderr);
  } finally {
    rmSync(target, { recursive: true, force: true });
  }
});

// A copy of the repo's vendored set plus scripts, so a test can tamper with the source.
function sourceCopy() {
  const src = tempDir('cp-src-');
  for (const p of [...vendored, 'scripts', 'CHECKSUMS']) cpSync(join(root, p), join(src, p), { recursive: true });
  return src;
}

test('sync refuses a tampered file', () => {
  const src = sourceCopy();
  const target = tempDir('cp-sync-');
  try {
    writeFileSync(join(src, 'src/View/Hx.php'), '<?php // tampered\n');
    const r = sh('sync-into.sh', [target], src);
    assert.equal(r.status, 1);
    assert.match(r.stderr, /checksums\.sh/);
  } finally {
    rmSync(src, { recursive: true, force: true });
    rmSync(target, { recursive: true, force: true });
  }
});

test('sync refuses an extra file in the source tree', () => {
  const src = sourceCopy();
  const target = tempDir('cp-sync-');
  try {
    writeFileSync(join(src, 'src/Extra.php'), '<?php\n');
    const r = sh('sync-into.sh', [target], src);
    assert.equal(r.status, 1);
    assert.match(r.stderr, /checksums\.sh/);
  } finally {
    rmSync(src, { recursive: true, force: true });
    rmSync(target, { recursive: true, force: true });
  }
});

test('sync removes stale files', () => {
  const target = tempDir('cp-sync-');
  try {
    mkdirSync(join(target, 'vendor-chatparts', 'src', 'Old'), { recursive: true });
    writeFileSync(join(target, 'vendor-chatparts', 'src', 'Old', 'Gone.php'), '<?php\n');
    writeFileSync(join(target, 'keep.txt'), 'host file\n');
    const r = sh('sync-into.sh', [target]);
    assert.equal(r.status, 0, r.stderr);
    assert.ok(!existsSync(join(target, 'vendor-chatparts', 'src', 'Old')), 'stale dir removed');
    assert.ok(existsSync(join(target, 'keep.txt')), 'host files outside vendor-chatparts kept');
  } finally {
    rmSync(target, { recursive: true, force: true });
  }
});

test('sync refuses an empty or root target', () => {
  for (const args of [[], [''], ['/']]) {
    const r = sh('sync-into.sh', args);
    assert.notEqual(r.status, 0, `refused ${JSON.stringify(args)}`);
  }
});

test('checksums are current in the repo', () => {
  const dir = tempDir('cp-sums-');
  try {
    const out = join(dir, 'CHECKSUMS');
    const r = sh('checksums.sh', [out]);
    assert.equal(r.status, 0, r.stderr);
    assert.equal(readFileSync(out, 'utf8'), readFileSync(join(root, 'CHECKSUMS'), 'utf8'));
    assert.match(readFileSync(out, 'utf8'), /^[0-9a-f]{64}  src\/View\/Hx\.php$/m);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});
