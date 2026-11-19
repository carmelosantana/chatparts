<?php

declare(strict_types=1);

namespace ChatParts\Model;

final readonly class Participant
{
    /** @param string $kind human|agent|bot|system */
    public function __construct(
        public string $kind,
        public ?string $id,
        public string $name,
    ) {
    }

    /** @return array{kind: string, id: ?string, name: string} */
    public function toArray(): array
    {
        return ['kind' => $this->kind, 'id' => $this->id, 'name' => $this->name];
    }

    /** @param array{kind: string, id: ?string, name: string} $a */
    public static function fromArray(array $a): self
    {
        return new self($a['kind'], $a['id'] ?? null, $a['name']);
    }
}
