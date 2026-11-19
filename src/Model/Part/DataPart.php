<?php

declare(strict_types=1);

namespace ChatParts\Model\Part;

/** Host-defined structured payload; $type names the payload kind (serialised as 'dataType'). */
final readonly class DataPart extends Part
{
    /** @param array<string, mixed> $payload */
    public function __construct(string $id, public string $type, public array $payload)
    {
        parent::__construct($id);
    }

    public function type(): string
    {
        return 'data';
    }

    public function toArray(): array
    {
        return ['type' => 'data', 'id' => $this->id, 'dataType' => $this->type, 'payload' => $this->payload];
    }

    public static function fromArray(array $a): self
    {
        return new self($a['id'], $a['dataType'], $a['payload'] ?? []);
    }
}
