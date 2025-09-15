<?php

declare(strict_types=1);

namespace App\Tools;

use App\Actions\AddRowToGoogleSheetsAction;
use App\DTOs\GoogleSheets\GoogleSheetsResponseDto;
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
            $requiredFields = ['task_title', 'task_description', 'priority', 'category'];
            foreach ($requiredFields as $field) {
                if (empty($arguments[$field])) {
                    return [
                        'success' => false,
                        'error' => "Обязательное поле '{$field}' не заполнено"
                    ];
                }
            }

            // Получаем настройки из конфигурации
            $spreadsheetId = config('project.google_sheets.default_spreadsheet_id');
            $range = config('project.google_sheets.default_range');

            if (empty($spreadsheetId)) {
                return [
                    'success' => false,
                    'error' => 'Не настроен ID таблицы Google Sheets'
                ];
            }

            // Подготавливаем данные для строки
            $rowData = [
                $arguments['task_title'],
                $arguments['task_description'],
                $arguments['priority'],
                $arguments['category'],
                $arguments['responsible_person'] ?? '',
                $arguments['due_date'] ?? '',
                $arguments['tags'] ?? '',
                date('Y-m-d H:i:s'), // Дата создания
                'Новая' // Статус
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
                    'error' => $result->errorMessage
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
                'spreadsheet_id' => $spreadsheetId
            ];

        } catch (\Throwable $e) {
            Log::error('Exception in add_row_to_sheets tool handler', [
                'exception' => $e->getMessage(),
                'arguments' => $arguments,
            ]);

            return [
                'success' => false,
                'error' => 'Произошла ошибка при добавлении задачи: ' . $e->getMessage()
            ];
        }
    }
}