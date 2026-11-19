/**
 * Builds assets/icons.svg, chatparts' Lucide sprite, from scripts/icons.lock.
 *
 * Usage, from the repo root (Node >= 22.6; zero dependencies):
 *
 *   node --experimental-strip-types scripts/build-icons.ts
 *       Normal run. Fetches every icon in icons.lock, checks each file's sha256 against the
 *       lock, and only when all of them match writes the sprite. A mismatch aborts with
 *       nothing written.
 *
 *   node --experimental-strip-types scripts/build-icons.ts --update
 *       Bootstrap or re-pin. Fetches every icon and writes its sha256 into icons.lock, then the
 *       sprite. Use it to add an icon (add `"name": ""` to the lock first) or to move to a new
 *       Lucide release (edit "lucide" to a release tag at least 48 hours old). Review the lock
 *       and sprite diffs before committing.
 *
 * Supply chain: the only URLs fetched are
 * https://raw.githubusercontent.com/lucide-icons/lucide/<tag>/icons/<name>.svg, never npm. A
 * fetched SVG is untrusted data: it is parsed as text, its root must be Lucide's standard
 * <svg>, and only <path|circle|rect|line|polyline|polygon|ellipse> with their geometry
 * attributes survive, re-serialised from the parsed values. Anything else (script, style,
 * foreignObject, use, comments, event handlers, href, unknown attributes) fails the build.
 */
import { createHash } from 'node:crypto';
import { readFileSync, renameSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

export type Lock = { lucide: string; icons: Record<string, string> };
export type FetchText = (url: string) => Promise<string>;
export type RunOptions = { lockPath: string; spritePath: string; update: boolean; fetchText?: FetchText };

const NAME = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
const TAG = /^\d+\.\d+\.\d+$/;
const SHA256 = /^[0-9a-f]{64}$/;
const MAX_BYTES = 16_384;

/** The attributes every Lucide root carries; the symbol repeats them, so any other root is refused. */
const ROOT: Record<string, string> = {
  xmlns: 'http://www.w3.org/2000/svg',
  width: '24',
  height: '24',
  viewBox: '0 0 24 24',
  fill: 'none',
  stroke: 'currentColor',
  'stroke-width': '2',
  'stroke-linecap': 'round',
  'stroke-linejoin': 'round',
};
const SYMBOL_ATTRS =
  'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';

const GEOMETRY: Record<string, string[]> = {
  path: ['d'],
  circle: ['cx', 'cy', 'r'],
  rect: ['x', 'y', 'width', 'height', 'rx', 'ry'],
  line: ['x1', 'y1', 'x2', 'y2'],
  polyline: ['points'],
  polygon: ['points'],
  ellipse: ['cx', 'cy', 'rx', 'ry'],
};
const NUMBER = /^-?(?:\d+\.?\d*|\.\d+)$/;
const PATH_DATA = /^[MmLlHhVvCcSsQqTtAaZzEe0-9.,\s+\-]+$/;
const POINTS = /^[0-9.,\s\-]+$/;

const ATTRS = '((?:\\s+[a-zA-Z0-9-]+="[^"<>]*")*)';
const ROOT_RE = new RegExp(`^\\s*<svg${ATTRS}\\s*>([\\s\\S]*)</svg>\\s*$`);
const ELEMENT_RE = new RegExp(`^\\s*<([a-zA-Z]+)${ATTRS}\\s*/>`);

export function iconUrl(tag: string, name: string): string {
  if (!TAG.test(tag)) throw new Error(`bad Lucide tag ${JSON.stringify(tag)}`);
  if (!NAME.test(name)) throw new Error(`bad icon name ${JSON.stringify(name)}`);
  return `https://raw.githubusercontent.com/lucide-icons/lucide/${tag}/icons/${name}.svg`;
}

export function sha256(text: string): string {
  return createHash('sha256').update(text, 'utf8').digest('hex');
}

/** The icon's shapes, re-serialised from parsed geometry only; throws on anything else. */
export function sanitize(name: string, svg: string): string {
  const fail = (why: string): never => {
    throw new Error(`icon ${name}: ${why}`);
  };
  const root = ROOT_RE.exec(svg) ?? fail('not a single Lucide <svg> root');
  for (const [k, v] of parseAttrs(root[1], fail)) {
    if (ROOT[k] !== v) fail(`unexpected root attribute ${k}="${v}"`);
  }

  const shapes: string[] = [];
  let body = root[2];
  while (body.trim() !== '') {
    const el = ELEMENT_RE.exec(body) ?? fail(`unexpected content ${JSON.stringify(body.trim().slice(0, 40))}`);
    const tag = el[1];
    const allowed = GEOMETRY[tag] ?? fail(`element <${tag}> is not allowed`);
    let out = `<${tag}`;
    for (const [k, v] of parseAttrs(el[2], fail)) {
      if (!allowed.includes(k)) fail(`attribute ${k} is not allowed on <${tag}>`);
      const ok = k === 'd' ? PATH_DATA.test(v) : k === 'points' ? POINTS.test(v) : NUMBER.test(v);
      if (!ok) fail(`bad ${k} value ${JSON.stringify(v)}`);
      out += ` ${k}="${v}"`;
    }
    shapes.push(`${out}/>`);
    body = body.slice(el[0].length);
  }
  if (shapes.length === 0) fail('no shapes');
  return shapes.join('');
}

function parseAttrs(s: string, fail: (why: string) => never): [string, string][] {
  const seen = new Set<string>();
  return [...s.matchAll(/\s+([a-zA-Z0-9-]+)="([^"]*)"/g)].map((m) => {
    if (seen.has(m[1])) fail(`duplicate attribute ${m[1]}`);
    seen.add(m[1]);
    return [m[1], m[2]];
  });
}

