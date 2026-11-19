// Tests for scripts/checksums.sh and scripts/sync-into.sh. Every test works on temp dirs.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, readdirSync, existsSync, rmSync, cpSync, chmodSync, statSync, symlinkSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const vendored = ['src', 'assets', 'autoload.php', 'LICENSE', 'THIRD_PARTY.md'];

const sh = (script, args = [], cwd = root, env = {}, runIn = cwd) =>
  spawnSync('bash', [join(cwd, 'scripts', script), ...args], { cwd: runIn, encoding: 'utf8', env: { ...process.env, ...env } });

// A PATH directory whose `cp` runs the real cp, then runs `after` in the copy's destination.
// This lets a test break the staged copy after the source checks have passed.
function cpShim(after) {
  const bin = tempDir('cp-shim-');
  const realCp = spawnSync('bash', ['-c', 'command -v cp'], { encoding: 'utf8' }).stdout.trim();
  writeFileSync(
    join(bin, 'cp'),
    `#!/usr/bin/env bash\n"${realCp}" "$@" || exit $?\ndest="\${@: -1}"\ncd "$dest" && ${after}\n`,
  );
  chmodSync(join(bin, 'cp'), 0o755);
  return bin;
}

// A host dir that already holds a vendored copy, with a marker file in it.
function hostWithPreviousCopy() {
  const target = tempDir('cp-sync-');
  mkdirSync(join(target, 'vendor-chatparts'));
  writeFileSync(join(target, 'vendor-chatparts', 'previous.txt'), 'old copy\n');
  return target;
}

function assertPreviousCopyIntact(target) {
  assert.deepEqual(readdirSync(join(target, 'vendor-chatparts')), ['previous.txt']);
  assert.deepEqual(readdirSync(target), ['vendor-chatparts'], 'no staging dir left behind');
}

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
    assert.equal(statSync(join(out, 'CHECKSUMS')).mode & 0o777, 0o644);
    assert.deepEqual(readdirSync(target), ['vendor-chatparts'], 'no staging dir left behind');
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
    assert.equal(statSync(out).mode & 0o777, 0o644);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
});

test('sync refuses a copied file that does not match and keeps the previous copy', () => {
  const target = hostWithPreviousCopy();
  const bin = cpShim("echo tampered >> src/View/Hx.php");
  try {
    const r = sh('sync-into.sh', [target], root, { PATH: `${bin}:${process.env.PATH}` });
    assert.equal(r.status, 1);
    assert.match(r.stderr, /sync-into: .*does not match CHECKSUMS/);
    assertPreviousCopyIntact(target);
  } finally {
    rmSync(target, { recursive: true, force: true });
    rmSync(bin, { recursive: true, force: true });
  }
});

test('sync refuses an extra copied file and keeps the previous copy', () => {
  const target = hostWithPreviousCopy();
  const bin = cpShim("echo x > src/Extra.php");
  try {
    const r = sh('sync-into.sh', [target], root, { PATH: `${bin}:${process.env.PATH}` });
    assert.equal(r.status, 1);
    assert.match(r.stderr, /sync-into: .*not in CHECKSUMS.*src\/Extra\.php/s);
    assertPreviousCopyIntact(target);
  } finally {
    rmSync(target, { recursive: true, force: true });
    rmSync(bin, { recursive: true, force: true });
  }
});

test('a failure mid-sync leaves the previous vendor copy intact', () => {
  const target = hostWithPreviousCopy();
  const bin = cpShim('exit 1');
  try {
    const r = sh('sync-into.sh', [target], root, { PATH: `${bin}:${process.env.PATH}` });
    assert.equal(r.status, 1);
    assert.match(r.stderr, /sync-into: /);
    assertPreviousCopyIntact(target);
  } finally {
    rmSync(target, { recursive: true, force: true });
    rmSync(bin, { recursive: true, force: true });
  }
});

test('sync resolves a relative target in the working directory even with CDPATH set', () => {
  const work = tempDir('cp-work-');
  const decoy = tempDir('cp-cdpath-');
  try {
    mkdirSync(join(work, 'host'));
    mkdirSync(join(decoy, 'host'));
    const r = sh('sync-into.sh', ['host'], root, { CDPATH: decoy }, work);
    assert.equal(r.status, 0, r.stderr);
    assert.ok(existsSync(join(work, 'host', 'vendor-chatparts', 'CHECKSUMS')), 'synced into ./host');
    assert.deepEqual(readdirSync(join(decoy, 'host')), [], 'CDPATH dir untouched');
    assert.deepEqual(readdirSync(decoy), ['host']);
  } finally {
    rmSync(work, { recursive: true, force: true });
    rmSync(decoy, { recursive: true, force: true });
  }
});

