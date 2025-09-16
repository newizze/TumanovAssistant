<?php

declare(strict_types=1);

namespace App\DTOs\Telegram;

final readonly class TelegramSendMessageDto
{
    public function __construct(
        public int|string $chatId,
        public string $text,
        public ?string $parseMode = null,
        public ?bool $disableWebPagePreview = null,
        public ?bool $disableNotification = null,
        public ?int $replyToMessageId = null,
        public ?array $replyMarkup = null,
    ) {}

    public function toArray(): array
    {
        $data = [
            'chat_id' => $this->chatId,
            'text' => $this->text,
        ];

        if ($this->parseMode !== null) {
            $data['parse_mode'] = $this->parseMode;
        }

        if ($this->disableWebPagePreview !== null) {
            $data['disable_web_page_preview'] = $this->disableWebPagePreview;
        }

        if ($this->disableNotification !== null) {
            $data['disable_notification'] = $this->disableNotification;
        }

        if ($this->replyToMessageId !== null) {
            $data['reply_to_message_id'] = $this->replyToMessageId;
        }

        if ($this->replyMarkup !== null) {
            $data['reply_markup'] = $this->replyMarkup;
        }

        return $data;
    }
}
