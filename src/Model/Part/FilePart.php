<?php

declare(strict_types=1);

namespace ChatParts\Model\Part;

final readonly class FilePart extends Part
{
    public function __construct(
        string $id,
        public string $name,
        public string $mime,
        public string $url,
        public ?string $thumbUrl,
    ) {
        parent::__construct($id);
    }

    public function type(): string
    {
        return 'file';
    }

    public function toArray(): array
    {
        return [
            'type' => 'file',
            'id' => $this->id,
            'name' => $this->name,
            'mime' => $this->mime,
            'url' => $this->url,
            'thumbUrl' => $this->thumbUrl,
        ];
    }

    public static function fromArray(array $a): self
    {
        return new self($a['id'], $a['name'], $a['mime'], $a['url'], $a['thumbUrl'] ?? null);
    }
}
