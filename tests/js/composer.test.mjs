// Pure composer helpers from assets/chatparts.js: auto-grow rows, Enter-to-send, attachments
// and @-mention lookup.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { rowsFor, shouldSubmit, nextAttachments, mentionQuery, format, isBlank } = require('../../assets/chatparts.js');

const MB = 1024 * 1024;
const img = (id, over = {}) => ({ id, name: `${id}.png`, size: 1000, type: 'image/png', ...over });
const enter = (over = {}) => ({ key: 'Enter', shiftKey: false, isComposing: false, ...over });

test('rowsFor: an empty draft is one row', () => {
  assert.equal(rowsFor(''), 1);
});

test('rowsFor: thirty lines stop at the ten-row cap', () => {
  assert.equal(rowsFor('a\n'.repeat(30)), 10);
});

test('rowsFor: a long line wraps every `wrap` characters', () => {
  assert.equal(rowsFor('x'.repeat(90)), 1);
  assert.equal(rowsFor('x'.repeat(91)), 2);
  assert.equal(rowsFor('x'.repeat(25), 10, 10), 3);
  assert.equal(rowsFor('a\nb\nc', 2), 2);
});

test('shouldSubmit: plain Enter on an enabled box sends', () => {
  assert.equal(shouldSubmit(enter(), false), true);
});

test('shouldSubmit: not while composing (IME), with Shift, when disabled, or for other keys', () => {
  assert.equal(shouldSubmit(enter({ isComposing: true }), false), false);
  assert.equal(shouldSubmit(enter({ keyCode: 229 }), false), false, 'Safari IME confirm');
  assert.equal(shouldSubmit(enter({ shiftKey: true }), false), false);
  assert.equal(shouldSubmit(enter(), true), false);
  assert.equal(shouldSubmit(enter({ key: 'a' }), false), false);
});

test('nextAttachments: add appends an accepted image', () => {
  const r = nextAttachments([], { type: 'add', item: img('a') });
  assert.deepEqual(r.list.map((x) => x.id), ['a']);
  assert.equal(r.error, null);
});

test('nextAttachments: accepts png, jpeg, webp and gif', () => {
  for (const type of ['image/png', 'image/jpeg', 'image/webp', 'image/gif']) {
    assert.equal(nextAttachments([], { type: 'add', item: img('a', { type }) }).error, null, type);
  }
});

test('nextAttachments: a fifth (and sixth) image is refused, list unchanged', () => {
  const four = ['a', 'b', 'c', 'd'].map((id) => img(id));
  const r5 = nextAttachments(four, { type: 'add', item: img('e') });
  assert.equal(r5.error, 'too-many');
  assert.deepEqual(r5.list, four);
  const r6 = nextAttachments(four, { type: 'add', item: img('f') });
  assert.equal(r6.error, 'too-many');
  assert.equal(r6.list.length, 4);
});

test('nextAttachments: 5 MB is accepted, 5 MB + 1 byte is refused', () => {
  assert.equal(nextAttachments([], { type: 'add', item: img('a', { size: 5 * MB }) }).error, null);
  const r = nextAttachments([], { type: 'add', item: img('a', { size: 5 * MB + 1 }) });
  assert.equal(r.error, 'too-large');
  assert.deepEqual(r.list, []);
});

test('nextAttachments: image/svg+xml and other types are refused', () => {
  assert.equal(nextAttachments([], { type: 'add', item: img('a', { type: 'image/svg+xml' }) }).error, 'type');
  assert.equal(nextAttachments([], { type: 'add', item: img('a', { type: 'application/pdf' }) }).error, 'type');
});

test('nextAttachments: removeLast and remove(id); the input list is never mutated', () => {
  const list = [img('a'), img('b'), img('c')];
  assert.deepEqual(nextAttachments(list, { type: 'removeLast' }).list.map((x) => x.id), ['a', 'b']);
  assert.deepEqual(nextAttachments(list, { type: 'remove', id: 'b' }).list.map((x) => x.id), ['a', 'c']);
  assert.deepEqual(nextAttachments([], { type: 'removeLast' }).list, []);
  assert.equal(list.length, 3);
});

test('mentionQuery: an @word ending at the caret', () => {
  assert.deepEqual(mentionQuery('@', 1), { start: 0, query: '' });
  assert.deepEqual(mentionQuery('hi @pro', 7), { start: 3, query: 'pro' });
  assert.deepEqual(mentionQuery('hi @claude.pers', 15), { start: 3, query: 'claude.pers' });
});

test('mentionQuery: nothing for an email, a finished mention or a caret elsewhere', () => {
  assert.equal(mentionQuery('me@example', 10), null);
  assert.equal(mentionQuery('@bot hello', 10), null);
  assert.equal(mentionQuery('plain text', 5), null);
});

test('mentionQuery: reads only up to the caret', () => {
  assert.deepEqual(mentionQuery('@bot hello', 2), { start: 0, query: 'b' });
});

test('format: %s takes the argument literally ($ patterns are not special)', () => {
  assert.equal(format('Remove %s', "$'x.png"), "Remove $'x.png");
  assert.equal(format('%s answered', '$&bot'), '$&bot answered');
  assert.equal(format('No placeholder', 'x'), 'No placeholder');
});

test('isBlank: a draft is sendable only with text; attachments alone do not count', () => {
  assert.equal(isBlank(''), true);
  assert.equal(isBlank(' \n\t '), true);
  assert.equal(isBlank(' a '), false);
});
