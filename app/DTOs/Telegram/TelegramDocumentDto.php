<?php

declare(strict_types=1);

namespace App\DTOs\Telegram;

final readonly class TelegramDocumentDto
{
    public function __construct(
        public string $fileId,
        public string $fileUniqueId,
        public ?string $fileName = null,
        public ?string $mimeType = null,
        public ?int $fileSize = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            fileId: $data['file_id'],
            fileUniqueId: $data['file_unique_id'],
            fileName: $data['file_name'] ?? null,
            mimeType: $data['mime_type'] ?? null,
            fileSize: $data['file_size'] ?? null,
        );
    }
}
