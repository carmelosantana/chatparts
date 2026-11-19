<?php

declare(strict_types=1);

namespace ChatParts\Tests\Support;

use ChatParts\Host\HostAdapter;
use ChatParts\Model\Participant;

/** A host with predictable output, for component tests. */
final class FakeHost implements HostAdapter
{
    public function __construct(private readonly ?string $viewerId = null)
    {
    }

    public function url(string $route, array $params = []): string
    {
        return '/' . $route . ($params === [] ? '' : '?' . http_build_query($params));
    }

    public function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="test-token">';
    }

    public function escape(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function markdown(string $text): string
    {
        return '<p>' . $this->escape($text) . '</p>';
    }

    public function avatarFor(Participant $p): array
    {
        return ['initials' => strtoupper(substr($p->name, 0, 2)), 'tone' => 'blue'];
    }

    public function t(string $s, mixed ...$args): string
    {
        return $args === [] ? $s : vsprintf($s, $args);
    }

    public function assetUrl(string $file): string
    {
        return '/assets/' . $file;
    }

    public function viewerId(): ?string
    {
        return $this->viewerId;
    }
}
