<?php

declare(strict_types=1);

namespace App\DTOs\Telegram;

final readonly class TelegramCallbackQueryDto
{
    public function __construct(
        public string $id,
        public TelegramUserDto $from,
        public ?TelegramMessageDto $message = null,
        public ?string $data = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            from: TelegramUserDto::fromArray($data['from']),
            message: isset($data['message']) ? TelegramMessageDto::fromArray($data['message']) : null,
            data: $data['data'] ?? null,
        );
    }
}