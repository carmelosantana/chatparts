<?php

declare(strict_types=1);

/**
 * Renders every chatparts component state into examples/kit.html, a static page for checking
 * the look in a browser (light and dark follow the OS setting). Run: php examples/kit.php
 *
 * The page loads only the kit's own assets, by relative path, with no inline script. A few
 * states that only the script produces (thumbnails, an open popover or picker, a filled
 * context ring) are written in here as static markup, and the composers after the first get
 * suffixed ids, since a real page has one composer.
 */

use ChatParts\Examples\ExampleHost;
use ChatParts\Model\Message;
use ChatParts\Model\MessageStatus;
use ChatParts\Model\Part\FilePart;
use ChatParts\Model\Part\ReasoningPart;
use ChatParts\Model\Part\SourcePart;
use ChatParts\Model\Part\TextPart;
use ChatParts\Model\Part\ToolPart;
use ChatParts\Model\Participant;
use ChatParts\Model\ToolState;
use ChatParts\View\Composer;
use ChatParts\View\ContextMeter;
use ChatParts\View\Conversation;
use ChatParts\View\Drawer;
use ChatParts\View\MentionPicker;
use ChatParts\View\MessageBubble;
use ChatParts\View\Notice;
use ChatParts\View\PresenceList;

require dirname(__DIR__) . '/autoload.php';
require __DIR__ . '/ExampleHost.php';

date_default_timezone_set('UTC');
$host = new ExampleHost();
$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$ada = new Participant('human', '1', 'Ada Lovelace');
$jess = new Participant('human', '2', 'Jess R.');
$agent = new Participant('agent', '7', 'claude.personal');
$bot = new Participant('bot', null, 'project-bot');
$system = new Participant('system', null, 'system');
$at = static fn (string $hm): int => (int) strtotime('2026-11-19 ' . $hm . ':00 UTC');
$msg = static fn (string $id, Participant $p, MessageStatus $s, array $parts, array $meta, string $hm): Message => new Message($id, 'demo', $p, $s, $parts, $meta, $at($hm));

$sources = [
    new SourcePart('s1', 1, '#1398 Hero section · comment by Jess R.', '#task-1398', 'task', '1398'),
    new SourcePart('s2', 2, '#1401 Mobile performance budget', '#task-1401', 'task', '1401'),
    new SourcePart('s3', 3, 'Transcript · creator interview, Oct 2', '', 'transcript', 't9'),
];
$answerAs = static fn (string $id): Message => $msg($id, $bot, MessageStatus::Done, [
    new ReasoningPart('r' . $id, "The question has two halves: the decision, and whether interviews mention it.\nSearch tasks first, then transcripts.", 2300),
    new ToolPart('tl1', 'search_tasks', ['q' => 'autoplay'], ToolState::Done, '2 tasks', null),
    new ToolPart('tl2', 'search_transcripts', ['q' => 'autoplay hero'], ToolState::Done, '1 clip', null),
    new ToolPart('tl3', 'read_lease', [], ToolState::Denied, null, 'not allowed for this asker'),
    new TextPart('t' . $id, "The team decided to **keep the hero video muted and paused until tapped** on mobile, and to autoplay it muted on desktop [1]. The reason given was data cost on phones [2].\n\nOne creator interview touches on it: viewers skip clips that start with sound [3]."),
    ...$sources,
], ['model' => 'claude-sonnet-5-5', 'tool_count' => 3, 'asked_by' => ['name' => 'Jess R.', 'role' => 'Member']], '14:06');

