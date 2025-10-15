<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Telegram\TelegramMessageDto;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Сервис для буферизации сообщений медиа-группы (альбома).
 * Когда пользователь отправляет несколько файлов, Telegram присылает их как отдельные сообщения
 * с одинаковым media_group_id. Этот сервис собирает их вместе.
 */
class MediaGroupBufferService
{
    /**
     * Время ожидания завершения медиа-группы в секундах
     */
    private const BUFFER_TIMEOUT = 2;

    /**
     * Префикс для ключей кэша
     */
    private const CACHE_PREFIX = 'media_group:';

    /**
     * Добавляет сообщение в буфер медиа-группы.
     * Возвращает массив всех сообщений группы, если группа завершена, иначе null.
     *
     * @return array<TelegramMessageDto>|null
     */
    public function addMessage(TelegramMessageDto $message): ?array
    {
        if (! $message->isPartOfMediaGroup()) {
            // Не часть группы - возвращаем сразу
            return [$message];
        }

        $mediaGroupId = $message->mediaGroupId;
        $cacheKey = self::CACHE_PREFIX.$mediaGroupId;

        Log::info('Adding message to media group buffer', [
            'media_group_id' => $mediaGroupId,
            'message_id' => $message->messageId,
            'has_photo' => $message->hasPhoto(),
            'has_document' => $message->hasDocument(),
        ]);

        // Получаем текущий буфер
        $buffer = Cache::get($cacheKey, [
            'messages' => [],
            'first_received_at' => now()->timestamp,
            'last_received_at' => now()->timestamp,
        ]);

        // Добавляем сообщение в буфер
        $buffer['messages'][] = $message;
        $buffer['last_received_at'] = now()->timestamp;

        // Сохраняем обновленный буфер
        Cache::put($cacheKey, $buffer, now()->addSeconds(self::BUFFER_TIMEOUT + 5));

        Log::info('Media group buffer updated', [
            'media_group_id' => $mediaGroupId,
            'messages_count' => count($buffer['messages']),
            'time_since_first' => now()->timestamp - $buffer['first_received_at'],
        ]);

        // Проверяем, нужно ли обработать группу
        if ($this->shouldProcessMediaGroup($buffer)) {
            Log::info('Media group ready for processing', [
                'media_group_id' => $mediaGroupId,
                'messages_count' => count($buffer['messages']),
            ]);

            // Удаляем буфер и возвращаем сообщения
            Cache::forget($cacheKey);

            return $buffer['messages'];
        }

        // Группа еще не завершена
        return null;
    }

    /**
     * Проверяет, нужно ли обработать медиа-группу.
     * Группа готова, если прошло достаточно времени с последнего сообщения
     * или достигнут лимит файлов (3).
     */
    private function shouldProcessMediaGroup(array $buffer): bool
    {
        $messagesCount = count($buffer['messages']);
        $timeSinceLastMessage = now()->timestamp - $buffer['last_received_at'];

        // Достигнут лимит файлов
        if ($messagesCount >= 3) {
            return true;
        }

        // Прошло достаточно времени с последнего сообщения
        if ($timeSinceLastMessage >= self::BUFFER_TIMEOUT) {
            return true;
        }

        return false;
    }

    /**
     * Объединяет несколько сообщений медиа-группы в одно с несколькими файлами.
     *
     * @param  array<TelegramMessageDto>  $messages
     */
    public function mergeMessages(array $messages): TelegramMessageDto
    {
        if (count($messages) === 1) {
            return $messages[0];
        }

        // Берем первое сообщение как базу
        $firstMessage = $messages[0];

        Log::info('Merging media group messages', [
            'media_group_id' => $firstMessage->mediaGroupId,
            'messages_count' => count($messages),
        ]);

        // Это виртуальное объединенное сообщение - просто возвращаем первое
        // Файлы будут собраны через getAllFilesFromMessages
        return $firstMessage;
    }

    /**
     * Собирает все file_id из массива сообщений медиа-группы.
     *
     * @param  array<TelegramMessageDto>  $messages
     * @return array<string>
     */
    public function getAllFilesFromMessages(array $messages): array
    {
        $allFileIds = [];

        foreach ($messages as $message) {
            $fileIds = $message->getAllFileIds();
            $allFileIds = array_merge($allFileIds, $fileIds);
        }

        // Ограничиваем максимум 3 файла
        $allFileIds = array_slice($allFileIds, 0, 3);

        Log::info('Collected files from media group', [
            'messages_count' => count($messages),
            'files_count' => count($allFileIds),
        ]);

        return $allFileIds;
    }

    /**
     * Получает текст из сообщений медиа-группы (обычно только первое сообщение имеет caption).
     *
     * @param  array<TelegramMessageDto>  $messages
     */
    public function getTextFromMessages(array $messages): ?string
    {
        foreach ($messages as $message) {
            $text = $message->getMessageText();
            if ($text !== null && $text !== '') {
                return $text;
            }
        }

        return null;
    }
}
