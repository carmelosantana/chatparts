// Pure helpers from assets/chatparts.js for the screen-reader announcement of a finished answer
// and the context ring following the attachments.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { answerToAnnounce, ringNeedsRefresh } = require('../../assets/chatparts.js');
const src = readFileSync(new URL('../../assets/chatparts.js', import.meta.url), 'utf8');

test('answerToAnnounce: a final frame with a name announces that name', () => {
  assert.equal(answerToAnnounce({ type: 'final', id: '12', name: 'project-bot' }, new Set()), 'project-bot');
});

test('answerToAnnounce: the same message is announced once', () => {
  assert.equal(answerToAnnounce({ type: 'final', id: '12', name: 'project-bot' }, new Set(['12'])), '');
  assert.equal(answerToAnnounce({ type: 'final', id: '13', name: 'project-bot' }, new Set(['12'])), 'project-bot');
});

test('answerToAnnounce: nothing for other events, a missing name, or a frame with no id', () => {
  assert.equal(answerToAnnounce({ type: 'delta', id: '12', name: 'project-bot' }, new Set()), '');
  assert.equal(answerToAnnounce({ type: 'done', id: '12', name: 'project-bot' }, new Set()), '');
  assert.equal(answerToAnnounce({ type: 'final', id: '12', name: '' }, new Set()), '');
  assert.equal(answerToAnnounce({ type: 'final', id: '', name: 'project-bot' }, new Set()), '');
});

test('the final answer is announced before its swap: sseMessage then fires on a detached bubble', () => {
  assert.match(src, /addEventListener\(\s*'htmx:sseBeforeMessage'/);
  const onMessage = src.slice(src.indexOf("addEventListener('htmx:sseMessage'"));
  assert.doesNotMatch(onMessage.slice(0, onMessage.indexOf('});')), /answered/);
});

test('ringNeedsRefresh: adding or removing an image refreshes, an unchanged count does not', () => {
  assert.equal(ringNeedsRefresh(0, 1), true);
  assert.equal(ringNeedsRefresh(3, 2), true);
  assert.equal(ringNeedsRefresh(4, 4), false, 'a refused fifth image');
  assert.equal(ringNeedsRefresh(0, 0), false);
});
