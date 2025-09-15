<?php

declare(strict_types=1);

namespace App\Tools;

class AddRowToSheetsToolDefinition
{
    public static function getDefinition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'add_row_to_sheets',
                'description' => 'Добавляет новую строку в указанную Google Sheets таблицу с данными задачи',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'task_title' => [
                            'type' => 'string',
                            'description' => 'Название задачи (краткое и понятное)'
                        ],
                        'task_description' => [
                            'type' => 'string',
                            'description' => 'Подробное описание задачи'
                        ],
                        'priority' => [
                            'type' => 'string',
                            'enum' => ['Высокий', 'Средний', 'Низкий'],
                            'description' => 'Приоритет задачи'
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Категория задачи (IT, Продажи, Маркетинг, Управление и т.д.)'
                        ],
                        'responsible_person' => [
                            'type' => 'string',
                            'description' => 'Ответственный за выполнение задачи'
                        ],
                        'due_date' => [
                            'type' => 'string',
                            'description' => 'Срок выполнения в формате YYYY-MM-DD (опционально)'
                        ],
                        'tags' => [
                            'type' => 'string',
                            'description' => 'Теги через запятую (опционально)'
                        ]
                    ],
                    'required' => ['task_title', 'task_description', 'priority', 'category']
                ]
            ]
        ];
    }
}