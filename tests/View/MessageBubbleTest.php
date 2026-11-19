<?php

declare(strict_types=1);

namespace ChatParts\Tests\View;

use ChatParts\Model\Message;
use ChatParts\Model\MessageStatus;
use ChatParts\Model\Part\Part;
use ChatParts\Model\Part\ReasoningPart;
use ChatParts\Model\Part\SourcePart;
use ChatParts\Model\Part\TextPart;
use ChatParts\Model\Part\ToolPart;
use ChatParts\Model\Participant;
use ChatParts\Model\ToolState;
use ChatParts\Tests\Support\FakeHost;
use ChatParts\Tests\Support\HostProxy;
use ChatParts\View\Avatar;
use ChatParts\View\MessageBubble;
use ChatParts\View\Notice;
use ChatParts\View\Receipt;
use PHPUnit\Framework\TestCase;

final class MessageBubbleTest extends TestCase
{
    public function testPartsRenderInOrder(): void
    {
        $html = (new MessageBubble(new FakeHost(), $this->message('bot', MessageStatus::Done, [
            new ReasoningPart('r1', 'thinking', 2300),
            new ToolPart('tl1', 'search_tasks', [], ToolState::Done, null, null),
            new TextPart('t1', 'Answer [1]'),
            new SourcePart('s1', 1, 'Task 4', '/task/4', 'task', '4'),
            new ToolPart('tl2', 'get_task', [], ToolState::Done, null, null),
            new TextPart('t2', 'More'),
        ])))->render();

        $reasoning = strpos($html, 'id="cp-reasoning-r1"');
        $tools = strpos($html, 'id="cp-tools-m1"');
        $text1 = strpos($html, 'id="cp-text-t1"');
        $text2 = strpos($html, 'id="cp-text-t2"');
        $sources = strpos($html, 'class="cp-sources"');
        $this->assertIsInt($reasoning);
        $this->assertTrue($reasoning < $tools && $tools < $text1 && $text1 < $text2 && $text2 < $sources, $html);
        $this->assertSame(1, substr_count($html, 'id="cp-tools-m1"'), 'one strip for all tool calls');
        $this->assertStringContainsString('Searched 1 source · 2 tools', $html);
        $this->assertStringContainsString('<a class="cp-cite" href="/task/4">1</a>', $html);
        $this->assertStringContainsString('Thought for 2 s', $html);
    }

    public function testBotBubbleHasBadgeAndDarkTile(): void
    {
        $html = (new MessageBubble(new FakeHost(), $this->message('bot', MessageStatus::Done, [new TextPart('t1', 'hi')])))->render();

        $this->assertStringStartsWith('<article id="cp-msg-m1" class="cp-msg cp-msg--bot" data-id="m1" data-state="done">', $html);
        $this->assertStringContainsString('<span class="cp-avatar cp-avatar--bot" aria-hidden="true"><svg class="cp-icon" aria-hidden="true" focusable="false"><use href="/assets/icons.svg#bot"/></svg></span>', $html);
        $this->assertStringContainsString('<span class="cp-badge cp-badge--bot">Bot</span>', $html);
        $this->assertStringContainsString('project-bot', $html);
        $this->assertStringNotContainsString('aria-busy', $html);
    }

    public function testHumanBubbleHasInitialsAndNoBadge(): void
    {
        $html = (new MessageBubble(new FakeHost(), $this->message('human', MessageStatus::Done, [new TextPart('t1', '<b>me</b>')], 'ada <x>')))->render();

        $this->assertStringContainsString('<span class="cp-avatar cp-avatar--human cp-tone-blue" aria-hidden="true">AD</span>', $html);
        $this->assertStringNotContainsString('cp-badge', $html);
        $this->assertStringContainsString('ada &lt;x&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;me&lt;/b&gt;', $html);
    }

    public function testStreamingSetsAriaBusy(): void
    {
        $html = (new MessageBubble(new FakeHost(), $this->message('bot', MessageStatus::Streaming, [new TextPart('t1', 'par<tial')]), true))->render();

        $this->assertStringStartsWith('<article id="cp-msg-m1" class="cp-msg cp-msg--bot" data-id="m1" data-state="streaming" aria-busy="true">', $html);
        $this->assertStringContainsString('<div id="cp-text-t1" class="cp-response cp-caret">par&lt;tial</div>', $html);
    }

    public function testEveryStatusRenders(): void
    {
        foreach (MessageStatus::cases() as $status) {
            $html = (new MessageBubble(new FakeHost(), $this->message('agent', $status, [])))->render();
            $this->assertStringContainsString('data-state="' . $status->value . '"', $html);
            $this->assertStringContainsString('<span class="cp-badge cp-badge--agent">Agent</span>', $html);
        }
    }

    public function testUnknownKindFallsBackToAFixedClass(): void
    {
        $html = (new MessageBubble(new FakeHost(), $this->message('x" onclick="1', MessageStatus::Done, [])))->render();

        $this->assertStringContainsString('class="cp-msg cp-msg--system"', $html);
        $this->assertStringNotContainsString('onclick', $html);
    }

    public function testAvatarToneHexBecomesACustomProperty(): void
    {
        $host = new class () extends HostProxy {
            public function avatarFor(Participant $p): array
            {
                return ['initials' => 'JD', 'tone' => '#1A2b3c'];
            }
        };
        $this->assertSame(
            '<span class="cp-avatar cp-avatar--agent" aria-hidden="true" style="--cp-tone:#1A2b3c">JD</span>',
            (new Avatar($host, new Participant('agent', '7', 'jd')))->render(),
        );
    }

    public function testReceiptLineAndStopReason(): void
    {
        $host = new FakeHost();
        $this->assertSame(
            '<footer class="cp-receipt">project-bot · qwen3 · 2 tools · answered for Ada (Manager)</footer>',
            (new Receipt($host, ['bot' => 'project-bot', 'model' => 'qwen3', 'tool_count' => 2, 'asked_by' => ['name' => 'Ada', 'role' => 'Manager']]))->render(),
        );
        $this->assertSame(
            '<footer class="cp-receipt">stopped: step limit reached</footer>',
            (new Receipt($host, ['bot' => 'project-bot', 'finish_reason' => 'max_iterations']))->render(),
        );
        $this->assertSame('', (new Receipt($host, ['finish_reason' => 'stop']))->render());
    }

    public function testNoticeLevels(): void
    {
        $host = new FakeHost();
        $this->assertStringStartsWith('<div class="cp-notice cp-notice--error" role="alert">', (new Notice($host, 'error', 'x'))->render());
        $this->assertStringContainsString('#triangle-exclamation-point', (new Notice($host, 'warning', 'x'))->render());
        $html = (new Notice($host, 'bogus', '<b>'))->render();
        $this->assertStringStartsWith('<div class="cp-notice cp-notice--info" role="status">', $html);
        $this->assertStringContainsString('&lt;b&gt;', $html);
    }

    /** @param list<Part> $parts */
    private function message(string $kind, MessageStatus $status, array $parts, string $name = 'project-bot'): Message
    {
        return new Message('m1', 'r1', new Participant($kind, null, $name), $status, $parts, [], 1_763_500_000);
    }
}
