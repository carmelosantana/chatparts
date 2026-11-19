<?php

declare(strict_types=1);

namespace ChatParts\Model;

use ChatParts\Model\Part\Part;
use ChatParts\Model\Part\TextPart;
use InvalidArgumentException;

final readonly class Message
{
    /** @var list<Part> */
    public array $parts;

    /**
     * @param list<Part>           $parts
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $id,
        public string $room,
        public Participant $participant,
        public MessageStatus $status,
        array $parts,
        public array $metadata,
        public int $createdAt,
    ) {
        $this->parts = array_values($parts);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'room' => $this->room,
            'participant' => $this->participant->toArray(),
            'status' => $this->status->value,
            'parts' => array_map(static fn (Part $p): array => $p->toArray(), $this->parts),
            'metadata' => $this->metadata,
            'createdAt' => $this->createdAt,
        ];
    }

    /** @param array<string, mixed> $a */
    public static function fromArray(array $a): self
    {
        return new self(
            $a['id'],
            $a['room'],
            Participant::fromArray($a['participant']),
            MessageStatus::from($a['status']),
            array_map(static fn (array $p): Part => Part::fromArray($p), $a['parts']),
            $a['metadata'] ?? [],
            $a['createdAt'],
        );
    }

    /** Text parts only, joined by a blank line; reasoning and tool output are excluded. */
    public function plainText(): string
    {
        $texts = [];
        foreach ($this->parts as $part) {
            if ($part instanceof TextPart) {
                $texts[] = $part->text;
            }
        }

        return implode("\n\n", $texts);
    }

    public function withPart(Part $part): self
    {
        return $this->withParts([...$this->parts, $part]);
    }

    /** Swaps the part with the same id; throws when no part has that id. */
    public function replacePart(Part $part): self
    {
        $parts = $this->parts;
        foreach ($parts as $i => $existing) {
            if ($existing->id === $part->id) {
                $parts[$i] = $part;

                return $this->withParts($parts);
            }
        }

        throw new InvalidArgumentException("No part with id '{$part->id}'.");
    }

    /** @param list<Part> $parts */
    private function withParts(array $parts): self
    {
        return new self(
            $this->id,
            $this->room,
            $this->participant,
            $this->status,
            $parts,
            $this->metadata,
            $this->createdAt,
        );
    }
}
