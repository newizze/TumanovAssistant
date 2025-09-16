<?php

declare(strict_types=1);

namespace App\DTOs\TaskAnalysis;

class TaskAnalysisRequestDto
{
    public function __construct(
        public readonly string $messageText,
        public readonly ?string $userId = null,
        public readonly ?string $chatId = null,
        public readonly array $context = []
    ) {}

    public function toArray(): array
    {
        return [
            'message_text' => $this->messageText,
            'user_id' => $this->userId,
            'chat_id' => $this->chatId,
            'context' => $this->context,
        ];
    }
}
