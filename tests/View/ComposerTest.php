<?php

declare(strict_types=1);

namespace ChatParts\Tests\View;

use ChatParts\Tests\Support\FakeHost;
use ChatParts\View\Composer;
use PHPUnit\Framework\TestCase;

final class ComposerTest extends TestCase
{
    private const OPTS = ['room' => '3', 'allowImages' => true, 'canAsk' => true, 'contextUrl' => '/context?room=3', 'stopUrl' => '/stop?room=3'];

    public function testHasCsrfAndClientMsgId(): void
    {
        $html = (new Composer(new FakeHost(), '/send', self::OPTS))->render();

        $this->assertStringStartsWith('<form class="cp-box" method="post" action="/send"', $html);
        $this->assertStringContainsString('<input type="hidden" name="csrf_token" value="test-token">', $html);
        $this->assertMatchesRegularExpression('/<input type="hidden" name="client_msg_id" value="[0-9a-f]{32}">/', $html);
        $this->assertStringContainsString('<input type="hidden" name="room" value="3">', $html);
        $this->assertStringContainsString('name="message"', $html);
        $this->assertStringContainsString('<button type="submit" class="cp-send" data-state="ready" data-stop-url="/stop?room=3" aria-label="Send">', $html);
        $this->assertStringContainsString('hx-get="/context?room=3" hx-trigger="input changed delay:500ms from:closest form, cp-attachments delay:500ms from:closest form, click once"', $html);
        $this->assertStringContainsString('hx-target="this" hx-swap="innerHTML" aria-controls="cp-context-pop" aria-expanded="false"><svg', $html);
        $this->assertStringContainsString('<span class="cp-sr">Context used</span></button>', $html);
        $this->assertStringContainsString('<div id="cp-context-pop" class="cp-context__pop" hidden=""></div>', $html);
    }

    public function testTextareaIsCappedAndRequired(): void
    {
        $html = (new Composer(new FakeHost(), '/send', self::OPTS))->render();

        $this->assertMatchesRegularExpression('/<textarea id="cp-message" class="cp-box__input" name="message" rows="1" maxlength="8000" required="" placeholder="[^"]*"><\/textarea>/', $html);
    }

    public function testNoAtBotHintWhenCannotAsk(): void
    {
        $this->assertStringContainsString('@bot', (new Composer(new FakeHost(), '/send', self::OPTS))->render());
        $html = (new Composer(new FakeHost(), '/send', ['canAsk' => false] + self::OPTS))->render();

        $this->assertStringNotContainsString('@bot', $html);
        $this->assertStringContainsString('placeholder="Message the room"', $html);
    }

    public function testPlaceholderFromOptsIsEscaped(): void
    {
        $html = (new Composer(new FakeHost(), '/send', ['placeholder' => 'Ask "x" <y>'] + self::OPTS))->render();

        $this->assertStringContainsString('placeholder="Ask &quot;x&quot; &lt;y&gt;"', $html);
    }

    public function testImageInputHiddenWhenImagesNotAllowed(): void
    {
        $with = (new Composer(new FakeHost(), '/send', self::OPTS))->render();
        $this->assertStringContainsString('<input type="file" id="cp-images" name="images[]" accept="image/png,image/jpeg,image/webp,image/gif" multiple="" hidden="">', $with);
        $this->assertStringContainsString('<div id="cp-thumbs" class="cp-thumbs"></div>', $with);
        $this->assertStringContainsString('data-action="attach"', $with);
        $this->assertStringContainsString('enctype="multipart/form-data"', $with);

        $html = (new Composer(new FakeHost(), '/send', ['allowImages' => false] + self::OPTS))->render();
        $this->assertStringNotContainsString('type="file"', $html);
        $this->assertStringNotContainsString('data-action="attach"', $html);
        $this->assertStringNotContainsString('#plus', $html);
        $this->assertStringNotContainsString('cp-thumbs', $html);
        $this->assertStringNotContainsString('multipart', $html);
    }

    public function testBotWontSeeImagesHintFollowsTheImagesThumbs(): void
    {
        $html = (new Composer(new FakeHost(), '/send', ['botSeesImages' => false] + self::OPTS))->render();
        $this->assertStringContainsString('<div id="cp-thumbs" class="cp-thumbs"></div><p class="cp-thumbs__hint">The bot won&#039;t see images</p>', $html);

        $this->assertStringNotContainsString('cp-thumbs__hint', (new Composer(new FakeHost(), '/send', self::OPTS))->render(), 'by default the bot sees images');
        $this->assertStringNotContainsString('cp-thumbs__hint', (new Composer(new FakeHost(), '/send', ['botSeesImages' => false, 'allowImages' => false] + self::OPTS))->render(), 'no images, no hint');
    }

    public function testUnsafeUrlsAreDropped(): void
    {
        $html = (new Composer(new FakeHost(), 'javascript:alert(1)', ['contextUrl' => 'javascript:x', 'stopUrl' => 'data:x'] + self::OPTS))->render();

        $this->assertStringNotContainsString('javascript', $html);
        $this->assertStringNotContainsString('data:x', $html);
        $this->assertStringNotContainsString('hx-get', $html, 'no ring without a safe context URL');
    }
}
