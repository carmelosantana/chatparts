<?php

declare(strict_types=1);

namespace ChatParts\Tests\Stream;

use ChatParts\Stream\SseWriter;
use ChatParts\Stream\StreamEvents;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SseWriterTest extends TestCase
{
    private string $buf = '';

    /** @var list<string> */
    private array $headers = [];

    private float $now = 1000.0;

    /** @var list<int> status codes passed with headers (0 = left alone) */
    private array $codes = [];

    private function writer(): SseWriter
    {
        return new SseWriter(
            function (string $s): void {
                $this->buf .= $s;
            },
            fn (): float => $this->now,
            function (string $h, bool $replace = true, int $code = 0): void {
                $this->headers[] = $h;
                $this->codes[] = $code;
            },
        );
    }

    public function testOpenSendsStreamHeadersOnce(): void
    {
        $w = $this->writer();
        $w->open();
        $w->open();

        $this->assertSame(
            ['Content-Type: text/event-stream', 'Cache-Control: no-cache', 'X-Accel-Buffering: no'],
            $this->headers,
        );
        $this->assertSame('', $this->buf);
    }

    public function testMultilineHtmlIsFramedPerLine(): void
    {
        $w = $this->writer();
        $w->event(StreamEvents::DELTA, "<p>one</p>\n<p>two</p>\n\n<p>three</p>");

        $this->assertSame(
            "event: delta\ndata: <p>one</p>\ndata: <p>two</p>\ndata: \ndata: <p>three</p>\n\n",
            $this->buf,
        );
    }

    public function testCarriageReturnsCannotInjectAField(): void
    {
        $w = $this->writer();
        $w->event(StreamEvents::DELTA, "a\r\nb\revent: evil\r\ndata: x");

        $this->assertSame(
            "event: delta\ndata: a\ndata: b\ndata: event: evil\ndata: data: x\n\n",
            $this->buf,
        );
    }

    /** @dataProvider badNames */
    public function testRejectsUnsafeEventName(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->writer()->event($name, '<p>x</p>');
    }

    /** @return iterable<string, array{string}> */
    public static function badNames(): iterable
    {
        yield 'empty' => [''];
        yield 'line feed' => ["delta\ndata: x"];
        yield 'carriage return' => ["delta\rx"];
        yield 'colon' => ['a:b'];
    }

    public function testCloseAlwaysSendsDone(): void
    {
        $w = $this->writer();
        $w->event(StreamEvents::DELTA, 'x');
        $w->close();
        $w->close();

        $this->assertSame("event: delta\ndata: x\n\nevent: done\ndata: \n\n", $this->buf);
    }

    public function testWritesAfterCloseAreNoOps(): void
    {
        $w = $this->writer();
        $w->close();
        $this->now += 60;
        $w->event(StreamEvents::DELTA, 'late');
        $w->heartbeat();
        $w->maybeHeartbeat();

        $this->assertSame("event: done\ndata: \n\n", $this->buf);
    }

    public function testCloseBeforeOpenSendsHeaders(): void
    {
        $w = $this->writer();
        $w->close();

        $this->assertSame(
            ['Content-Type: text/event-stream', 'Cache-Control: no-cache', 'X-Accel-Buffering: no'],
            $this->headers,
        );
        $this->assertSame("event: done\ndata: \n\n", $this->buf);
    }

    public function testEventBeforeOpenSendsHeadersOnce(): void
    {
        $w = $this->writer();
        $w->event(StreamEvents::DELTA, 'x');
        $w->heartbeat();
        $w->open();

        $this->assertCount(3, $this->headers);
        $this->assertSame([0, 0, 0], $this->codes, 'a normal stream leaves the status alone');
    }

    public function testRefuseSendsOnlyDone(): void
    {
        $w = $this->writer();
        $w->refuse();
        $w->close();

        $this->assertSame(
            ['Content-Type: text/event-stream', 'Cache-Control: no-cache', 'X-Accel-Buffering: no'],
            $this->headers,
        );
        $this->assertSame(200, $this->codes[0], 'refuse() sets 200 explicitly');
        $this->assertSame("event: done\ndata: \n\n", $this->buf);
    }

    public function testHeartbeatIsAComment(): void
    {
        $w = $this->writer();
        $w->heartbeat();

        $this->assertSame(": hb\n\n", $this->buf);
    }

    public function testHeartbeatAfterFifteenSeconds(): void
    {
        $w = $this->writer();
        $this->now += 14.9;
        $w->maybeHeartbeat();
        $this->assertSame('', $this->buf);

        $this->now += 0.1;
        $w->maybeHeartbeat();
        $this->assertSame(": hb\n\n", $this->buf);

        $this->now += 10;
        $w->maybeHeartbeat();
        $this->assertSame(": hb\n\n", $this->buf, 'the heartbeat resets the quiet timer');
    }

    public function testAnEventResetsTheHeartbeatTimer(): void
    {
        $w = $this->writer();
        $this->now += 10;
        $w->event(StreamEvents::DELTA, 'x');
        $this->now += 10;
        $w->maybeHeartbeat();
        $this->assertSame("event: delta\ndata: x\n\n", $this->buf);

        $this->now += 5;
        $w->maybeHeartbeat(15);
        $this->assertSame("event: delta\ndata: x\n\n: hb\n\n", $this->buf);
    }

    public function testEventNamesMatchTheContract(): void
    {
        $this->assertSame(
            ['delta', 'reasoning', 'parts', 'final', 'done'],
            [StreamEvents::DELTA, StreamEvents::REASONING, StreamEvents::PARTS, StreamEvents::FINAL, StreamEvents::DONE],
        );
    }

    public function testOobInjectsAttributeOnRoot(): void
    {
        $this->assertSame(
            '<div hx-swap-oob="true" id="m1" class="x"><span id="inner">a</span></div>',
            StreamEvents::oob('<div id="m1" class="x"><span id="inner">a</span></div>'),
        );
    }

    public function testOobReplacesExistingAttributeAndEscapesMode(): void
    {
        $this->assertSame(
            '<li hx-swap-oob="beforeend:#a&quot;&gt;" id="x" data-k=\'v>w\'><b hx-swap-oob="true">c</b></li>',
            StreamEvents::oob(
                '<li hx-swap-oob="outerHTML" id="x" data-k=\'v>w\' HX-SWAP-OOB><b hx-swap-oob="true">c</b></li>',
                'beforeend:#a">',
            ),
        );
    }

    public function testOobAllowsLeadingWhitespaceAndComments(): void
    {
        $this->assertSame(
            "\n  <!-- m -->\n<section hx-swap-oob=\"true\">s</section>",
            StreamEvents::oob("\n  <!-- m -->\n<section>s</section>"),
        );
    }

    public function testOobOnSelfClosingRoot(): void
    {
        $this->assertSame('<hr hx-swap-oob="true" id="h"/>', StreamEvents::oob('<hr id="h"/>'));
    }

    /** @dataProvider notElements */
    public function testOobRejectsNonElement(string $html): void
    {
        $this->expectException(InvalidArgumentException::class);

        StreamEvents::oob($html);
    }

    /** @return iterable<string, array{string}> */
    public static function notElements(): iterable
    {
        yield 'empty' => [''];
        yield 'text' => ['hello <b>x</b>'];
        yield 'comment only' => ['<!-- x -->'];
        yield 'closing tag' => ['</div>'];
        yield 'doctype' => ['<!doctype html><p>x</p>'];
        yield 'unterminated tag' => ['<div id="x'];
    }
}
