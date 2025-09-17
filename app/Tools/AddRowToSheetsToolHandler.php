<?php

declare(strict_types=1);

namespace App\Tools;

use App\Actions\AddRowToGoogleSheetsAction;
use Illuminate\Support\Facades\Log;

class AddRowToSheetsToolHandler
{
    public function __construct(
        private readonly AddRowToGoogleSheetsAction $addRowAction
    ) {}

    public function handle(array $arguments): array
    {
        try {
            Log::info('Handling add_row_to_sheets tool call', [
                'arguments' => $arguments,
            ]);

            // Валидация обязательных параметров
            $requiredFields = ['task_title', 'task_description', 'priority', 'executor'];
            foreach ($requiredFields as $field) {
                if (empty($arguments[$field])) {
                    return [
                        'success' => false,
                        'error' => "Обязательное поле '{$field}' не заполнено",
                    ];
                }
            }

            // Получаем настройки из конфигурации
            $spreadsheetId = config('project.google_sheets.default_spreadsheet_id');
            $range = config('project.google_sheets.default_range');

            if (empty($spreadsheetId)) {
                return [
                    'success' => false,
                    'error' => 'Не настроен ID таблицы Google Sheets',
                ];
            }

            // Генерируем уникальный ID задачи
            $taskId = strtoupper(substr(md5(uniqid()), 0, 8));

            // Находим исполнителя по short_code
            $executors = config('project.executors', []);
            $executorInfo = collect($executors)->firstWhere('short_code', $arguments['executor'] ?? '');

            // Подготавливаем данные для строки согласно структуре таблицы
            $rowData = [
                $taskId, // ID
                date('d.m.Y H:i:s'), // Дата создания
                $arguments['sender_name'] ?? '', // Отправитель ФИО
                $arguments['executor'] ?? '', // Исполнитель (short_code)
                $arguments['task_type'] ?? '', // Тип задачи
                $arguments['task_title'], // Краткое название
                $arguments['task_description'], // Подробное описание
                $arguments['expected_result'] ?? '', // Ожидаемый конечный результат
                $arguments['priority'], // Приоритет
                $arguments['file_link_1'] ?? '', // Ссылка на файл отправителя 1
                $arguments['file_link_2'] ?? '', // Ссылка на файл отправителя 2
                $arguments['file_link_3'] ?? '', // Ссылка на файл отправителя 3
                '', // План исполнителя
                'Неразобранная', // Статус
                '', // Дата факт готово
                '', // Приложение от исполнителя
                '', // Комментарий исполнителя
                '', // Дата первого выхода из Неразобранная
                $executorInfo['tg_username'] ?? '', // Почта сотрудника (используем tg_username)
                $arguments['priority'], // Приоритет.
                $executorInfo['tg_username'] ?? '', // Тг сотрудника
            ];

            // Добавляем строку в таблицу
            $result = $this->addRowAction->execute(
                spreadsheetId: $spreadsheetId,
                range: $range,
                values: $rowData
            );

            if ($result->hasError()) {
                Log::error('Failed to add row via tool', [
                    'error' => $result->errorMessage,
                    'arguments' => $arguments,
                ]);

                return [
                    'success' => false,
                    'error' => $result->errorMessage,
                ];
            }

            Log::info('Successfully added row via tool', [
                'task_title' => $arguments['task_title'],
                'updated_cells' => $result->updatedCells,
            ]);

            return [
                'success' => true,
                'message' => "Задача '{$arguments['task_title']}' успешно добавлена в таблицу",
                'updated_cells' => $result->updatedCells,
                'spreadsheet_id' => $spreadsheetId,
            ];

        } catch (\Throwable $e) {
            Log::error('Exception in add_row_to_sheets tool handler', [
                'exception' => $e->getMessage(),
                'arguments' => $arguments,
            ]);

            return [
                'success' => false,
                'error' => 'Произошла ошибка при добавлении задачи: '.$e->getMessage(),
            ];
        }
    }
}
