<?php

declare(strict_types=1);

namespace App\DTOs\Telegram;

final readonly class TelegramInlineKeyboardButtonDto
{
    public function __construct(
        public string $text,
        public ?string $callbackData = null,
        public ?string $url = null,
    ) {}

    public function toArray(): array
    {
        $data = [
            'text' => $this->text,
        ];

        if ($this->callbackData !== null) {
            $data['callback_data'] = $this->callbackData;
        }

        if ($this->url !== null) {
            $data['url'] = $this->url;
        }

        return $data;
    }
}
