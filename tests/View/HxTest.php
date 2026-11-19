<?php

declare(strict_types=1);

namespace ChatParts\Tests\View;

use ChatParts\View\Hx;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HxTest extends TestCase
{
    /** @dataProvider unknownKeys */
    public function testRejectsUnknownAttribute(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);

        Hx::attrs([$key => 'x']);
    }

    /** @return iterable<string, array{string}> */
    public static function unknownKeys(): iterable
    {
        yield 'event handler' => ['onclick'];
        yield 'verb not on the list' => ['delete'];
        yield 'htmx on:' => ['on:click'];
        yield 'prefixed form' => ['hx-get'];
        yield 'attribute breakout' => ['get" onload="x'];
    }

    public function testJsonEncodesValsAndHeaders(): void
    {
        $this->assertSame(
            ['hx-vals' => '{"room":"r/1","n":2}', 'hx-headers' => '{"X-CSRF-Token":"t<>"}'],
            Hx::attrs(['vals' => ['room' => 'r/1', 'n' => 2], 'headers' => ['X-CSRF-Token' => 't<>']]),
        );
    }

    public function testEmptyValsEncodeAsAnObject(): void
    {
        $this->assertSame(['hx-vals' => '{}'], Hx::attrs(['vals' => []]));
    }

    public function testMapsKeysToAttributeNames(): void
    {
        $this->assertSame(
            [
                'hx-get' => '/room?after=4',
                'hx-post' => '/send',
                'hx-target' => '#cp-log',
                'hx-swap' => 'beforeend',
                'hx-trigger' => 'every 4s',
                'hx-ext' => 'sse',
                'sse-connect' => '/stream?sid=a',
                'sse-swap' => 'delta',
                'sse-close' => 'done',
                'hx-swap-oob' => 'true',
                'hx-indicator' => '#spin',
                'hx-disabled-elt' => 'find button',
            ],
            Hx::attrs([
                'get' => '/room?after=4',
                'post' => '/send',
                'target' => '#cp-log',
                'swap' => 'beforeend',
                'trigger' => 'every 4s',
                'ext' => 'sse',
                'sse-connect' => '/stream?sid=a',
                'sse-swap' => 'delta',
                'sse-close' => 'done',
                'swap-oob' => 'true',
                'indicator' => '#spin',
                'disabled-elt' => 'find button',
            ]),
        );
    }

    public function testNullValueIsKeptForTagToDrop(): void
    {
        $this->assertSame(['hx-indicator' => null], Hx::attrs(['indicator' => null]));
    }

    public function testArrayOnlyForValsAndHeaders(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Hx::attrs(['get' => ['x' => 1]]);
    }
}
