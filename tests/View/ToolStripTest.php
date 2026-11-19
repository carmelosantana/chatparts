<?php

declare(strict_types=1);

namespace ChatParts\Tests\View;

use ChatParts\Model\Part\SourcePart;
use ChatParts\Model\Part\ToolPart;
use ChatParts\Model\ToolState;
use ChatParts\Tests\Support\FakeHost;
use ChatParts\View\ToolStrip;
use PHPUnit\Framework\TestCase;

final class ToolStripTest extends TestCase
{
    public function testStatesBecomeDataState(): void
    {
        $tools = [
            new ToolPart('a', 'search_tasks', ['q' => 'x'], ToolState::Done, '3 tasks', null),
            new ToolPart('b', 'get_task', [], ToolState::Running, null, null),
            new ToolPart('c', 'get<task>', [], ToolState::Error, null, 'not <allowed>'),
        ];
        $sources = [
            new SourcePart('s1', 1, 'A', '/a', 'task', '1'),
            new SourcePart('s2', 2, 'B', '/b', 'task', '2'),
        ];
        $html = (new ToolStrip(new FakeHost(), 'm1', $tools, $sources))->render();

        $this->assertStringStartsWith('<details id="cp-tools-m1" class="cp-tools">', $html);
        $this->assertStringNotContainsString(' open', $html);
        $this->assertStringContainsString('Searched 2 sources · 3 tools', $html);
        $this->assertSame(3, substr_count($html, '<li class="cp-tool"'));
        $this->assertStringContainsString('<li class="cp-tool" data-state="done">', $html);
        $this->assertStringContainsString('<li class="cp-tool" data-state="running">', $html);
        $this->assertStringContainsString('<li class="cp-tool" data-state="error">', $html);
        $this->assertStringContainsString('3 tasks', $html);
        $this->assertStringContainsString('get&lt;task&gt;', $html);
        $this->assertStringContainsString('not &lt;allowed&gt;', $html);
    }

    public function testWithoutSourcesTheSummarySaysUsedTools(): void
    {
        $html = (new ToolStrip(new FakeHost(), 'm1', [new ToolPart('a', 'x', [], ToolState::Pending, null, null), new ToolPart('b', 'y', [], ToolState::Denied, null, null)]))->render();

        $this->assertStringContainsString('Used 2 tools', $html);
        $this->assertStringContainsString('data-state="denied"', $html);
    }
}
