<?php

declare(strict_types=1);

namespace ChatParts\Tests\View;

use ChatParts\Tests\Support\FakeHost;
use ChatParts\View\Icon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class IconTest extends TestCase
{
    public function testRendersUseWithAriaHidden(): void
    {
        $this->assertSame(
            '<svg class="cp-icon" aria-hidden="true" focusable="false"><use href="/assets/icons.svg#check"/></svg>',
            (string) new Icon(new FakeHost(), 'check'),
        );
    }

    public function testCustomClassIsEscaped(): void
    {
        $this->assertSame(
            '<svg class="cp-icon cp-&quot;x" aria-hidden="true" focusable="false"><use href="/assets/icons.svg#bot"/></svg>',
            (new Icon(new FakeHost(), 'bot', 'cp-icon cp-"x'))->render(),
        );
    }

    public function testUnknownIconThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Icon(new FakeHost(), 'not-an-icon');
    }

    public function testSpriteHoldsExactlyTheLockedIcons(): void
    {
        $lock = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/scripts/icons.lock'), true, 512, JSON_THROW_ON_ERROR);
        preg_match_all('/<symbol id="([a-z0-9-]+)"/', (string) file_get_contents(dirname(__DIR__, 2) . '/assets/icons.svg'), $m);

        $this->assertSame(array_keys($lock['icons']), $m[1]);
        $this->assertSame(Icon::names(), $m[1]);
    }
}
