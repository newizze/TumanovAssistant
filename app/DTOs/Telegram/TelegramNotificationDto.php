<?php

declare(strict_types=1);

namespace App\DTOs\Telegram;

/**
 * DTO для хранения информации о временном уведомлении
 */
readonly class TelegramNotificationDto
{
    public function __construct(
        public int|string $chatId,
        public int $messageId,
        public string $text
    ) {}

    public static function create(int|string $chatId, int $messageId, string $text): self
    {
        return new self($chatId, $messageId, $text);
    }
}
