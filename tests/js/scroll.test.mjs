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

// ---- Poll cursor, dedupe and Send/Stop scope. "Final" is what the server rendered as done,
// stopped or error; a draft the client itself marked failed (after an SSE error) is not final.
const { pollCursor, dedupeAction, hasOwnStreaming, isFinal } = require('../../assets/chatparts.js');

const m = (id, state, failed = false) => ({ id, state, failed });

test('isFinal: server done, stopped and error are final; a client-failed draft is not', () => {
  assert.equal(isFinal(m('1', 'done')), true);
  assert.equal(isFinal(m('1', 'stopped')), true);
  assert.equal(isFinal(m('1', 'error')), true);
  assert.equal(isFinal(m('1', 'error', true)), false);
  for (const state of ['submitted', 'streaming', 'weird', null]) assert.equal(isFinal(m('1', state)), false, String(state));
});

test('pollCursor: all final, the largest id', () => {
  assert.equal(pollCursor([m('10', 'done'), m('12', 'error'), m('11', 'stopped')]), 12);
});

test('pollCursor: never past the oldest unfinished bubble, even when a newer one is final', () => {
  assert.equal(pollCursor([m('40', 'done'), m('41', 'streaming'), m('42', 'done')]), 40);
  assert.equal(pollCursor([m('40', 'done'), m('41', 'error', true), m('42', 'done')]), 40, 'client-failed draft');
  assert.equal(pollCursor([m('39', 'submitted'), m('41', 'streaming'), m('42', 'done')]), 38);
});

test('pollCursor: unfinished ids above the largest final keep the final id', () => {
  assert.equal(pollCursor([m('40', 'done'), m('45', 'streaming')]), 40);
});

test('pollCursor: only unfinished bubbles, one below the oldest', () => {
  assert.equal(pollCursor([m('41', 'streaming')]), 40);
});

test('pollCursor: null with no numeric ids (keep the server-rendered value)', () => {
  assert.equal(pollCursor([]), null);
  assert.equal(pollCursor([m('m1', 'done'), m('draft', 'streaming')]), null);
});

test('dedupeAction: a final copy replaces a non-final one', () => {
  for (const existing of [m('1', 'submitted'), m('1', 'streaming'), m('1', 'error', true)]) {
    for (const incoming of [m('1', 'done'), m('1', 'stopped'), m('1', 'error')]) {
      assert.equal(dedupeAction(existing, incoming), 'replace', `${existing.state}/${existing.failed} <- ${incoming.state}`);
    }
  }
});

test('dedupeAction: everything else drops the incoming copy', () => {
  assert.equal(dedupeAction(m('1', 'done'), m('1', 'done')), 'drop');
  assert.equal(dedupeAction(m('1', 'error'), m('1', 'done')), 'drop', 'a stored error is final');
  assert.equal(dedupeAction(m('1', 'done'), m('1', 'streaming')), 'drop');
  assert.equal(dedupeAction(m('1', 'streaming'), m('1', 'streaming')), 'drop');
  assert.equal(dedupeAction(m('1', 'streaming'), m('1', 'error', true)), 'drop');
  assert.equal(dedupeAction(m('1', 'error', true), m('1', 'submitted')), 'drop');
});

// The asker's own post response carries the stream (sse-connect); a poll may deliver the same
// draft first without one. The streaming copy must win, or the asker never sees the reply stream.
const ms = (id, state, hasStream, failed = false) => ({ id, state, failed, hasStream });

test('dedupeAction: a non-final copy with a stream replaces a non-final copy without one', () => {
  for (const existing of [ms('1', 'submitted', false), ms('1', 'streaming', false)]) {
    for (const incoming of [ms('1', 'submitted', true), ms('1', 'streaming', true)]) {
      assert.equal(dedupeAction(existing, incoming), 'replace', `${existing.state} <- ${incoming.state}+stream`);
    }
  }
});

test('dedupeAction: a stream never displaces a stream, a final copy, or wins without one', () => {
  assert.equal(dedupeAction(ms('1', 'streaming', true), ms('1', 'streaming', true)), 'drop', 'both stream: keep the first');
  assert.equal(dedupeAction(ms('1', 'done', false), ms('1', 'streaming', true)), 'drop', 'final stays');
  assert.equal(dedupeAction(ms('1', 'streaming', true), ms('1', 'streaming', false)), 'drop', 'the stream stays');
  assert.equal(dedupeAction(ms('1', 'streaming', false), ms('1', 'streaming', false)), 'drop');
  assert.equal(dedupeAction(ms('1', 'streaming', true), ms('1', 'done', false)), 'replace', 'final still wins');
});

test('hasOwnStreaming: true only for a streaming bubble whose id this composer posted', () => {
  const own = new Set(['20']);
  assert.equal(hasOwnStreaming(own, [m('20', 'streaming')]), true);
  assert.equal(hasOwnStreaming(own, [m('21', 'streaming')]), false, "someone else's bot reply");
  assert.equal(hasOwnStreaming(own, [m('20', 'done')]), false, 'finished');
  assert.equal(hasOwnStreaming(new Set(), [m('20', 'streaming')]), false);
  assert.equal(hasOwnStreaming(undefined, [m('20', 'streaming')]), false);
});
