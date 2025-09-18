<?php

declare(strict_types=1);

namespace App\DTOs\GoogleSheets;

class GoogleSheetsReadDto
{
    public function __construct(
        public readonly string $spreadsheetId,
        public readonly string $range,
        public readonly string $majorDimension = 'ROWS',
        public readonly string $valueRenderOption = 'FORMATTED_VALUE',
        public readonly string $dateTimeRenderOption = 'FORMATTED_STRING'
    ) {}
}