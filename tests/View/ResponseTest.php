<?php

declare(strict_types=1);

namespace ChatParts\Tests\View;

use ChatParts\Host\HostAdapter;
use ChatParts\Model\Part\SourcePart;
use ChatParts\Model\Part\TextPart;
use ChatParts\Tests\Support\FakeHost;
use ChatParts\Tests\Support\HostProxy;
use ChatParts\View\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testStreamingTextIsEscaped(): void
    {
        $html = (new Response(new FakeHost(), new TextPart('t1', 'hi <img src=x onerror=1> [1]'), true))->render();

        $this->assertSame('<div id="cp-text-t1" class="cp-response cp-caret">hi &lt;img src=x onerror=1&gt; [1]</div>', $html);
    }

    public function testFinalUsesHostMarkdownAndRewritesCitations(): void
    {
        $sources = [new SourcePart('s1', 1, 'Task #4', 'https://kb.test/task/4', 'task', '4')];
        $html = (new Response(new FakeHost(), new TextPart('t1', 'See [1] and [9].'), false, $sources))->render();

        $this->assertSame(
            '<div id="cp-text-t1" class="cp-response"><p>See <a class="cp-cite" href="https://kb.test/task/4">1</a> and .</p></div>',
            $html,
        );
    }

    public function testMarkersInsideCodeAndTagsAreLeftAlone(): void
    {
        $sources = [new SourcePart('s1', 1, 'A', '/a', 'task', '1')];
        $md = '<p data-x="[1]">[1] <code>[1]</code> <pre><b>[1]</b></pre> [0] [100]</p>';
        $html = (new Response(self::rawMarkdownHost(), new TextPart('t1', $md), false, $sources))->render();

        $this->assertSame(
            '<div id="cp-text-t1" class="cp-response"><p data-x="[1]"><a class="cp-cite" href="/a">1</a> <code>[1]</code> <pre><b>[1]</b></pre> [0] [100]</p></div>',
            $html,
        );
    }

    public function testUnsafeSourceUrlRendersAPlainMarker(): void
    {
        $sources = [new SourcePart('s1', 2, 'Bad', 'javascript:alert(1)', 'task', '1')];
        $html = (new Response(new FakeHost(), new TextPart('t1', 'x [2]'), false, $sources))->render();

        $this->assertStringContainsString('x <span class="cp-cite">2</span>', $html);
        $this->assertStringNotContainsString('javascript', $html);
    }

    /** A host whose markdown() returns its input untouched, so a test can hand it exact HTML. */
    private static function rawMarkdownHost(): HostAdapter
    {
        return new class () extends HostProxy {
            public function markdown(string $text): string
            {
                return $text;
            }
        };
    }
}
