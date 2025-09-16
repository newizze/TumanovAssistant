<?php

declare(strict_types=1);

namespace App\DTOs\Telegram;

final readonly class TelegramUserDto
{
    public function __construct(
        public int $id,
        public bool $isBot,
        public string $firstName,
        public ?string $lastName = null,
        public ?string $username = null,
        public ?string $languageCode = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            isBot: $data['is_bot'],
            firstName: $data['first_name'],
            lastName: $data['last_name'] ?? null,
            username: $data['username'] ?? null,
            languageCode: $data['language_code'] ?? null,
        );
    }

    public function getFullName(): string
    {
        return trim($this->firstName.' '.($this->lastName ?? ''));
    }
}
