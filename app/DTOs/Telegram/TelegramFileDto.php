<?php

declare(strict_types=1);

namespace App\DTOs\Telegram;

final readonly class TelegramFileDto
{
    public function __construct(
        public string $fileId,
        public string $fileUniqueId,
        public ?int $fileSize = null,
        public ?string $filePath = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            fileId: $data['file_id'],
            fileUniqueId: $data['file_unique_id'],
            fileSize: $data['file_size'] ?? null,
            filePath: $data['file_path'] ?? null,
        );
    }

    public function getFileUrl(string $botToken): ?string
    {
        if ($this->filePath === null) {
            return null;
        }

        return "https://api.telegram.org/file/bot{$botToken}/{$this->filePath}";
    }
}