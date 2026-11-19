<?php

declare(strict_types=1);

namespace ChatParts\Tests\View;

use ChatParts\Tests\Support\FakeHost;
use ChatParts\View\ContextMeter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ContextMeterTest extends TestCase
{
    public function testRingDashFromSegments(): void
    {
        $html = (new ContextMeter(new FakeHost(), [
            ['key' => 'instructions', 'label' => 'Instructions', 'tokens' => 1_600],
            ['key' => 'history', 'label' => 'History', 'tokens' => 12_000],
            ['key' => 'tools', 'label' => 'Tools', 'tokens' => 3_500],
            ['key' => 'reply', 'label' => 'Reply reserve', 'tokens' => 4_000],
        ], 200_000))->render();

        $this->assertStringContainsString('stroke-dasharray="4.6 44.0"', $html);
        $this->assertStringContainsString('<span class="cp-ring__pct" aria-hidden="true">11%</span>', $html);
        $this->assertStringContainsString('<span class="cp-sr">11% of context used</span>', $html);
        $this->assertStringContainsString('<div id="cp-context-pop" hx-swap-oob="innerHTML">', $html);
        $this->assertStringContainsString('<span class="cp-context__label">History</span><span class="cp-context__tokens">12,000</span>', $html);
        $this->assertStringContainsString('21,100 of 200,000 tokens', $html);
    }

    public function testZeroTokenSegmentsHidden(): void
    {
        $html = (new ContextMeter(new FakeHost(), [
            ['key' => 'history', 'label' => 'History', 'tokens' => 100],
            ['key' => 'images', 'label' => 'Images <b>', 'tokens' => 0],
        ], 1_000))->render();

        $this->assertStringContainsString('data-key="history"', $html);
        $this->assertStringNotContainsString('images', $html);
        $this->assertStringNotContainsString('Images', $html);
    }

    public function testOverfullClampsToFullRing(): void
    {
        $html = (new ContextMeter(new FakeHost(), [['key' => 'history', 'label' => 'History', 'tokens' => 5_000]], 1_000))->render();

        $this->assertStringContainsString('stroke-dasharray="44.0 44.0"', $html);
        $this->assertStringContainsString('>100%<', $html);
    }

    public function testLabelsAreEscapedAndBadKeysDropped(): void
    {
        $html = (new ContextMeter(new FakeHost(), [['key' => 'x" onclick="1', 'label' => '<i>a</i>', 'tokens' => 1]], 10))->render();

        $this->assertStringContainsString('&lt;i&gt;a&lt;/i&gt;', $html);
        $this->assertStringNotContainsString('onclick', $html);
    }

    public function testWindowMustBePositive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ContextMeter(new FakeHost(), [], 0);
    }

    public function testMalformedSegmentIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ContextMeter(new FakeHost(), [['key' => 'h', 'label' => 'H', 'tokens' => -1]], 10);
    }
}
