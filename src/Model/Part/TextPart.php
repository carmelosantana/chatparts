<?php

declare(strict_types=1);

namespace ChatParts\Model\Part;

final readonly class TextPart extends Part
{
    public function __construct(string $id, public string $text)
    {
        parent::__construct($id);
    }

    public function type(): string
    {
        return 'text';
    }

    public function toArray(): array
    {
        return ['type' => 'text', 'id' => $this->id, 'text' => $this->text];
    }

    public static function fromArray(array $a): self
    {
        return new self($a['id'], $a['text']);
    }
}
