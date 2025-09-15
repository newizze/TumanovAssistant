<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\HttpRequestDto;
use App\DTOs\HttpResponseDto;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class HttpService
{
    private int $retryAttempts;
    private int $retryDelay;
    private array $defaultHeaders;

    public function __construct()
    {
        $this->retryAttempts = config('project.http.retry_attempts', 3);
        $this->retryDelay = config('project.http.retry_delay', 1000);
        $this->defaultHeaders = config('project.api.default_headers', []);
    }

    public function request(HttpRequestDto $requestDto): HttpResponseDto
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->retryAttempts) {
            try {
                $attempt++;

                Log::info('HTTP request attempt', [
                    'attempt' => $attempt,
                    'method' => $requestDto->getMethod(),
                    'url' => $requestDto->url,
                    'has_auth' => $requestDto->hasAuthentication(),
                ]);

                $response = $this->makeRequest($requestDto);

                Log::info('HTTP request successful', [
                    'method' => $requestDto->getMethod(),
                    'url' => $requestDto->url,
                    'status_code' => $response->statusCode,
                    'attempt' => $attempt,
                ]);

                return $response;

            } catch (ConnectionException $e) {
                $lastException = $e;

                Log::warning('HTTP connection failed', [
                    'attempt' => $attempt,
                    'method' => $requestDto->getMethod(),
                    'url' => $requestDto->url,
                    'error' => $e->getMessage(),
                    'will_retry' => $attempt < $this->retryAttempts,
                ]);

                if ($attempt < $this->retryAttempts) {
                    usleep($this->retryDelay * 1000);
                }

            } catch (RequestException $e) {
                Log::error('HTTP request failed with client/server error', [
                    'method' => $requestDto->getMethod(),
                    'url' => $requestDto->url,
                    'status_code' => $e->response->status(),
                    'error' => $e->getMessage(),
                ]);

                return new HttpResponseDto(
                    statusCode: $e->response->status(),
                    body: $e->response->body(),
                    headers: $e->response->headers(),
                    isSuccessful: false,
                    errorMessage: $e->getMessage(),
                );

            } catch (Throwable $e) {
                $lastException = $e;

                Log::error('HTTP request failed with unexpected error', [
                    'attempt' => $attempt,
                    'method' => $requestDto->getMethod(),
                    'url' => $requestDto->url,
                    'error' => $e->getMessage(),
                    'will_retry' => $attempt < $this->retryAttempts,
                ]);

                if ($attempt < $this->retryAttempts) {
                    usleep($this->retryDelay * 1000);
                }
            }
        }

        Log::error('HTTP request failed after all retry attempts', [
            'method' => $requestDto->getMethod(),
            'url' => $requestDto->url,
            'total_attempts' => $this->retryAttempts,
            'final_error' => $lastException?->getMessage(),
        ]);

        return new HttpResponseDto(
            statusCode: 0,
            body: '',
            headers: [],
            isSuccessful: false,
            errorMessage: $lastException?->getMessage() ?? 'Unknown error after all retry attempts',
        );
    }

    private function makeRequest(HttpRequestDto $requestDto): HttpResponseDto
    {
        $headers = array_merge($this->defaultHeaders, $requestDto->getAllHeaders());

        $httpClient = Http::withHeaders($headers)
            ->timeout($requestDto->timeout)
            ->connectTimeout($requestDto->connectTimeout);

        $response = match ($requestDto->getMethod()) {
            'GET' => $httpClient->get($requestDto->url, $requestDto->data),
            'POST' => $httpClient->post($requestDto->url, $requestDto->data),
            'PUT' => $httpClient->put($requestDto->url, $requestDto->data),
            'DELETE' => $httpClient->delete($requestDto->url, $requestDto->data),
            'PATCH' => $httpClient->patch($requestDto->url, $requestDto->data),
            default => throw new Exception("Unsupported HTTP method: {$requestDto->getMethod()}"),
        };

        return new HttpResponseDto(
            statusCode: $response->status(),
            body: $response->body(),
            headers: $response->headers(),
            isSuccessful: $response->successful(),
        );
    }
}
