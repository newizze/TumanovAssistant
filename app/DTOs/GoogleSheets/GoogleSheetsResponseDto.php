<?php

declare(strict_types=1);

namespace App\DTOs\GoogleSheets;

class GoogleSheetsResponseDto
{
    public function __construct(
        public readonly bool $isSuccessful,
        public readonly array $data = [],
        public readonly ?string $errorMessage = null,
        public readonly ?string $spreadsheetId = null,
        public readonly ?int $updatedRows = null,
        public readonly ?int $updatedColumns = null,
        public readonly ?int $updatedCells = null
    ) {}

    public static function success(array $data): self
    {
        return new self(
            isSuccessful: true,
            data: $data,
            spreadsheetId: $data['spreadsheetId'] ?? null,
            updatedRows: $data['updatedRows'] ?? null,
            updatedColumns: $data['updatedColumns'] ?? null,
            updatedCells: $data['updatedCells'] ?? null
        );
    }

    public static function error(string $errorMessage): self
    {
        return new self(
            isSuccessful: false,
            errorMessage: $errorMessage
        );
    }

    public function hasError(): bool
    {
        return !$this->isSuccessful;
    }
}