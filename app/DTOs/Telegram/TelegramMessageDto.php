<?php

declare(strict_types=1);

namespace App\DTOs\Telegram;

final readonly class TelegramMessageDto
{
    public function __construct(
        public int $messageId,
        public TelegramUserDto $from,
        public TelegramChatDto $chat,
        public int $date,
        public ?string $text = null,
        public ?TelegramVoiceDto $voice = null,
        public ?array $entities = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            messageId: $data['message_id'],
            from: TelegramUserDto::fromArray($data['from']),
            chat: TelegramChatDto::fromArray($data['chat']),
            date: $data['date'],
            text: $data['text'] ?? null,
            voice: isset($data['voice']) ? TelegramVoiceDto::fromArray($data['voice']) : null,
            entities: $data['entities'] ?? null,
        );
    }

    public function hasText(): bool
    {
        return $this->text !== null && $this->text !== '';
    }

    public function hasVoice(): bool
    {
        return $this->voice !== null;
    }

    public function isPrivateChat(): bool
    {
        return $this->chat->type === 'private';
    }
}