$room = [
    $msg('10', $system, MessageStatus::Done, [new TextPart('t10', 'Today')], [], '14:00'),
    $msg('11', $ada, MessageStatus::Done, [new TextPart('t11', '@claude.personal pick up #1412: the image pipeline is blocking the launch checklist.')], [], '14:01'),
    $msg('12', $agent, MessageStatus::Done, [new TextPart('t12', "On it. Reserved `Assets/img/**` for 2 h.\n\n```\nCache-Control: public, max-age=31536000, immutable\n```")], [], '14:03'),
    $msg('121', $jess, MessageStatus::Done, [new TextPart('t121', '@bot what did we decide about hero video autoplay, and did anyone mention it in the creator interviews?')], [], '14:05'),
    $answerAs('13'),
    $msg('14', $ada, MessageStatus::Done, [
        new TextPart('t14', '@bot is #1412 still blocked? Screenshot of the CDN panel attached.'),
        new FilePart('f14', 'cdn-panel.png', 'image/png', '#file-cdn-panel', null),
    ], [], '14:09'),
];
$streaming = $msg('15', $bot, MessageStatus::Streaming, [
    new ReasoningPart('r15', 'Check the task, then its latest comments.', null),
    new ToolPart('tl4', 'get_task', ['id' => 1412], ToolState::Done, 'In progress', null),
    new ToolPart('tl5', 'search_comments', ['task' => 1412], ToolState::Running, null, null),
    new TextPart('t15', 'From the board: #1412 is In progress and assigned to claude.personal, who holds the'),
], [], '14:09');

// ---- Room: conversation (with a streaming reply), composer, rail -------------------------

$conversation = (string) new Conversation($host, $room, '', 14);
$conversation = str_replace('</div><button type="button" id="cp-jump"', new MessageBubble($host, $streaming, true) . '</div><button type="button" id="cp-jump"', $conversation);

$meter = (string) new ContextMeter($host, [
    ['key' => 'instructions', 'label' => 'Instructions and rules', 'tokens' => 1_800],
    ['key' => 'tools', 'label' => 'Tool definitions', 'tokens' => 1_100],
    ['key' => 'history', 'label' => 'Room history (last 40)', 'tokens' => 6_200],
    ['key' => 'sources', 'label' => 'Retrieved sources (cap)', 'tokens' => 8_000],
    ['key' => 'message', 'label' => 'Your message + images', 'tokens' => 3_240],
    ['key' => 'reply', 'label' => 'Kept for the reply', 'tokens' => 4_000],
], 200_000);
preg_match('~^(.*)<div id="cp-context-pop" hx-swap-oob="innerHTML">(.*)</div>$~s', $meter, $m);
[$ring, $rows] = [$m[1], $m[2]];

$composer = (string) new Composer($host, '#send', ['room' => 'demo', 'allowImages' => true, 'canAsk' => true, 'contextUrl' => '#context', 'stopUrl' => '#stop']);
$composer = (string) preg_replace('~(<button type="button" class="cp-ctl cp-ctl--context"[^>]*aria-expanded=")false(">).*?(</button>)~s', '${1}true${2}' . $ring . '${3}', $composer);
$composer = str_replace('<div id="cp-context-pop" class="cp-context__pop" hidden=""></div>', '<div id="cp-context-pop" class="cp-context__pop">' . $rows . '</div>', $composer);
$thumb = static fn (string $name): string => '<div class="cp-thumb"><span class="cp-thumb__img"></span><button type="button" class="cp-thumb__x" aria-label="Remove ' . $e($name) . '"><svg class="cp-icon" aria-hidden="true" focusable="false"><use href="../assets/icons.svg#x"/></svg></button></div>';
$composer = str_replace('<div id="cp-thumbs" class="cp-thumbs"></div>', '<div id="cp-thumbs" class="cp-thumbs">' . $thumb('screenshot-1.png') . $thumb('screenshot-2.png') . '</div>', $composer);

$rail = (string) new PresenceList($host, [
    'people' => [['name' => 'Ada Lovelace', 'id' => '1', 'online' => true], ['name' => 'Jess R.', 'id' => '2', 'online' => false]],
    'agents' => [['name' => 'claude.personal', 'id' => '7', 'online' => true], ['name' => 'codex.dev', 'id' => '8']],
    'bots' => [['name' => 'project-bot']],
]);

// ---- Message states ----------------------------------------------------------------------

