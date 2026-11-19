<?php

declare(strict_types=1);

namespace ChatParts\Tests\View;

use ChatParts\Host\HostAdapter;
use ChatParts\Tests\Support\FakeHost;
use ChatParts\View\Component;
use ChatParts\View\Hx;
use PHPUnit\Framework\TestCase;

final class ComponentTest extends TestCase
{
    public function testTagEscapesValuesAndDropsNullAttributes(): void
    {
        $c = new class (new FakeHost()) extends Component {
            public function render(): string
            {
                return $this->tag('div', ['class' => 'a"b<c>', 'hidden' => null, 'data-x' => ''], 'inner')
                    . $this->tag('input', ['name' => 'm']);
            }
        };

        $this->assertSame('<div class="a&quot;b&lt;c&gt;" data-x="">inner</div><input name="m">', (string) $c);
    }

    public function testHxAttributesAreEscapedByTag(): void
    {
        $c = new class (new FakeHost()) extends Component {
            public function render(): string
            {
                return $this->tag('form', Hx::attrs(['post' => '/send?a=1&b=2', 'vals' => ['q' => '"x"']]));
            }
        };

        $this->assertSame(
            '<form hx-post="/send?a=1&amp;b=2" hx-vals="{&quot;q&quot;:&quot;\&quot;x\&quot;&quot;}"></form>',
            $c->render(),
        );
    }

    public function testUrlHelperRefusesScriptSchemes(): void
    {
        $c = new class (new FakeHost()) extends Component {
            public function render(): string
            {
                return implode('|', array_map($this->u(...), [
                    'https://example.test/a?b=1&c=2', '/room/1', 'javascript:alert(1)', ' JavaScript:x', 'data:text/html,x', 'mailto:a@b.test',
                ]));
            }
        };

        $this->assertSame('https://example.test/a?b=1&amp;c=2|/room/1||||mailto:a@b.test', $c->render());
    }

    public function testHostIsAvailableToSubclasses(): void
    {
        $c = new class (new FakeHost()) extends Component {
            public function render(): string
            {
                return $this->e('<b>') . $this->host->t('%d new', 3);
            }

            public function host(): HostAdapter
            {
                return $this->host;
            }
        };

        $this->assertSame('&lt;b&gt;3 new', $c->render());
        $this->assertInstanceOf(FakeHost::class, $c->host());
    }
}
