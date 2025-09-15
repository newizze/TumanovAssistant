<?php

declare(strict_types=1);

namespace App\DTOs\OpenAI;

final readonly class ConversationResponseDto
{
    public function __construct(
        public string $id,
        public string $object,
        public int $createdAt,
        public ?array $metadata = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            object: $data['object'],
            createdAt: $data['created_at'],
            metadata: $data['metadata'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'object' => $this->object,
            'created_at' => $this->createdAt,
            'metadata' => $this->metadata,
        ];
    }
}