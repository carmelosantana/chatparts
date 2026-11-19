// Pure conversation helpers from assets/chatparts.js: stick-to-bottom, the poll cursor and
// de-duplicating bubbles that arrive by both the send response and the poll.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { isNearBottom, maxMessageId, isDuplicate, setQueryParam } = require('../../assets/chatparts.js');

test('isNearBottom: true at the bottom and exactly at the threshold', () => {
  // scrollHeight 1000, clientHeight 400: bottom is scrollTop 600.
  assert.equal(isNearBottom(600, 400, 1000), true);
  assert.equal(isNearBottom(552, 400, 1000), true, '48 px away is still near');
});

test('isNearBottom: false one pixel past the threshold; custom threshold', () => {
  assert.equal(isNearBottom(551, 400, 1000), false);
  assert.equal(isNearBottom(500, 400, 1000, 100), true);
  assert.equal(isNearBottom(499, 400, 1000, 100), false);
});

test('isNearBottom: content shorter than the box is at the bottom', () => {
  assert.equal(isNearBottom(0, 400, 200), true);
});

test('maxMessageId: the largest numeric id, compared as numbers', () => {
  assert.equal(maxMessageId(['3', '12', '9']), 12);
  assert.equal(maxMessageId(['100', '99']), 100);
});

test('maxMessageId: null when there is no numeric id', () => {
  assert.equal(maxMessageId([]), null);
  assert.equal(maxMessageId(['m1', '', 'draft', '1e3', '-4', '2.5']), null);
});

test('maxMessageId: non-numeric ids are skipped', () => {
  assert.equal(maxMessageId(['m1', '7', null, undefined, '5']), 7);
});

test('isDuplicate: an id already present (Set or array)', () => {
  assert.equal(isDuplicate('7', new Set(['5', '7'])), true);
  assert.equal(isDuplicate('7', ['5', '7']), true);
  assert.equal(isDuplicate('8', new Set(['5', '7'])), false);
});

test('isDuplicate: an empty or missing id is never a duplicate', () => {
  assert.equal(isDuplicate('', new Set([''])), false);
  assert.equal(isDuplicate(null, ['x']), false);
});

test('setQueryParam: replaces an existing value and keeps the rest', () => {
  assert.equal(setQueryParam('/chat/poll?room=4&after=0', 'after', 12), '/chat/poll?room=4&after=12');
  assert.equal(setQueryParam('/?controller=Chat&after=3&x=1', 'after', '9'), '/?controller=Chat&after=9&x=1');
});

test('setQueryParam: adds the key when missing, before any fragment', () => {
  assert.equal(setQueryParam('/chat/poll', 'after', 5), '/chat/poll?after=5');
  assert.equal(setQueryParam('/chat/poll?room=1#end', 'after', 5), '/chat/poll?room=1&after=5#end');
});

test('setQueryParam: does not touch a key that merely ends with the name', () => {
  assert.equal(setQueryParam('/p?safter=1', 'after', 2), '/p?safter=1&after=2');
});

test('setQueryParam: encodes the value', () => {
  assert.equal(setQueryParam('/p', 'q', 'a b&c'), '/p?q=a%20b%26c');
});
