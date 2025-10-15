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

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function handle(array $arguments): array
    {
        try {
            Log::info('Handling add_row_to_sheets tool call', [
                'arguments' => $arguments,
            ]);

            // Валидация обязательных параметров
            $requiredFields = ['task_title', 'task_description', 'expected_result', 'priority', 'task_type', 'executor', 'sender_name', 'requires_verification'];
            foreach ($requiredFields as $field) {
                if (empty($arguments[$field])) {
                    return [
                        'success' => false,
                        'error' => "Обязательное поле '{$field}' не заполнено",
                    ];
                }
            }

            // Получаем настройки из конфигурации
            /** @var string|null $spreadsheetId */
            $spreadsheetId = config('project.google_sheets.default_spreadsheet_id');
            /** @var string $range */
            $range = config('project.google_sheets.default_range', 'Sheet1!A:Z');

            if (empty($spreadsheetId)) {
                return [
                    'success' => false,
                    'error' => 'Не настроен ID таблицы Google Sheets',
                ];
            }

            // Генерируем уникальный ID задачи
            $taskId = strtoupper(substr(md5(uniqid()), 0, 8));

            // Находим исполнителя по short_code
            /** @var array<int, array<string, string>> $executors */
            $executors = config('project.executors', []);
            /** @var array<string, string>|null $executorInfo */
            $executorInfo = collect($executors)->firstWhere('short_code', $arguments['executor'] ?? '');

            // Подготавливаем данные для строки согласно структуре таблицы
            // Структура: A-AD (30 колонок)
            $rowData = [
                $taskId, // A: ID
                date('d.m.Y H:i:s'), // B: Дата создания
                $arguments['sender_name'] ?? '', // C: Отправитель ФИО
                $arguments['executor'] ?? '', // D: Исполнитель
                $arguments['task_type'] ?? '', // E: Тип задачи1
                $arguments['task_title'], // F: Краткое название
                $arguments['task_description'], // G: Подробное описание
                $arguments['expected_result'] ?? '', // H: Ожидаемый конечный результат
                $arguments['priority'], // I: Приоритет
                $arguments['file_link_1'] ?? '', // J: Ссылка на файл отправителя
                $arguments['file_link_2'] ?? '', // K: Ссылка на файл отправителя2
                $arguments['file_link_3'] ?? '', // L: Ссылка на файл отправителя3
                '', // M: План исполнителя
                'Неразобранная', // N: Статус
                '', // O: Дата факт готово
                '', // P: Приложение от исполнителя
                '', // Q: Комментарий исполнителя
                '', // R: Дата первого выхода из Неразобранная
                '', // S: Почта сотрудника
                '', // T: Приоритет.
                '', // U: Тг сотрудника
                '', // V: Ссылка на задачу
                '', // W: Тех
                '', // X: Тип задачи
                '', // Y: Диагноз (корень проблемы)
                '', // Z: Что сделано, чтобы не повторилось
                '', // AA: Чек отправки готового/отмененного
                '', // AB: Почта исполнителя
                '', // AC: Лог
                $arguments['requires_verification'] ?? 'Нет', // AD: Требуется ли проверка
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

            $taskTitle = is_string($arguments['task_title']) ? $arguments['task_title'] : '';

            return [
                'success' => true,
                'message' => 'Задача обработана',
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
