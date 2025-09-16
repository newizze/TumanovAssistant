<?php

declare(strict_types=1);

namespace App\DTOs\OpenAI;

final readonly class ConversationRequestDto
{
    public function __construct(
        public ?array $items = null,
        public ?array $metadata = null,
    ) {}

    public function toArray(): array
    {
        $data = [];

        if ($this->items !== null) {
            $data['items'] = $this->items;
        }

        if ($this->metadata !== null) {
            $data['metadata'] = $this->metadata;
        }

        return $data;
    }
}
