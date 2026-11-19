<?php

declare(strict_types=1);

namespace ChatParts\Model\Part;

use InvalidArgumentException;

abstract readonly class Part
{
    public function __construct(public string $id)
    {
    }

    abstract public function type(): string;

    /** @return array<string, mixed> always carries 'type' and 'id' */
    abstract public function toArray(): array;

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): Part
    {
        return match ($a['type'] ?? null) {
            'text' => TextPart::fromArray($a),
            'reasoning' => ReasoningPart::fromArray($a),
            'tool' => ToolPart::fromArray($a),
            'source' => SourcePart::fromArray($a),
            'file' => FilePart::fromArray($a),
            'data' => DataPart::fromArray($a),
            default => throw new InvalidArgumentException(
                'Unknown part type: ' . var_export($a['type'] ?? null, true),
            ),
        };
    }
}
