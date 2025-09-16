<?php

declare(strict_types=1);

namespace App\DTOs\Telegram;

final readonly class TelegramWebhookDto
{
    public function __construct(
        public int $updateId,
        public ?TelegramMessageDto $message = null,
        public ?TelegramCallbackQueryDto $callbackQuery = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            updateId: $data['update_id'],
            message: isset($data['message']) ? TelegramMessageDto::fromArray($data['message']) : null,
            callbackQuery: isset($data['callback_query']) ? TelegramCallbackQueryDto::fromArray($data['callback_query']) : null,
        );
    }

    public function hasMessage(): bool
    {
        return $this->message !== null;
    }

    public function hasCallbackQuery(): bool
    {
        return $this->callbackQuery !== null;
    }
}
