<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Telegram\TelegramFileDto;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TelegramFileService
{
    public function __construct(
        private readonly TelegramService $telegramService
    ) {}

    /**
     * Скачивает файл из Telegram и сохраняет в public/telegram_files
     *
     * @param  string  $fileId  ID файла в Telegram
     * @return string|null Публичная ссылка на файл или null при ошибке
     */
    public function downloadAndSaveFile(string $fileId): ?string
    {
        try {
            Log::info('Starting file download from Telegram', [
                'file_id' => $fileId,
            ]);

            // Получаем информацию о файле
            $fileInfo = $this->telegramService->getFile($fileId);

            if (! $fileInfo instanceof \App\DTOs\Telegram\TelegramFileDto) {
                Log::error('Failed to get file info from Telegram', [
                    'file_id' => $fileId,
                ]);

                return null;
            }

            // Скачиваем файл
            $fileContent = $this->telegramService->downloadFile($fileInfo);

            if (! $fileContent) {
                Log::error('Failed to download file content', [
                    'file_id' => $fileId,
                ]);

                return null;
            }

            // Определяем имя и расширение файла
            $fileName = $this->generateFileName($fileInfo);
            $relativePath = 'telegram_files/'.$fileName;

            // Создаем директорию если не существует
            $this->ensureDirectoryExists('telegram_files');

            // Сохраняем файл в public/telegram_files
            $saved = Storage::disk('public')->put($relativePath, $fileContent);

            if (! $saved) {
                Log::error('Failed to save file to public storage', [
                    'file_id' => $fileId,
                    'path' => $relativePath,
                ]);

                return null;
            }

            // Генерируем публичную ссылку
            $publicUrl = asset('storage/'.$relativePath);

            Log::info('File downloaded and saved successfully', [
                'file_id' => $fileId,
                'public_url' => $publicUrl,
                'file_size' => strlen($fileContent),
            ]);

            return $publicUrl;

        } catch (\Throwable $e) {
            Log::error('Exception during file download and save', [
                'file_id' => $fileId,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Обрабатывает массив файлов из сообщения Telegram
     *
     * @param  array  $files  Массив file_id из сообщения
     * @return array Массив публичных ссылок на файлы
     */
    public function processMessageFiles(array $files): array
    {
        $downloadedFiles = [];

        foreach ($files as $fileId) {
            if (empty($fileId)) {
                continue;
            }

            $publicUrl = $this->downloadAndSaveFile($fileId);

            if ($publicUrl) {
                $downloadedFiles[] = $publicUrl;
            }
        }

        Log::info('Processed message files', [
            'total_files' => count($files),
            'downloaded_files' => count($downloadedFiles),
        ]);

        return $downloadedFiles;
    }

    private function generateFileName(TelegramFileDto $fileDto): string
    {
        // Извлекаем расширение из пути файла
        $extension = '';
        if ($fileDto->filePath) {
            $extension = pathinfo($fileDto->filePath, PATHINFO_EXTENSION);
        }

        // Если расширения нет, используем общее
        if (empty($extension)) {
            $extension = 'file';
        }

        // Генерируем уникальное имя файла
        $timestamp = now()->format('Y-m-d_H-i-s');
        $uniqueId = Str::substr($fileDto->fileUniqueId, 0, 8);

        return "{$timestamp}_{$uniqueId}.{$extension}";
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (! Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);

            Log::info('Created directory for telegram files', [
                'directory' => $directory,
            ]);
        }
    }
}
