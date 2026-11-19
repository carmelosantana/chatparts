<?php

declare(strict_types=1);

namespace ChatParts\Tests\Model;

use ChatParts\Model\FinishReason;
use ChatParts\Model\Message;
use ChatParts\Model\MessageStatus;
use ChatParts\Model\Part\DataPart;
use ChatParts\Model\Part\FilePart;
use ChatParts\Model\Part\Part;
use ChatParts\Model\Part\ReasoningPart;
use ChatParts\Model\Part\SourcePart;
use ChatParts\Model\Part\TextPart;
use ChatParts\Model\Part\ToolPart;
use ChatParts\Model\Participant;
use ChatParts\Model\ToolState;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{
    public function testRoundTripPreservesPartOrderAndTypes(): void
    {
        $message = $this->message([
            new ReasoningPart('p1', 'thinking', 1200),
            new ToolPart('p2', 'search_tasks', ['q' => 'bug'], ToolState::Done, '3 tasks', null),
            new TextPart('p3', 'Found three.'),
            new SourcePart('p4', 1, 'Fix login', 'https://example.test/t/7', 'task', '7'),
            new FilePart('p5', 'report.pdf', 'application/pdf', 'https://example.test/f/1', null),
            new DataPart('p6', 'chart', ['points' => [1, 2, 3]]),
        ]);

        $array = $message->toArray();
        $restored = Message::fromArray($array);

        $this->assertSame(
            ['reasoning', 'tool', 'text', 'source', 'file', 'data'],
            array_map(static fn (Part $p): string => $p->type(), $restored->parts),
        );
        $this->assertSame(
            [ReasoningPart::class, ToolPart::class, TextPart::class, SourcePart::class, FilePart::class, DataPart::class],
            array_map(static fn (Part $p): string => $p::class, $restored->parts),
        );
        $this->assertEquals($message, $restored);
        $this->assertSame($array, $restored->toArray());
    }

    public function testPlainTextJoinsTextPartsOnly(): void
    {
        $message = $this->message([
            new TextPart('a', 'Hello'),
            new ReasoningPart('b', 'secret thoughts', null),
            new ToolPart('c', 'lookup', [], ToolState::Error, 'tool summary', 'boom'),
            new TextPart('d', 'world'),
        ]);

        $this->assertSame("Hello\n\nworld", $message->plainText());
    }

    public function testReplacePartById(): void
    {
        $original = $this->message([new TextPart('a', 'one'), new TextPart('b', 'two')]);

        $replaced = $original->replacePart(new TextPart('b', 'TWO'));
        $appended = $replaced->withPart(new TextPart('c', 'three'));

        $this->assertSame("one\n\ntwo", $original->plainText());
        $this->assertSame("one\n\nTWO", $replaced->plainText());
        $this->assertSame("one\n\nTWO\n\nthree", $appended->plainText());
    }

    public function testReplacePartWithUnknownIdThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->message([new TextPart('a', 'one')])->replacePart(new TextPart('zz', 'x'));
    }

    public function testUnknownPartTypeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Part::fromArray(['type' => 'hologram', 'id' => 'x']);
    }

    public function testReceiptText(): void
    {
        $this->assertSame('stopped: run budget reached', FinishReason::BudgetExhausted->receiptText());
        $this->assertSame('the provider refused', FinishReason::Refusal->receiptText());
        $this->assertSame('stopped by you', FinishReason::Stopped->receiptText());
        $this->assertSame("couldn't answer", FinishReason::Error->receiptText());
        $this->assertSame('', FinishReason::Stop->receiptText());
        $this->assertSame('stopped: content filter', FinishReason::ContentFilter->receiptText());
        $this->assertSame('stopped: too much context', FinishReason::ContextWindowExceeded->receiptText());
        $this->assertSame('stopped: step limit reached', FinishReason::MaxIterations->receiptText());
    }

    /** @param list<Part> $parts */
    private function message(array $parts): Message
    {
        return new Message(
            'm1',
            'room-1',
            new Participant('agent', 'agent-9', 'Helper'),
            MessageStatus::Done,
            $parts,
            ['model' => 'test'],
            1_700_000_000,
        );
    }
}
