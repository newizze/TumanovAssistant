<?php

declare(strict_types=1);

namespace App\DTOs;

use InvalidArgumentException;

final readonly class HttpRequestDto
{
    public function __construct(
        public string $method,
        public string $url,
        public array $data = [],
        public array $headers = [],
        public ?string $bearerToken = null,
        public int $timeout = 30,
        public int $connectTimeout = 10,
    ) {
        $this->validateMethod();
        $this->validateUrl();
    }

    private function validateMethod(): void
    {
        $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

        if (! in_array(strtoupper($this->method), $allowedMethods, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid HTTP method: %s. Allowed methods: %s',
                    $this->method,
                    implode(', ', $allowedMethods)
                )
            );
        }
    }

    private function validateUrl(): void
    {
        if (! filter_var($this->url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("Invalid URL: {$this->url}");
        }
    }

    public function getMethod(): string
    {
        return strtoupper($this->method);
    }

    public function getAllHeaders(): array
    {
        $headers = $this->headers;

        if ($this->bearerToken !== null) {
            $headers['Authorization'] = "Bearer {$this->bearerToken}";
        }

        return $headers;
    }

    public function hasAuthentication(): bool
    {
        return $this->bearerToken !== null;
    }
}
