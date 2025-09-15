<?php

declare(strict_types=1);

namespace App\DTOs\GoogleSheets;

class GoogleSheetsAddRowDto
{
    public function __construct(
        public readonly string $spreadsheetId,
        public readonly string $range,
        public readonly array $values,
        public readonly string $majorDimension = 'ROWS',
        public readonly string $valueInputOption = 'USER_ENTERED'
    ) {}

    public function toArray(): array
    {
        return [
            'range' => $this->range,
            'majorDimension' => $this->majorDimension,
            'values' => [$this->values],
        ];
    }
}