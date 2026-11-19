<?php

declare(strict_types=1);

namespace ChatParts\Tests\View;

use ChatParts\Model\Part\SourcePart;
use ChatParts\Tests\Support\FakeHost;
use ChatParts\View\InlineCitation;
use ChatParts\View\SourceList;
use PHPUnit\Framework\TestCase;

final class SourcesTest extends TestCase
{
    public function testOnlyGivenSourcesRender(): void
    {
        $html = (new SourceList(new FakeHost(), [
            new SourcePart('s1', 1, 'Task <4>', 'https://kb.test/task/4', 'task', '4'),
            new SourcePart('s3', 3, 'Note', '/k/3', 'knowledge', 'k3'),
        ]))->render();

        $this->assertSame(2, substr_count($html, '<li '));
        $this->assertStringContainsString('Task &lt;4&gt;', $html);
        $this->assertStringContainsString('href="https://kb.test/task/4"', $html);
        $this->assertStringContainsString('href="/k/3"', $html);
        $this->assertStringContainsString('data-kind="knowledge"', $html);
        $this->assertStringNotContainsString('>2<', $html);
    }

    public function testEmptyListRendersNothing(): void
    {
        $this->assertSame('', (new SourceList(new FakeHost(), []))->render());
    }

    public function testUnsafeSourceIsNotALink(): void
    {
        $html = (new SourceList(new FakeHost(), [new SourcePart('s1', 1, 'Bad', 'javascript:x', 'task', '1')]))->render();

        $this->assertStringNotContainsString('<a ', $html);
        $this->assertStringNotContainsString('javascript', $html);
        $this->assertStringContainsString('Bad', $html);
    }

    public function testCitationWithoutSourceRendersNothing(): void
    {
        $host = new FakeHost();
        $this->assertSame('', (new InlineCitation($host, 9, null))->render());
        $this->assertSame(
            '<a class="cp-cite" href="/t?a=1&amp;b=2">4</a>',
            (new InlineCitation($host, 4, new SourcePart('s', 4, 'T', '/t?a=1&b=2', 'task', '1')))->render(),
        );
    }
}
