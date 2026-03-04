<?php

declare(strict_types=1);

namespace App\DTOs\TicketsApp;

class TicketsAppTicketCreateDto
{
    /**
     * @param  array<int, string>  $fileLinks
     */
    public function __construct(
        public readonly string $shortTitle,
        public readonly string $description,
        public readonly string $expectedResult,
        public readonly int $priorityId,
        public readonly int $taskTypeId,
        public readonly int $executorId,
        public readonly string $authorTelegramUsername,
        public readonly array $fileLinks = [],
        public readonly bool $needsReview = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'short_title' => $this->shortTitle,
            'description' => $this->description,
            'expected_result' => $this->expectedResult,
            'priority_id' => $this->priorityId,
            'task_type_id' => $this->taskTypeId,
            'executor_id' => $this->executorId,
            'author_telegram_username' => $this->authorTelegramUsername,
            'file_links' => $this->fileLinks,
            'needs_review' => $this->needsReview,
        ];
    }
}
