<?php

declare(strict_types=1);

namespace ChatParts\Tests\Markdown;

use ChatParts\Markdown\SafeText;
use PHPUnit\Framework\TestCase;

final class SafeTextTest extends TestCase
{
    public function testEscapesHtml(): void
    {
        $this->assertSame(
            '<p>&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; &#039;q&#039;</p>',
            SafeText::render('<script>alert("x")</script> & \'q\''),
        );
    }

    public function testAutolinksOnlyHttp(): void
    {
        $html = SafeText::render('see https://example.test/a?b=1&c=2 or javascript:alert(1) or ftp://x.test');

        $this->assertSame(
            '<p>see <a href="https://example.test/a?b=1&amp;c=2" rel="nofollow noopener noreferrer" target="_blank">'
            . 'https://example.test/a?b=1&amp;c=2</a> or javascript:alert(1) or ftp://x.test</p>',
            $html,
        );
    }

    public function testLinkStopsAtQuotesAndTrailingPunctuation(): void
    {
        $this->assertSame(
            '<p>(<a href="http://x.test/p" rel="nofollow noopener noreferrer" target="_blank">http://x.test/p</a>). '
            . '&quot;<a href="https://y.test" rel="nofollow noopener noreferrer" target="_blank">https://y.test</a>&quot;&lt;b&gt;</p>',
            SafeText::render('(http://x.test/p). "https://y.test"<b>'),
        );
    }

    public function testParagraphsAndLineBreaks(): void
    {
        $this->assertSame(
            "<p>one<br>\ntwo</p>\n<p>three</p>",
            SafeText::render("  one\r\ntwo\n\n\n  \nthree\n"),
        );
    }

    public function testEmptyTextRendersNothing(): void
    {
        $this->assertSame('', SafeText::render(" \n\n "));
    }
}
