<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\GoogleSheets\GoogleSheetsAddRowDto;
use App\DTOs\GoogleSheets\GoogleSheetsResponseDto;
use App\DTOs\HttpRequestDto;
use Illuminate\Support\Facades\Log;

class GoogleSheetsService extends HttpService
{
    private const SHEETS_API_BASE_URL = 'https://sheets.googleapis.com/v4/spreadsheets';

    public function addRow(GoogleSheetsAddRowDto $dto): GoogleSheetsResponseDto
    {
        try {
            $accessToken = $this->getAccessToken();
            if (! $accessToken) {
                return GoogleSheetsResponseDto::error('Failed to get access token');
            }

            $url = sprintf(
                '%s/%s/values/%s:append?valueInputOption=%s',
                self::SHEETS_API_BASE_URL,
                $dto->spreadsheetId,
                $dto->range,
                $dto->valueInputOption
            );

            $request = new HttpRequestDto(
                method: 'POST',
                url: $url,
                data: $dto->toArray(),
                bearerToken: $accessToken
            );

            $response = $this->request($request);

            if ($response->hasError()) {
                Log::error('Google Sheets API request failed', [
                    'error' => $response->errorMessage,
                    'status_code' => $response->statusCode,
                    'spreadsheet_id' => $dto->spreadsheetId,
                    'range' => $dto->range,
                ]);

                return GoogleSheetsResponseDto::error(
                    $response->errorMessage ?? 'Google Sheets API request failed'
                );
            }

            $responseData = $response->getJsonData();

            Log::info('Successfully added row to Google Sheets', [
                'spreadsheet_id' => $dto->spreadsheetId,
                'range' => $dto->range,
                'updated_cells' => $responseData['updates']['updatedCells'] ?? 0,
            ]);

            return GoogleSheetsResponseDto::success($responseData);

        } catch (\Throwable $e) {
            Log::error('Exception occurred while adding row to Google Sheets', [
                'exception' => $e->getMessage(),
                'spreadsheet_id' => $dto->spreadsheetId,
                'range' => $dto->range,
            ]);

            return GoogleSheetsResponseDto::error($e->getMessage());
        }
    }

    private function getAccessToken(): ?string
    {
        try {
            $credentialsPath = base_path('credentials.json');

            if (! file_exists($credentialsPath)) {
                Log::error('Google credentials file not found', ['path' => $credentialsPath]);

                return null;
            }

            $credentials = json_decode(file_get_contents($credentialsPath), true);

            if (! $credentials) {
                Log::error('Failed to parse Google credentials file');

                return null;
            }

            $tokenUrl = 'https://oauth2.googleapis.com/token';
            $now = time();
            $expiry = $now + 3600; // 1 hour

            // Create JWT payload
            $payload = [
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/spreadsheets',
                'aud' => $tokenUrl,
                'exp' => $expiry,
                'iat' => $now,
            ];

            // Create JWT header
            $header = [
                'alg' => 'RS256',
                'typ' => 'JWT',
            ];

            // Encode JWT
            $headerEncoded = $this->base64UrlEncode(json_encode($header));
            $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
            $signature = $this->signJWT("$headerEncoded.$payloadEncoded", $credentials['private_key']);

            $jwt = "$headerEncoded.$payloadEncoded.$signature";

            // Request access token
            $request = new HttpRequestDto(
                method: 'POST',
                url: $tokenUrl,
                data: [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]
            );

            $response = $this->request($request);

            if ($response->hasError()) {
                Log::error('Failed to get Google access token', [
                    'error' => $response->errorMessage,
                    'status_code' => $response->statusCode,
                ]);

                return null;
            }

            $tokenData = $response->getJsonData();

            return $tokenData['access_token'] ?? null;

        } catch (\Throwable $e) {
            Log::error('Exception occurred while getting Google access token', [
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function signJWT(string $message, string $privateKey): string
    {
        $key = openssl_pkey_get_private($privateKey);

        if (! $key) {
            throw new \Exception('Invalid private key');
        }

        openssl_sign($message, $signature, $key, OPENSSL_ALGO_SHA256);

        return $this->base64UrlEncode($signature);
    }
}
