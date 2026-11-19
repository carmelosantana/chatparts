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

    public function testWorksFromAVendoredCopyOfSrcAndAssetsOnly(): void
    {
        $copy = $this->vendoredCopy();

        $this->assertSame(
            ['ok', '<svg class="cp-icon" aria-hidden="true" focusable="false"><use href="/v/icons.svg#bot"/></svg>'],
            $this->renderIn($copy, 'bot'),
        );
    }

    public function testMissingSpriteThrowsAClearError(): void
    {
        $copy = $this->vendoredCopy();
        unlink($copy . '/assets/icons.svg');

        [$status, $out] = $this->renderIn($copy, 'bot');

        $this->assertSame('error', $status);
        $this->assertStringStartsWith('LogicException: Icon sprite not readable: ', $out);
    }

    public function testSpriteHoldsExactlyTheLockedIcons(): void
    {
        $lock = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/scripts/icons.lock'), true, 512, JSON_THROW_ON_ERROR);
        preg_match_all('/<symbol id="([a-z0-9-]+)"/', (string) file_get_contents(dirname(__DIR__, 2) . '/assets/icons.svg'), $m);

        $this->assertSame(array_keys($lock['icons']), $m[1]);
        $this->assertSame(Icon::names(), $m[1]);
    }

    /** A temp dir holding only what a host vendors: src/ and assets/. */
    private function vendoredCopy(): string
    {
        $root = dirname(__DIR__, 2);
        $copy = sys_get_temp_dir() . '/cp-vendored-' . bin2hex(random_bytes(4));
        mkdir($copy);
        exec('cp -R ' . escapeshellarg($root . '/src') . ' ' . escapeshellarg($root . '/assets') . ' ' . escapeshellarg($copy), $o, $code);
        $this->assertSame(0, $code);
        copy($root . '/autoload.php', $copy . '/autoload.php');
        $this->assertDirectoryDoesNotExist($copy . '/scripts');

        return $copy;
    }

    /**
     * Renders an icon in a fresh PHP process (Icon caches its names per process) that loads
     * only the copy's autoloader. Any PHP warning or notice is promoted to an exception.
     *
     * @return array{string, string} [ok|error, rendered html or "Class: message"]
     */
    private function renderIn(string $copy, string $name): array
    {
        $code = <<<'PHP'
            set_error_handler(static function (int $no, string $msg): never { throw new ErrorException($msg, 0, $no); });
            require $argv[1] . '/autoload.php';
            $host = new class implements ChatParts\Host\HostAdapter {
                public function url(string $route, array $params = []): string { return ''; }
                public function csrfField(): string { return ''; }
                public function escape(string $s): string { return htmlspecialchars($s); }
                public function markdown(string $text): string { return ''; }
                public function avatarFor(ChatParts\Model\Participant $p): array { return ['initials' => '', 'tone' => '']; }
                public function t(string $s, mixed ...$args): string { return $s; }
                public function assetUrl(string $file): string { return '/v/' . $file; }
                public function viewerId(): ?string { return null; }
            };
            try {
                echo json_encode(['ok', (string) new ChatParts\View\Icon($host, $argv[2])]);
            } catch (Throwable $e) {
                echo json_encode(['error', $e::class . ': ' . $e->getMessage()]);
            }
            PHP;
        $out = shell_exec(implode(' ', array_map('escapeshellarg', [PHP_BINARY, '-r', $code, '--', $copy, $name])) . ' 2>&1');
        exec('rm -rf ' . escapeshellarg($copy));

        return json_decode((string) $out, true, 512, JSON_THROW_ON_ERROR);
    }
}
