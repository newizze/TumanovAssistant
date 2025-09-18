<?php

declare(strict_types=1);

namespace App\Tools;

class AddRowToSheetsToolDefinition
{
    public static function getDefinition(): array
    {
        // Получаем список исполнителей из конфигурации
        $executors = config('project.executors', []);
        $executorCodes = array_column($executors, 'short_code');

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
                    'sender_name' => [
                        'type' => 'string',
                        'description' => 'Код Отправителя задачи (выбери подходящего из списка), например ГД НТ',
                    ],
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
                ],
                'required' => ['task_title', 'task_description', 'expected_result', 'priority', 'task_type', 'executor', 'sender_name'],
            ],
        ];
    }
}
