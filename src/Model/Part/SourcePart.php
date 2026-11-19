<?php

declare(strict_types=1);

namespace ChatParts\Model\Part;

final readonly class SourcePart extends Part
{
    /**
     * @param int    $n    citation number shown in the text, 1-based
     * @param string $kind task|comment|knowledge
     * @param string $ref  host-side identifier of the cited record
     */
    public function __construct(
        string $id,
        public int $n,
        public string $title,
        public string $url,
        public string $kind,
        public string $ref,
    ) {
        parent::__construct($id);
    }

    public function type(): string
    {
        return 'source';
    }

    public function toArray(): array
    {
        return [
            'type' => 'source',
            'id' => $this->id,
            'n' => $this->n,
            'title' => $this->title,
            'url' => $this->url,
            'kind' => $this->kind,
            'ref' => $this->ref,
        ];
    }

    public static function fromArray(array $a): self
    {
        return new self($a['id'], $a['n'], $a['title'], $a['url'], $a['kind'], $a['ref']);
    }
}