$states = [
    'Human, plain text' => new MessageBubble($host, $msg('s21', $jess, MessageStatus::Done, [new TextPart('ts21', 'Can someone review the CDN headers once Claude pushes?')], [], '15:00')),
    'Human, submitted (not yet stored)' => new MessageBubble($host, $msg('s22', $ada, MessageStatus::Submitted, [new TextPart('ts22', 'Sending this one now.')], [], '15:01')),
    'Agent' => new MessageBubble($host, $msg('s23', $agent, MessageStatus::Done, [new TextPart('ts23', "Pushed `cdn-headers`. Two files changed:\n\n- `nginx/cdn.conf`\n- `Assets/img/.htaccess`")], [], '15:02')),
    'Bot, streaming (thinking open, a tool running, caret)' => new MessageBubble($host, $msg('s24', $bot, MessageStatus::Streaming, [
        new ReasoningPart('rs24', 'Looking for the lease first.', null),
        new ToolPart('tls24', 'list_leases', [], ToolState::Pending, null, null),
        new TextPart('ts24', 'Checking who holds'),
    ], [], '15:03'), true),
    'Bot, ready, with reasoning, tools, citations, sources and receipt' => new MessageBubble($host, $answerAs('s20')),
    'Bot, stopped by the asker' => new MessageBubble($host, $msg('s25', $bot, MessageStatus::Stopped, [new TextPart('ts25', 'The lease on `Assets/img/**` is held by')], [], '15:04')),
    'Bot, run budget reached' => new MessageBubble($host, $msg('s26', $bot, MessageStatus::Done, [
        new ToolPart('tls26', 'search_tasks', [], ToolState::Error, null, 'timed out after 10 s'),
        new TextPart('ts26', 'I could not finish the search.'),
    ], ['finish_reason' => 'budget_exhausted'], '15:05')),
    'Bot, error' => new MessageBubble($host, $msg('s27', $bot, MessageStatus::Error, [], [], '15:06')),
    'System line' => new MessageBubble($host, $msg('s28', $system, MessageStatus::Done, [new TextPart('ts28', 'Jess R. joined the room')], [], '15:07')),
];
$stateHtml = '';
foreach ($states as $label => $bubble) {
    $stateHtml .= '<p class="kit-label">' . $e($label) . '</p>' . $bubble;
}
$notices = new Notice($host, 'info', 'The project bot is off: no AI provider is set up.')
    . new Notice($host, 'warning', 'Slow down: 15 messages this minute. At 20 the room pauses you for a minute.')
    . new Notice($host, 'error', "The bot couldn't answer: the provider timed out. Your message was kept.");

// ---- Composer variant: no images, no @bot, a draft with the mention picker open ---------

/** Give a second copy of a component its own ids (a real page has one composer). */
$suffix = static fn (string $html, string $sfx): string => (string) preg_replace('~\b(id|for|aria-controls)="(cp-[a-z-]+)"~', '$1="$2' . $sfx . '"', $html);

$picker = (string) new MentionPicker($host, [
    ['kind' => 'agent', 'handle' => 'claude.personal', 'name' => 'claude.personal', 'id' => '7'],
    ['kind' => 'human', 'handle' => 'ada', 'name' => 'Ada Lovelace', 'id' => '1'],
    ['kind' => 'agent', 'handle' => 'codex.dev', 'name' => 'codex.dev', 'id' => '8'],
]);
$picker = str_replace(['role="listbox" aria-label="Mention someone" hidden=""', 'id="cp-mention-0" class="cp-mention__item" role="option" aria-selected="false"'], ['role="listbox" aria-label="Mention someone"', 'id="cp-mention-0" class="cp-mention__item" role="option" aria-selected="true"'], $picker);
$plain = (string) new Composer($host, '#send', ['room' => 'demo']);
$plain = str_replace('></textarea>', '>Thanks! @c</textarea>', $plain);
$plain = str_replace('</form>', $picker . '</form>', $plain);
$plain = $suffix($plain, '-b');

// ---- Drawer --------------------------------------------------------------------------------

