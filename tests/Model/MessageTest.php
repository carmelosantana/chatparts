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

    public function testJsonRoundTripKeepsNullsAndEmptyObjects(): void
    {
        $message = new Message(
            'm2',
            'room-1',
            new Participant('system', null, 'System'),
            MessageStatus::Streaming,
            [
                new ReasoningPart('r', 'hmm', null),
                new ToolPart('t', 'ping', [], ToolState::Pending, null, null),
                new DataPart('d', 'empty', []),
            ],
            [],
            1_700_000_001,
        );

        $json = json_encode($message, JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('"input":{}', $json);
        $this->assertStringContainsString('"payload":{}', $json);
        $this->assertStringContainsString('"metadata":{}', $json);
        $this->assertStringContainsString('"durationMs":null', $json);
        $this->assertStringContainsString('"id":null', $json);

        $restored = Message::fromArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));

        $this->assertEquals($message, $restored);
        $this->assertSame($json, json_encode($restored, JSON_THROW_ON_ERROR));
    }

    /**
     * @dataProvider malformedMessages
     * @param array<string, mixed> $row
     */
    public function testMalformedMessageThrowsInvalidArgument(array $row, string $expected): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expected);

        Message::fromArray($row);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function malformedMessages(): iterable
    {
        $ok = self::message([new TextPart('a', 'hi')])->toArray();
        $part = static fn (array $p): array => ['parts' => [$p]] + $ok;

        yield 'missing id' => [array_diff_key($ok, ['id' => 1]), "'id'"];
        yield 'int room' => [['room' => 5] + $ok, "'room'"];
        yield 'participant not array' => [['participant' => 'bob'] + $ok, "'participant'"];
        yield 'participant missing name' => [['participant' => ['kind' => 'human']] + $ok, "'name'"];
        yield 'participant int id' => [['participant' => ['kind' => 'human', 'id' => 3, 'name' => 'B']] + $ok, "'id'"];
        yield 'unknown status' => [['status' => 'lost'] + $ok, "'status'"];
        yield 'int status' => [['status' => 1] + $ok, "'status'"];
        yield 'parts not array' => [['parts' => 'x'] + $ok, "'parts'"];
        yield 'metadata not array' => [['metadata' => 'x'] + $ok, "'metadata'"];
        yield 'string createdAt' => [['createdAt' => '1700000000'] + $ok, "'createdAt'"];
        yield 'part not array' => [['parts' => ['text']] + $ok, 'part'];
        yield 'part missing type' => [$part(['id' => 'a', 'text' => 'x']), "'type'"];
        yield 'part int type' => [$part(['type' => 1, 'id' => 'a']), "'type'"];
        yield 'text missing text' => [$part(['type' => 'text', 'id' => 'a']), "'text'"];
        yield 'text int id' => [$part(['type' => 'text', 'id' => 1, 'text' => 'x']), "'id'"];
        yield 'reasoning string duration' => [$part(['type' => 'reasoning', 'id' => 'a', 'text' => 'x', 'durationMs' => '5']), "'durationMs'"];
        yield 'tool unknown state' => [$part(['type' => 'tool', 'id' => 'a', 'name' => 'n', 'input' => [], 'state' => 'zzz']), "'state'"];
        yield 'tool input string' => [$part(['type' => 'tool', 'id' => 'a', 'name' => 'n', 'input' => 'q', 'state' => 'done']), "'input'"];
        yield 'tool error int' => [$part(['type' => 'tool', 'id' => 'a', 'name' => 'n', 'state' => 'done', 'error' => 1]), "'error'"];
        yield 'source string n' => [$part(['type' => 'source', 'id' => 'a', 'n' => '1', 'title' => 't', 'url' => 'u', 'kind' => 'task', 'ref' => '7']), "'n'"];
        yield 'file missing url' => [$part(['type' => 'file', 'id' => 'a', 'name' => 'n', 'mime' => 'm']), "'url'"];
        yield 'data payload string' => [$part(['type' => 'data', 'id' => 'a', 'dataType' => 'c', 'payload' => 'p']), "'payload'"];
    }

    public function testSubclassFromArrayOnlyBuildsItsOwnType(): void
    {
        $this->assertInstanceOf(TextPart::class, TextPart::fromArray(['type' => 'text', 'id' => 'a', 'text' => 'x']));

        $this->expectException(InvalidArgumentException::class);
        TextPart::fromArray(['type' => 'data', 'id' => 'a', 'dataType' => 'c', 'payload' => []]);
    }

    public function testReceiptText(): void
    {
        $this->assertSame('stopped: run budget reached', FinishReason::BudgetExhausted->receiptText());
        $this->assertSame('the provider refused', FinishReason::Refusal->receiptText());
        $this->assertSame('stopped', FinishReason::Stopped->receiptText());
        $this->assertSame("couldn't answer", FinishReason::Error->receiptText());
        $this->assertSame('', FinishReason::Stop->receiptText());
        $this->assertSame('stopped: content filter', FinishReason::ContentFilter->receiptText());
        $this->assertSame('stopped: too much context', FinishReason::ContextWindowExceeded->receiptText());
        $this->assertSame('stopped: step limit reached', FinishReason::MaxIterations->receiptText());
    }

    /** @param list<Part> $parts */
    private static function message(array $parts): Message
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
