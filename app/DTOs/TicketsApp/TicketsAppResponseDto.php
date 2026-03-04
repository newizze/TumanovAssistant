<?php

declare(strict_types=1);

namespace App\DTOs\TicketsApp;

class TicketsAppResponseDto
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly bool $success,
        public readonly array $data = [],
        public readonly ?string $errorMessage = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function success(array $data): self
    {
        return new self(
            success: true,
            data: $data,
        );
    }

    public static function error(string $errorMessage): self
    {
        return new self(
            success: false,
            errorMessage: $errorMessage,
        );
    }

    public function hasError(): bool
    {
        return ! $this->success;
    }

    public function getTicketUid(): ?string
    {
        $ticket = $this->data['ticket'] ?? null;

        if (is_array($ticket) && isset($ticket['ticket_uid']) && is_string($ticket['ticket_uid'])) {
            return $ticket['ticket_uid'];
        }

        return null;
    }
}
