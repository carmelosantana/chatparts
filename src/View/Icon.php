<?php

declare(strict_types=1);

namespace ChatParts\View;

use ChatParts\Host\HostAdapter;
use InvalidArgumentException;

/**
 * A Lucide icon from the sprite at assets/icons.svg. Decorative: hidden from assistive tech,
 * so the control around it carries the accessible name. The names allowed are the ones in
 * scripts/icons.lock, the same list the sprite was built from.
 */
final class Icon extends Component
{
    private const LOCK = __DIR__ . '/../../scripts/icons.lock';

    /** @var list<string>|null */
    private static ?array $names = null;

    /** @throws InvalidArgumentException for a name that is not in the sprite */
    public function __construct(HostAdapter $host, private readonly string $name, private readonly string $class = 'cp-icon')
    {
        parent::__construct($host);
        if (!in_array($name, self::names(), true)) {
            throw new InvalidArgumentException("Unknown icon: {$name}");
        }
    }

    /** @return list<string> the icon names in the sprite, in lock order */
    public static function names(): array
    {
        if (self::$names === null) {
            $lock = json_decode((string) file_get_contents(self::LOCK), true, 512, JSON_THROW_ON_ERROR);
            self::$names = array_keys($lock['icons']);
        }

        return self::$names;
    }

    public function render(): string
    {
        $href = $this->a($this->host->assetUrl('icons.svg') . '#' . $this->name);

        return $this->tag('svg', ['class' => $this->class, 'aria-hidden' => 'true', 'focusable' => 'false'], "<use href=\"{$href}\"/>");
    }
}
