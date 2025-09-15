<?php

declare(strict_types=1);

namespace App\DTOs\TaskAnalysis;

class TaskAnalysisResponseDto
{
    public function __construct(
        public readonly bool $shouldCreateTask,
        public readonly ?string $taskTitle = null,
        public readonly ?string $taskDescription = null,
        public readonly ?string $priority = null,
        public readonly ?string $category = null,
        public readonly ?array $tags = null,
        public readonly ?string $dueDate = null,
        public readonly ?string $reasoning = null,
        public readonly array $extractedData = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            shouldCreateTask: $data['should_create_task'] ?? false,
            taskTitle: $data['task_title'] ?? null,
            taskDescription: $data['task_description'] ?? null,
            priority: $data['priority'] ?? null,
            category: $data['category'] ?? null,
            tags: $data['tags'] ?? null,
            dueDate: $data['due_date'] ?? null,
            reasoning: $data['reasoning'] ?? null,
            extractedData: $data['extracted_data'] ?? []
        );
    }

    public function toSpreadsheetRow(): array
    {
        if (!$this->shouldCreateTask) {
            return [];
        }

        return [
            $this->taskTitle ?? '',
            $this->taskDescription ?? '',
            $this->priority ?? 'Medium',
            $this->category ?? 'General',
            implode(', ', $this->tags ?? []),
            $this->dueDate ?? '',
            date('Y-m-d H:i:s'), // Created date
            'New', // Status
        ];
    }
}