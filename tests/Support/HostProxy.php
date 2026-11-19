<?php

declare(strict_types=1);

namespace ChatParts\Tests\Support;

use ChatParts\Host\HostAdapter;
use ChatParts\Model\Participant;

/** FakeHost behind an open class, so a test can override one method in an anonymous subclass. */
class HostProxy implements HostAdapter
{
    private readonly FakeHost $fake;

    public function __construct()
    {
        $this->fake = new FakeHost();
    }

    public function url(string $route, array $params = []): string
    {
        return $this->fake->url($route, $params);
    }

    public function csrfField(): string
    {
        return $this->fake->csrfField();
    }

    public function escape(string $s): string
    {
        return $this->fake->escape($s);
    }

    public function markdown(string $text): string
    {
        return $this->fake->markdown($text);
    }

    public function avatarFor(Participant $p): array
    {
        return $this->fake->avatarFor($p);
    }

    public function t(string $s, mixed ...$args): string
    {
        return $this->fake->t($s, ...$args);
    }

    public function assetUrl(string $file): string
    {
        return $this->fake->assetUrl($file);
    }
}
