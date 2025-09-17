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
        public ?array $photo = null,
        public ?TelegramDocumentDto $document = null,
        public ?string $caption = null,
    ) {}

    public static function fromArray(array $data): self
    {
        // Обрабатываем массив фото (разные размеры)
        $photo = null;
        if (isset($data['photo']) && is_array($data['photo'])) {
            $photo = array_map(
                fn ($photoData) => TelegramPhotoSizeDto::fromArray($photoData),
                $data['photo']
            );
        }

        return new self(
            messageId: $data['message_id'],
            from: TelegramUserDto::fromArray($data['from']),
            chat: TelegramChatDto::fromArray($data['chat']),
            date: $data['date'],
            text: $data['text'] ?? null,
            voice: isset($data['voice']) ? TelegramVoiceDto::fromArray($data['voice']) : null,
            entities: $data['entities'] ?? null,
            photo: $photo,
            document: isset($data['document']) ? TelegramDocumentDto::fromArray($data['document']) : null,
            caption: $data['caption'] ?? null,
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

    public function hasPhoto(): bool
    {
        return $this->photo !== null && count($this->photo) > 0;
    }

    public function hasDocument(): bool
    {
        return $this->document !== null;
    }

    public function hasCaption(): bool
    {
        return $this->caption !== null && $this->caption !== '';
    }

    public function hasFiles(): bool
    {
        return $this->hasPhoto() || $this->hasDocument();
    }

    /**
     * Получает file_id самого большого фото (последний в массиве)
     */
    public function getLargestPhotoFileId(): ?string
    {
        if (! $this->hasPhoto()) {
            return null;
        }

        $lastPhoto = $this->photo[array_key_last($this->photo)];

        return $lastPhoto->fileId;
    }

    public function getDocumentFileId(): ?string
    {
        return $this->document?->fileId;
    }

    /**
     * Получает все file_id из сообщения (фото и документы)
     */
    public function getAllFileIds(): array
    {
        $fileIds = [];

        if ($this->hasPhoto()) {
            $fileIds[] = $this->getLargestPhotoFileId();
        }

        if ($this->hasDocument()) {
            $fileIds[] = $this->getDocumentFileId();
        }

        return array_filter($fileIds);
    }

    public function isPrivateChat(): bool
    {
        return $this->chat->type === 'private';
    }

    /**
     * Получает текст сообщения (приоритет: text, потом caption)
     */
    public function getMessageText(): ?string
    {
        return $this->text ?? $this->caption;
    }
}
