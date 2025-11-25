<?php

declare(strict_types=1);

namespace App\Tools;

use InvalidArgumentException;

class AddRowToSheetsToolDefinition
{
    /**
     * @return array<string, mixed>
     */
    public static function getDefinition(?string $forcedSender = null): array
    {
        // Получаем список исполнителей из конфигурации
        /** @var array<int, array<string, string>> $executors */
        $executors = config('project.executors', []);
        $executorCodes = array_column($executors, 'short_code');

        // ВАЖНО: sender_name всегда должен быть зафиксирован системой, AI не должен его выбирать
        if (empty($forcedSender)) {
            throw new InvalidArgumentException('Sender identifier must be provided to tool definition');
        }

        $senderProperty = [
            'type' => 'string',
            'description' => 'Код отправителя задачи (определяется автоматически системой)',
            'enum' => [$forcedSender],
            'const' => $forcedSender, // JSON Schema: единственно возможное значение
        ];

        return [
            'type' => 'function',
            'name' => 'add_row_to_sheets',
            'description' => 'Добавляет новую строку в указанную Google Sheets таблицу с данными задачи',
            'parameters' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'task_title' => [
                        'type' => 'string',
                        'description' => 'Краткое название задачи',
                    ],
                    'task_description' => [
                        'type' => 'string',
                        'description' => 'Подробное описание задачи',
                    ],
                    'expected_result' => [
                        'type' => 'string',
                        'description' => 'Ожидаемый конечный результат',
                    ],
                    'priority' => [
                        'type' => 'string',
                        'enum' => ['Высокий', 'Средний', 'Низкий'],
                        'description' => 'Приоритет задачи',
                    ],
                    'task_type' => [
                        'type' => 'string',
                        'description' => 'Тип задачи (например: Разработка, Настройка, Исправление, Анализ и т.д.)',
                    ],
                    'executor' => [
                        'type' => 'string',
                        'enum' => $executorCodes,
                        'description' => 'Код Исполнителя задачи (выбери подходящего из списка), например ИТ ВУ',
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
                    'requires_verification' => [
                        'type' => 'string',
                        'enum' => ['Да', 'Нет'],
                        'description' => 'Требуется ли проверка задачи постановщиком перед приемкой. По умолчанию "Нет" (автоприемка). "Да" - только если явно указано требование проверки',
                        'default' => 'Нет',
                    ],
                ],
                'required' => ['task_title', 'task_description', 'expected_result', 'priority', 'task_type', 'executor', 'sender_name', 'requires_verification'],
            ],
        ];
    }
}
