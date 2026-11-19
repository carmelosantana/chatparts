<?php

declare(strict_types=1);

namespace ChatParts\Model\Part;

final readonly class ReasoningPart extends Part
{
    public function __construct(string $id, public string $text, public ?int $durationMs)
    {
        parent::__construct($id);
    }

    public function type(): string
    {
        return 'reasoning';
    }

    public function toArray(): array
    {
        return ['type' => 'reasoning', 'id' => $this->id, 'text' => $this->text, 'durationMs' => $this->durationMs];
    }

    public static function fromArray(array $a): self
    {
        return new self($a['id'], $a['text'], $a['durationMs'] ?? null);
    }
}