test('sync accepts a target that starts with a dash', () => {
  const work = tempDir('cp-work-');
  try {
    mkdirSync(join(work, '-x'));
    const r = sh('sync-into.sh', ['-x'], root, {}, work);
    assert.equal(r.status, 0, r.stderr);
    assert.ok(existsSync(join(work, '-x', 'vendor-chatparts', 'CHECKSUMS')));
  } finally {
    rmSync(work, { recursive: true, force: true });
  }
});

test('sync refuses a target inside the chatparts checkout', () => {
  const src = sourceCopy();
  try {
    for (const t of [src, join(src, 'src'), join(src, 'src', 'View')]) {
      const r = sh('sync-into.sh', [t], src);
      assert.equal(r.status, 1, t);
      assert.match(r.stderr, /inside the chatparts checkout/);
    }
    assert.ok(!existsSync(join(src, 'vendor-chatparts')));
    assert.ok(!existsSync(join(src, 'src', 'vendor-chatparts')));
    assert.ok(!existsSync(join(src, 'src', 'View', 'vendor-chatparts')));
  } finally {
    rmSync(src, { recursive: true, force: true });
  }
});

// A PATH directory whose `mv` fails when one of its arguments matches the bash `pattern`,
// and runs the real mv otherwise.
function mvShim(pattern) {
  const bin = tempDir('mv-shim-');
  const realMv = spawnSync('bash', ['-c', 'command -v mv'], { encoding: 'utf8' }).stdout.trim();
  writeFileSync(
    join(bin, 'mv'),
    `#!/usr/bin/env bash\nfor a in "$@"; do case "$a" in ${pattern}) echo "mv shim: refused $a" >&2; exit 1 ;; esac; done\nexec "${realMv}" "$@"\n`,
  );
  chmodSync(join(bin, 'mv'), 0o755);
  return bin;
}

test('a failed move of the new copy into place restores the previous copy', () => {
  const target = hostWithPreviousCopy();
  const bin = mvShim('*/.vendor-chatparts.*'); // the verified stage
  try {
    const r = sh('sync-into.sh', [target], root, { PATH: `${bin}:${process.env.PATH}` });
    assert.equal(r.status, 1);
    assert.match(r.stderr, /sync-into: .*restored/);
    assertPreviousCopyIntact(target);
  } finally {
    rmSync(target, { recursive: true, force: true });
    rmSync(bin, { recursive: true, force: true });
  }
});

test('a failed move of the previous copy aside leaves it in place', () => {
  const target = hostWithPreviousCopy();
  const bin = mvShim('*/.vendor-chatparts-old.*/old');
  try {
    const r = sh('sync-into.sh', [target], root, { PATH: `${bin}:${process.env.PATH}` });
    assert.equal(r.status, 1);
    assert.match(r.stderr, /sync-into: /);
    assertPreviousCopyIntact(target);
  } finally {
    rmSync(target, { recursive: true, force: true });
    rmSync(bin, { recursive: true, force: true });
  }
});

test('sync refuses a destination that is the chatparts checkout or contains it', () => {
  // The checkout itself named vendor-chatparts, with the target its parent.
  const parent = tempDir('cp-parent-');
  const src = join(parent, 'vendor-chatparts');
  mkdirSync(src);
  const copy = sourceCopy();
  cpSync(copy, src, { recursive: true });
  rmSync(copy, { recursive: true, force: true });
  // Hosts whose vendor-chatparts is a symlink to the checkout, or to a directory above it.
  const toRoot = tempDir('cp-link-');
  const toAbove = tempDir('cp-link-');
  symlinkSync(src, join(toRoot, 'vendor-chatparts'));
  symlinkSync(parent, join(toAbove, 'vendor-chatparts'));
  try {
    for (const t of [parent, toRoot, toAbove]) {
      const r = sh('sync-into.sh', [t], src);
      assert.equal(r.status, 1, t);
      assert.match(r.stderr, /sync-into: .*chatparts checkout/, t);
    }
    assert.ok(existsSync(join(src, 'scripts', 'sync-into.sh')), 'checkout untouched');
    assert.deepEqual(readdirSync(parent), ['vendor-chatparts']);
    assert.deepEqual(readdirSync(toRoot), ['vendor-chatparts']);
    assert.deepEqual(readdirSync(toAbove), ['vendor-chatparts']);
  } finally {
    for (const d of [parent, toRoot, toAbove]) rmSync(d, { recursive: true, force: true });
  }
});

test('sync explains a staging dir it cannot make', { skip: process.getuid?.() === 0 && 'root ignores permissions' }, () => {
  const target = tempDir('cp-sync-');
  try {
    chmodSync(target, 0o555);
    const r = sh('sync-into.sh', [target]);
    assert.equal(r.status, 1);
    assert.match(r.stderr, /sync-into: .*staging/);
  } finally {
    chmodSync(target, 0o755);
    rmSync(target, { recursive: true, force: true });
  }
});
