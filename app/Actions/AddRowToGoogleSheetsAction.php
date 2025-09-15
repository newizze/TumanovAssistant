<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\GoogleSheets\GoogleSheetsAddRowDto;
use App\DTOs\GoogleSheets\GoogleSheetsResponseDto;
use App\Services\GoogleSheetsService;
use Illuminate\Support\Facades\Log;

class AddRowToGoogleSheetsAction
{
    public function __construct(
        private readonly GoogleSheetsService $googleSheetsService
    ) {}

    public function execute(
        string $spreadsheetId,
        string $range,
        array $values
    ): GoogleSheetsResponseDto {
        Log::info('Adding row to Google Sheets', [
            'spreadsheet_id' => $spreadsheetId,
            'range' => $range,
            'values_count' => count($values),
        ]);

        $dto = new GoogleSheetsAddRowDto(
            spreadsheetId: $spreadsheetId,
            range: $range,
            values: $values
        );

        $result = $this->googleSheetsService->addRow($dto);

        if ($result->hasError()) {
            Log::error('Failed to add row to Google Sheets', [
                'spreadsheet_id' => $spreadsheetId,
                'range' => $range,
                'error' => $result->errorMessage,
            ]);
        } else {
            Log::info('Successfully added row to Google Sheets', [
                'spreadsheet_id' => $spreadsheetId,
                'range' => $range,
                'updated_cells' => $result->updatedCells,
            ]);
        }

        return $result;
    }
}