$drawerBody = new MessageBubble($host, $msg('d1', new Participant('human', '1', 'You'), MessageStatus::Done, [new TextPart('td1', "What's left before this can close?")], [], '16:00'))
    . new MessageBubble($host, $msg('d2', $bot, MessageStatus::Done, [
        new ToolPart('tld2', 'get_task', [], ToolState::Done, '1 task, 2 comments', null),
        new TextPart('td2', 'Only the CDN cache headers [1]. The WebP conversion is reported done [2].'),
        new SourcePart('sd1', 1, '#1412 description', '#task-1412', 'task', '1412'),
        new SourcePart('sd2', 2, '#1412 · comment, 20m ago', '#comment-88', 'comment', '88'),
    ], ['model' => 'claude-sonnet-5-5', 'tool_count' => 1, 'asked_by' => ['name' => 'Ada Lovelace', 'role' => 'Manager']], '16:00'))
    . $suffix((string) new Composer($host, '#ask', ['placeholder' => 'Ask about #1412']), '-c');
$drawer = $suffix((string) new Drawer($host, 'Ask the project bot', 'Private to you · not posted in the room', $drawerBody), '-c');

// ---- Page ----------------------------------------------------------------------------------

$client = '~(name="client_msg_id" value=")[0-9a-f]{32}~';
$section = static fn (string $title, string $body, string $class = ''): string => '<section class="kit-section' . ($class === '' ? '' : ' ' . $class) . '"><h2 class="kit-h">' . $title . '</h2>' . $body . '</section>';

$page = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="htmx-config" content='{"allowEval":false,"allowScriptTags":false}'>
<title>chatparts kit</title>
<link rel="stylesheet" href="../assets/chatparts.css">
<script src="../assets/htmx.min.js" defer></script>
<script src="../assets/htmx-ext-sse.js" defer></script>
<script src="../assets/chatparts.js" defer></script>
<style>
body { margin: 0; background: var(--cp-surface); color: var(--cp-text); font: 14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
.kit-page { max-width: 1200px; margin: 0 auto; padding: 24px 16px 64px; }
.kit-page > h1 { margin: 0 0 4px; font-size: 22px; }
.kit-page > p { margin: 0 0 24px; color: var(--cp-muted); }
.kit-section { margin: 0 0 40px; }
.kit-h { margin: 0 0 12px; font-size: 15px; }
.kit-label { max-width: 800px; margin: 16px auto 6px; color: var(--cp-muted); font-size: 11px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; }
.kit-room { display: grid; grid-template-columns: minmax(0, 1fr) 280px; height: 760px; border: 1px solid var(--cp-border); background: var(--cp-surface); }
.kit-room__main { display: flex; flex-direction: column; min-height: 0; padding: 16px; }
.kit-room__main .cp-thread { flex: 1; }
.kit-variant .cp-box { margin-top: 220px; }
.kit-drawer { position: relative; height: 640px; overflow: hidden; border: 1px solid var(--cp-border); background: var(--cp-surface); }
.kit-drawer .cp-drawer { position: absolute; max-width: 100%; }
@media (max-width: 760px) {
  .kit-room { grid-template-columns: minmax(0, 1fr); height: auto; }
  .kit-room__main { height: 680px; padding: 8px; }
}
</style>
</head>
<body>
<main class="kit-page">
<h1>chatparts kit</h1>
<p>Every component state, rendered by examples/kit.php. Dark mode follows the system setting.</p>
HTML;

$page .= $section('Room: conversation, streaming reply, composer with images, context popover and stop', '<div class="kit-room"><div class="kit-room__main">' . $conversation . $composer . '</div>' . $rail . '</div>')
    . $section('Message states', $stateHtml)
    . $section('Notices', (string) $notices)
    . $section('Composer without images or @bot, a draft, the mention picker open', $plain, 'kit-variant')
    . $section('Drawer', '<div class="kit-drawer">' . $drawer . '</div>')
    . "</main>\n</body>\n</html>\n";

$page = (string) preg_replace($client, '${1}' . str_repeat('0', 32), $page);
file_put_contents(__DIR__ . '/kit.html', $page);
fwrite(STDOUT, 'Wrote ' . __DIR__ . "/kit.html\n");
