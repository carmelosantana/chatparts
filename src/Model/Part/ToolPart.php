<?php

declare(strict_types=1);

namespace ChatParts\Model\Part;

use ChatParts\Model\ToolState;

final readonly class ToolPart extends Part
{
    /** @param array<string, mixed> $input */
    public function __construct(
        string $id,
        public string $name,
        public array $input,
        public ToolState $state,
        public ?string $outputSummary,
        public ?string $error,
    ) {
        parent::__construct($id);
    }

    public function type(): string
    {
        return 'tool';
    }

    public function toArray(): array
    {
        return [
            'type' => 'tool',
            'id' => $this->id,
            'name' => $this->name,
            'input' => $this->input,
            'state' => $this->state->value,
            'outputSummary' => $this->outputSummary,
            'error' => $this->error,
        ];
    }

    public static function fromArray(array $a): self
    {
        return new self(
            $a['id'],
            $a['name'],
            $a['input'] ?? [],
            ToolState::from($a['state']),
            $a['outputSummary'] ?? null,
            $a['error'] ?? null,
        );
    }
}
