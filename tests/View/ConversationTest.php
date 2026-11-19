<?php

declare(strict_types=1);

namespace ChatParts\Tests\View;

use ChatParts\Model\Message;
use ChatParts\Model\MessageStatus;
use ChatParts\Model\Part\TextPart;
use ChatParts\Model\Participant;
use ChatParts\Tests\Support\FakeHost;
use ChatParts\View\Conversation;
use ChatParts\View\Drawer;
use ChatParts\View\MentionPicker;
use ChatParts\View\PresenceList;
use PHPUnit\Framework\TestCase;

final class ConversationTest extends TestCase
{
    public function testPollUrlCarriesCursor(): void
    {
        $html = (new Conversation(new FakeHost(), [], '/room/poll', 42))->render();

        $this->assertStringContainsString(
            '<div id="cp-conversation" class="cp-conversation" role="log" aria-live="off" hx-get="/room/poll?after=42" hx-trigger="every 4s" hx-swap="beforeend" data-cp-poll="">',
            $html,
        );
        $this->assertStringNotContainsString('visibilityState', $html, 'trigger filters need eval');
        $this->assertStringContainsString('id="cp-jump"', $html);
        $this->assertStringContainsString('<div class="cp-sr" role="status" id="cp-announce"></div>', $html);

        $this->assertStringContainsString('hx-get="/poll?room=3&amp;after=7"', (new Conversation(new FakeHost(), [], '/poll?room=3', 7))->render());
        $this->assertStringNotContainsString('hx-get', (new Conversation(new FakeHost(), [], 'javascript:x', 7))->render());
    }

    public function testMessagesRenderInOrder(): void
    {
        $m = static fn (string $id, string $text): Message => new Message($id, 'r1', new Participant('human', '1', 'ada'), MessageStatus::Done, [new TextPart('t' . $id, $text)], [], 1_763_500_000);
        $html = (new Conversation(new FakeHost(), [$m('1', 'first'), $m('2', 'second')], '/poll', 2))->render();

        $this->assertTrue(strpos($html, 'id="cp-msg-1"') < strpos($html, 'id="cp-msg-2"'));
        $this->assertTrue(strpos($html, 'id="cp-msg-2"') < strpos($html, 'id="cp-jump"'), 'jump pill sits outside the log');
    }

    public function testPresenceListGroupsAndOnlineDots(): void
    {
        $html = (new PresenceList(new FakeHost(), [
            'people' => [['id' => '1', 'name' => 'ada <x>', 'online' => true], ['id' => '2', 'name' => 'bob']],
            'agents' => [['id' => '9', 'name' => 'scout']],
            'bots' => [],
            'bogus' => [['name' => 'nope']],
        ]))->render();

        $this->assertStringStartsWith('<aside class="cp-rail" aria-label="Members">', $html);
        $this->assertStringContainsString('People', $html);
        $this->assertStringContainsString('Agents', $html);
        $this->assertStringNotContainsString('Bots', $html, 'empty groups are left out');
        $this->assertStringNotContainsString('nope', $html);
        $this->assertStringContainsString('ada &lt;x&gt;', $html);
        $this->assertSame(1, substr_count($html, 'class="cp-dot '), 'only entries with presence data get a dot');
        $this->assertStringContainsString('cp-badge--agent', $html);
    }

    public function testMentionPickerListsEntries(): void
    {
        $html = (new MentionPicker(new FakeHost(), [
            ['kind' => 'bot', 'handle' => 'bot', 'name' => 'project-bot'],
            ['kind' => 'human', 'id' => '1', 'handle' => 'ada" x', 'name' => 'Ada'],
        ]))->render();

        $this->assertStringStartsWith('<ul id="cp-mention" class="cp-mention" role="listbox" aria-label="Mention someone" hidden="">', $html);
        $this->assertStringContainsString('<li id="cp-mention-0" class="cp-mention__item" role="option" aria-selected="false" data-handle="bot">', $html);
        $this->assertStringContainsString('data-handle="ada&quot; x"', $html);
        $this->assertStringContainsString('cp-badge--bot', $html);
        $this->assertStringContainsString('#bot', $html);
    }

    public function testDrawerShell(): void
    {
        $html = (new Drawer(new FakeHost(), 'Ask the <bot>', 'Task #4', '<p>body</p>'))->render();

        $this->assertStringStartsWith('<aside id="cp-drawer" class="cp-drawer" aria-labelledby="cp-drawer-title">', $html);
        $this->assertStringContainsString('<h2 id="cp-drawer-title" class="cp-drawer__title">Ask the &lt;bot&gt;</h2>', $html);
        $this->assertStringContainsString('Task #4', $html);
        $this->assertStringContainsString('data-action="close" aria-label="Close"', $html);
        $this->assertStringContainsString('<div class="cp-drawer__body"><p>body</p></div>', $html);
    }
}
