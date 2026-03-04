<?php

declare(strict_types=1);

namespace App\Tools;

use InvalidArgumentException;

class AddTicketToolDefinition
{
    /**
     * @param  array<int, array<string, mixed>>  $executors  Executors from tickets-app API
     * @param  array<int, array<string, mixed>>  $priorities  Priorities from tickets-app API
     * @param  array<int, array<string, mixed>>  $taskTypes  Task types from tickets-app API
     * @return array<string, mixed>
     */
    public static function getDefinition(
        string $forcedSender,
        array $executors = [],
        array $priorities = [],
        array $taskTypes = [],
    ): array {
        if (empty($forcedSender)) {
            throw new InvalidArgumentException('Sender identifier must be provided to tool definition');
        }

        // Build executor enum: "id:code" format so AI picks by name, we extract id
        $executorEnum = [];
        foreach ($executors as $executor) {
            /** @var int|string $id */
            $id = $executor['id'] ?? 0;
            /** @var string $code */
            $code = $executor['code'] ?? '';
            $executorEnum[] = $id.':'.$code;
        }

        // Build priority enum from DB
        $priorityEnum = [];
        foreach ($priorities as $priority) {
            /** @var int|string $id */
            $id = $priority['id'] ?? 0;
            /** @var string $name */
            $name = $priority['name'] ?? '';
            $priorityEnum[] = $id.':'.$name;
        }

        // Build task type enum from DB
        $taskTypeEnum = [];
        foreach ($taskTypes as $taskType) {
            /** @var int|string $id */
            $id = $taskType['id'] ?? 0;
            /** @var string $name */
            $name = $taskType['name'] ?? '';
            $taskTypeEnum[] = $id.':'.$name;
        }

        $senderProperty = [
            'type' => 'string',
            'description' => 'Telegram username отправителя задачи (определяется автоматически системой)',
            'enum' => [$forcedSender],
            'const' => $forcedSender,
        ];

        return [
            'type' => 'function',
            'name' => 'create_ticket',
            'description' => 'Создаёт новый тикет (задачу) в системе управления задачами',
            'parameters' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'short_title' => [
                        'type' => 'string',
                        'description' => 'Краткое название задачи',
                    ],
                    'description' => [
                        'type' => 'string',
                        'description' => 'Подробное описание задачи',
                    ],
                    'expected_result' => [
                        'type' => 'string',
                        'description' => 'Ожидаемый конечный результат',
                    ],
                    'priority' => [
                        'type' => 'string',
                        'enum' => $priorityEnum !== [] ? $priorityEnum : ['Высокий', 'Средний', 'Низкий'],
                        'description' => 'Приоритет задачи в формате "id:название"',
                    ],
                    'task_type' => [
                        'type' => 'string',
                        'enum' => $taskTypeEnum !== [] ? $taskTypeEnum : ['Разработка'],
                        'description' => 'Тип задачи в формате "id:название"',
                    ],
                    'executor' => [
                        'type' => 'string',
                        'enum' => $executorEnum !== [] ? $executorEnum : ['unknown'],
                        'description' => 'Исполнитель задачи в формате "id:код". Выбери подходящего из списка',
                    ],
                    'sender_name' => $senderProperty,
                    'file_link_1' => [
                        'type' => 'string',
                        'description' => 'Ссылка на первый файл от отправителя (опционально)',
                    ],
                    'file_link_2' => [
                        'type' => 'string',
                        'description' => 'Ссылка на второй файл от отправителя (опционально)',
                    ],
                    'file_link_3' => [
                        'type' => 'string',
                        'description' => 'Ссылка на третий файл от отправителя (опционально)',
                    ],
                    'needs_review' => [
                        'type' => 'string',
                        'enum' => ['Да', 'Нет'],
                        'description' => 'Требуется ли проверка задачи постановщиком перед приёмкой. По умолчанию "Нет" (автоприёмка).',
                        'default' => 'Нет',
                    ],
                ],
                'required' => ['short_title', 'description', 'expected_result', 'priority', 'task_type', 'executor', 'sender_name', 'needs_review'],
            ],
        ];
    }

    /**
     * Extracts numeric ID from "id:name" format string.
     */
    public static function extractId(string $value): int
    {
        $parts = explode(':', $value, 2);

        return (int) $parts[0];
    }
}
