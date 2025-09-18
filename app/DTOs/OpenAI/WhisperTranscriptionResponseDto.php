<?php

declare(strict_types=1);

namespace App\DTOs\OpenAI;

final readonly class WhisperTranscriptionResponseDto
{
    public function __construct(
        public string $text,
        public ?string $language = null,
        public ?float $duration = null,
        public ?array $segments = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            text: $data['text'],
            language: $data['language'] ?? null,
            duration: $data['duration'] ?? null,
            segments: $data['segments'] ?? null,
        );
    }

    public function hasText(): bool
    {
        return ! in_array(trim($this->text), ['', '0'], true);
    }
}