function readLock(path: string, update: boolean): Lock {
  const lock = JSON.parse(readFileSync(path, 'utf8')) as Lock;
  if (typeof lock.lucide !== 'string' || !TAG.test(lock.lucide)) throw new Error(`${path}: "lucide" must be a release tag`);
  if (typeof lock.icons !== 'object' || lock.icons === null || Object.keys(lock.icons).length === 0) {
    throw new Error(`${path}: "icons" must be a non-empty object`);
  }
  for (const [name, sum] of Object.entries(lock.icons)) {
    if (!NAME.test(name)) throw new Error(`${path}: bad icon name ${JSON.stringify(name)}`);
    if (typeof sum !== 'string' || !(SHA256.test(sum) || (update && sum === ''))) {
      throw new Error(`${path}: ${name} needs a sha256 (run with --update to write it)`);
    }
  }
  return lock;
}

async function fetchFromGitHub(url: string): Promise<string> {
  const u = new URL(url);
  if (u.protocol !== 'https:' || u.hostname !== 'raw.githubusercontent.com') throw new Error(`refusing to fetch ${url}`);
  const res = await fetch(u, { redirect: 'error' });
  if (!res.ok) throw new Error(`HTTP ${res.status} for ${url}`);
  const bytes = await res.arrayBuffer();
  if (bytes.byteLength > MAX_BYTES) throw new Error(`${url} is larger than ${MAX_BYTES} bytes`);
  return new TextDecoder('utf-8', { fatal: true }).decode(bytes);
}

function writeAtomic(path: string, text: string): void {
  writeFileSync(`${path}.tmp`, text);
  renameSync(`${path}.tmp`, path);
}

/** Fetches, checks and sanitises every icon first; writes only when all of them pass. */
export async function run({ lockPath, spritePath, update, fetchText = fetchFromGitHub }: RunOptions): Promise<number> {
  const lock = readLock(lockPath, update);
  const sums: Record<string, string> = {};
  const symbols: string[] = [];
  for (const [name, pinned] of Object.entries(lock.icons)) {
    const text = await fetchText(iconUrl(lock.lucide, name));
    const sum = sha256(text);
    if (!update && sum !== pinned) throw new Error(`checksum mismatch for ${name}: lock has ${pinned}, fetched ${sum}`);
    symbols.push(`<symbol id="${name}" ${SYMBOL_ATTRS}>${sanitize(name, text)}</symbol>`);
    sums[name] = sum;
  }

  const sprite =
    `<!-- Generated by scripts/build-icons.ts from Lucide ${lock.lucide} (ISC, see THIRD_PARTY.md). Do not edit. -->\n` +
    `<svg xmlns="http://www.w3.org/2000/svg">\n${symbols.join('\n')}\n</svg>\n`;
  if (update) writeAtomic(lockPath, `${JSON.stringify({ lucide: lock.lucide, icons: sums }, null, 2)}\n`);
  writeAtomic(spritePath, sprite);
  return symbols.length;
}

if (process.argv[1] !== undefined && pathToFileURL(process.argv[1]).href === import.meta.url) {
  const args = process.argv.slice(2);
  const unknown = args.filter((a) => a !== '--update');
  if (unknown.length > 0) {
    console.error(`unknown argument ${unknown[0]}; the only flag is --update`);
    process.exit(2);
  }
  const here = dirname(fileURLToPath(import.meta.url));
  run({ lockPath: join(here, 'icons.lock'), spritePath: join(here, '..', 'assets', 'icons.svg'), update: args.includes('--update') })
    .then((n) => console.log(`wrote assets/icons.svg (${n} icons)${args.includes('--update') ? ' and scripts/icons.lock' : ''}`))
    .catch((e: Error) => {
      console.error(e.message);
      process.exitCode = 1;
    });
}
