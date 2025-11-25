<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\GoogleSheets\GoogleSheetsReadDto;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExecutorService
{
    private const EXECUTORS_SPREADSHEET_ID = '1PKFf72F2DuyfEXAz2bLwP5nyOdcqsqfH4qJGXB_b26E';

    private const EXECUTORS_RANGE = 'A:L';

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
     * Находит исполнителя по telegram username из списка утвержденных исполнителей
     *
     * @param  array<int, array<string, string>>|null  $executors
     * @return array<string, string>|null
     */
    public function findExecutorByTelegramUsername(?string $username, ?array $executors = null): ?array
    {
        if ($username === null) {
            return null;
        }

        $normalizedUsername = $this->normalizeTelegramUsername($username);

        if ($normalizedUsername === '') {
            return null;
        }

        $approvedExecutors = $executors ?? $this->getApprovedExecutors();

        foreach ($approvedExecutors as $executor) {
            $executorUsername = $this->normalizeTelegramUsername($executor['tg_username'] ?? null);

            if ($executorUsername !== '' && $executorUsername === $normalizedUsername) {
                return $executor;
            }
        }

        return null;
    }

    private function normalizeTelegramUsername(?string $username): string
    {
        if ($username === null) {
            return '';
        }

        $normalized = trim($username);

        if ($normalized === '') {
            return '';
        }

        return ltrim(strtolower($normalized), '@');
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
                if (count($row) >= 12) {
                    $comment = trim($row[6] ?? ''); // Column G - "Комментарий Николая"

                    if ($comment === 'Подтверждаю') {
                        $shortCode = trim($row[3] ?? ''); // Column D - Аббревиатура
                        $telegram = trim($row[5] ?? '');  // Column F - Telegram
                        $position = trim($row[8] ?? '');  // Column I - Должность
                        $firstName = trim($row[9] ?? ''); // Column J - Имя
                        $lastName = trim($row[10] ?? ''); // Column K - Фамилия
                        $middleName = trim($row[11] ?? ''); // Column L - Отчество

                        // Собираем полное имя
                        $fullName = trim("$lastName $firstName $middleName");

                        if ($shortCode && $telegram && $fullName) {
                            $executors[] = [
                                'short_code' => $shortCode,
                                'full_name' => $fullName,
                                'position' => $position,
                                'first_name' => $firstName,
                                'last_name' => $lastName,
                                'middle_name' => $middleName,
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
}
