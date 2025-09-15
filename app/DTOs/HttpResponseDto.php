<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class HttpResponseDto
{
    public function __construct(
        public int $statusCode,
        public string $body,
        public array $headers = [],
        public bool $isSuccessful = false,
        public ?string $errorMessage = null,
    ) {
    }

    public function isOk(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }

    public function getJsonData(): ?array
    {
        $decoded = json_decode($this->body, true);
        
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    public function hasError(): bool
    {
        return $this->errorMessage !== null || !$this->isOk();
    }
}
