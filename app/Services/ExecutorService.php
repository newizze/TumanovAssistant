<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\GoogleSheets\GoogleSheetsReadDto;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExecutorService
{
    private const EXECUTORS_SPREADSHEET_ID = '1PKFf72F2DuyfEXAz2bLwP5nyOdcqsqfH4qJGXB_b26E';

    private const EXECUTORS_RANGE = 'A:H';

    private const CACHE_KEY = 'approved_executors';

    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private readonly GoogleSheetsService $googleSheetsService
    ) {}

    /**
     * @return array<int, array<string, string>>
     */
    public function getApprovedExecutors(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn (): array => $this->fetchExecutorsFromSheet());
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function refreshExecutorsCache(): array
    {
        Cache::forget(self::CACHE_KEY);

        return $this->getApprovedExecutors();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function fetchExecutorsFromSheet(): array
    {
        try {
            $readDto = new GoogleSheetsReadDto(
                spreadsheetId: self::EXECUTORS_SPREADSHEET_ID,
                range: self::EXECUTORS_RANGE
            );

            $response = $this->googleSheetsService->readValues($readDto);

            if ($response->hasError()) {
                Log::error('Failed to fetch executors from Google Sheets', [
                    'error' => $response->errorMessage,
                ]);

                throw new \Exception("Failed to fetch executors from Google Sheets: {$response->errorMessage}");
            }

            $values = $response->data['values'] ?? [];
            $executors = [];
            // Skip header row (index 0)
            $counter = count($values);

            // Skip header row (index 0)
            for ($i = 1; $i < $counter; $i++) {
                $row = $values[$i];

                // Check if row has enough columns and status is "Подтверждаю"
                if (count($row) >= 7) {
                    $comment = trim($row[6] ?? ''); // Column G - "Комментарий Николая"

                    if ($comment === 'Подтверждаю') {
                        $name = $this->extractFullName($row[3] ?? '');
                        $shortCode = $row[3] ?? '';
                        $telegram = $row[5] ?? '';

                        if ($name && $shortCode && $telegram) {
                            $executors[] = [
                                'name' => $name,
                                'short_code' => $shortCode,
                                'tg_username' => $telegram,
                            ];
                        }
                    }
                }
            }

            Log::info('Successfully fetched executors from Google Sheets', [
                'count' => count($executors),
            ]);

            return $executors;

        } catch (\Throwable $e) {
            Log::error('Exception occurred while fetching executors', [
                'exception' => $e->getMessage(),
            ]);

            throw new \Exception("Failed to fetch executors from Google Sheets: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    private function extractFullName(string $shortCode): string
    {
        // Map short codes to full names based on known data
        $nameMapping = [
            'РОП ДА' => 'Абрамов Дмитрий Юрьевич',
            'РОМ ИК' => 'Коротков И. В.',
            'АС ГД' => 'Голубева Александра Алексеевна',
            'ФД ДТ' => 'Туктарова Диана Ильшатовна',
            'ОД ДМ' => 'Матюшин Денис',
            'ИТ ВУ' => 'Владислав Умнов IT',
            'ФК ЭБ' => 'Элеонора Бабои',
            'М АО' => 'Андрей Орлов',
        ];

        return $nameMapping[$shortCode] ?? $shortCode;
    }

}
