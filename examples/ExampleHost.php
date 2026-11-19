<?php

declare(strict_types=1);

namespace ChatParts\Examples;

use ChatParts\Host\HostAdapter;
use ChatParts\Model\Participant;

/**
 * A minimal host for the examples: English strings, a tiny markdown subset (paragraphs, fenced
 * code, inline code, bold, bullet lists) and asset URLs relative to examples/.
 */
final class ExampleHost implements HostAdapter
{
    private const TONES = ['blue', 'purple', 'teal', 'orange', 'green', 'pink', 'slate'];

    public function url(string $route, array $params = []): string
    {
        return '#' . $route . ($params === [] ? '' : '?' . http_build_query($params));
    }

    public function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="example">';
    }

    public function escape(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function markdown(string $text): string
    {
        $html = '';
        foreach (preg_split('/\n{2,}/', trim($text)) ?: [] as $block) {
            if (preg_match('/^```\w*\n(.*?)\n?```$/s', $block, $m) === 1) {
                $html .= '<pre><code>' . $this->escape($m[1]) . '</code></pre>';
            } elseif (preg_match('/^- /', $block) === 1) {
                $items = array_map(fn (string $l): string => '<li>' . $this->inline(substr($l, 2)) . '</li>', explode("\n", $block));
                $html .= '<ul>' . implode('', $items) . '</ul>';
            } else {
                $html .= '<p>' . $this->inline($block) . '</p>';
            }
        }

        return $html;
    }

    public function avatarFor(Participant $p): array
    {
        $words = array_values(array_filter(preg_split('/[\s._-]+/', trim($p->name)) ?: []));
        $initials = count($words) > 1 ? $words[0][0] . $words[1][0] : substr($words[0] ?? '?', 0, 2);

        return ['initials' => strtoupper($initials),'tone' => self::TONES[crc32($p->name) % count(self::TONES)]];
    }

    public function t(string $s, mixed ...$args): string
    {
        return $args === [] ? $s : vsprintf($s, $args);
    }

    public function assetUrl(string $file): string
    {
        return '../assets/' . $file;
    }

    private function inline(string $s): string
    {
        $s = $this->escape($s);
        $s = (string) preg_replace('/`([^`]+)`/', '<code>$1</code>', $s);
        $s = (string) preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $s);

        return nl2br($s, false);
    }
}
