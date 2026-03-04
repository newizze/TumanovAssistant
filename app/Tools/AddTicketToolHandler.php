<?php

declare(strict_types=1);

namespace App\Tools;

use App\Actions\CreateTicketInAppAction;
use Illuminate\Support\Facades\Log;

class AddTicketToolHandler
{
    public function __construct(
        private readonly CreateTicketInAppAction $createTicketAction
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function handle(array $arguments): array
    {
        try {
            Log::info('Handling create_ticket tool call', [
                'arguments' => $arguments,
            ]);

            // Validate required fields
            $requiredFields = ['short_title', 'description', 'expected_result', 'priority', 'task_type', 'executor', 'sender_name', 'needs_review'];
            foreach ($requiredFields as $field) {
                if (empty($arguments[$field])) {
                    return [
                        'success' => false,
                        'error' => "Обязательное поле '{$field}' не заполнено",
                    ];
                }
            }

            // Extract IDs from "id:name" format
            /** @var string $priorityStr */
            $priorityStr = $arguments['priority'];
            /** @var string $taskTypeStr */
            $taskTypeStr = $arguments['task_type'];
            /** @var string $executorStr */
            $executorStr = $arguments['executor'];

            $priorityId = AddTicketToolDefinition::extractId($priorityStr);
            $taskTypeId = AddTicketToolDefinition::extractId($taskTypeStr);
            $executorId = AddTicketToolDefinition::extractId($executorStr);

            if ($priorityId === 0 || $taskTypeId === 0 || $executorId === 0) {
                return [
                    'success' => false,
                    'error' => 'Не удалось определить ID приоритета, типа задачи или исполнителя',
                ];
            }

            // Collect file links
            /** @var array<int, string> $fileLinks */
            $fileLinks = [];
            for ($i = 1; $i <= 3; $i++) {
                $link = $arguments["file_link_{$i}"] ?? '';
                if (is_string($link) && $link !== '') {
                    $fileLinks[] = $link;
                }
            }

            /** @var string $needsReviewStr */
            $needsReviewStr = $arguments['needs_review'] ?? 'Нет';
            $needsReview = $needsReviewStr === 'Да';

            /** @var string $authorTelegramUsername */
            $authorTelegramUsername = $arguments['sender_name'] ?? '';

            /** @var string $shortTitle */
            $shortTitle = $arguments['short_title'];
            /** @var string $description */
            $description = $arguments['description'];
            /** @var string $expectedResult */
            $expectedResult = $arguments['expected_result'];

            $result = $this->createTicketAction->execute(
                shortTitle: $shortTitle,
                description: $description,
                expectedResult: $expectedResult,
                priorityId: $priorityId,
                taskTypeId: $taskTypeId,
                executorId: $executorId,
                authorTelegramUsername: $authorTelegramUsername,
                fileLinks: $fileLinks,
                needsReview: $needsReview,
            );

            if ($result->hasError()) {
                Log::error('Failed to create ticket via tool', [
                    'error' => $result->errorMessage,
                    'arguments' => $arguments,
                ]);

                return [
                    'success' => false,
                    'error' => $result->errorMessage,
                ];
            }

            Log::info('Successfully created ticket via tool', [
                'ticket_uid' => $result->getTicketUid(),
                'short_title' => $arguments['short_title'],
            ]);

            return [
                'success' => true,
                'message' => '💼 Задача поставлена 🔔 Ответственный уведомлен',
                'ticket_uid' => $result->getTicketUid(),
            ];

        } catch (\Throwable $e) {
            Log::error('Exception in create_ticket tool handler', [
                'exception' => $e->getMessage(),
                'arguments' => $arguments,
            ]);

            return [
                'success' => false,
                'error' => 'Произошла ошибка при создании задачи: '.$e->getMessage(),
            ];
        }
    }
}
