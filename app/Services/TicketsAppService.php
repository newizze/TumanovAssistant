<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\HttpRequestDto;
use App\DTOs\TicketsApp\TicketsAppResponseDto;
use App\DTOs\TicketsApp\TicketsAppTicketCreateDto;
use Illuminate\Support\Facades\Log;

class TicketsAppService extends HttpService
{
    private function getBaseUrl(): string
    {
        /** @var string $url */
        $url = config('project.tickets_app.base_url', 'http://localhost:8000');

        return rtrim($url, '/');
    }

    private function getApiKey(): string
    {
        /** @var string $key */
        $key = config('project.tickets_app.api_key', '');

        return $key;
    }

    /**
     * @return array<string, string>
     */
    private function getApiHeaders(): array
    {
        return [
            'X-Bot-API-Key' => $this->getApiKey(),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getExecutors(): array
    {
        try {
            $request = new HttpRequestDto(
                method: 'GET',
                url: $this->getBaseUrl().'/api/v1/bot/executors',
                headers: $this->getApiHeaders(),
            );

            $response = $this->request($request);

            if ($response->hasError()) {
                Log::error('Failed to fetch executors from tickets-app', [
                    'error' => $response->errorMessage,
                    'status_code' => $response->statusCode,
                ]);

                return [];
            }

            $data = $response->getJsonData();
            /** @var array<int, array<string, mixed>> $executors */
            $executors = $data['executors'] ?? [];

            Log::info('Successfully fetched executors from tickets-app', [
                'count' => count($executors),
            ]);

            return $executors;

        } catch (\Throwable $e) {
            Log::error('Exception fetching executors from tickets-app', [
                'exception' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getLookups(): array
    {
        try {
            $request = new HttpRequestDto(
                method: 'GET',
                url: $this->getBaseUrl().'/api/v1/bot/lookups',
                headers: $this->getApiHeaders(),
            );

            $response = $this->request($request);

            if ($response->hasError()) {
                Log::error('Failed to fetch lookups from tickets-app', [
                    'error' => $response->errorMessage,
                    'status_code' => $response->statusCode,
                ]);

                return [];
            }

            $data = $response->getJsonData();
            /** @var array<int, array<string, mixed>> $priorities */
            $priorities = $data['priorities'] ?? [];
            /** @var array<int, array<string, mixed>> $taskTypes */
            $taskTypes = $data['task_types'] ?? [];
            /** @var array<int, array<string, mixed>> $statuses */
            $statuses = $data['statuses'] ?? [];

            Log::info('Successfully fetched lookups from tickets-app', [
                'priorities_count' => count($priorities),
                'task_types_count' => count($taskTypes),
                'statuses_count' => count($statuses),
            ]);

            return [
                'priorities' => $priorities,
                'task_types' => $taskTypes,
                'statuses' => $statuses,
            ];

        } catch (\Throwable $e) {
            Log::error('Exception fetching lookups from tickets-app', [
                'exception' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function createTicket(TicketsAppTicketCreateDto $dto): TicketsAppResponseDto
    {
        try {
            $request = new HttpRequestDto(
                method: 'POST',
                url: $this->getBaseUrl().'/api/v1/bot/tickets',
                data: $dto->toArray(),
                headers: $this->getApiHeaders(),
            );

            $response = $this->request($request);

            if ($response->hasError()) {
                Log::error('Failed to create ticket in tickets-app', [
                    'error' => $response->errorMessage,
                    'status_code' => $response->statusCode,
                    'response_body' => $response->body,
                ]);

                return TicketsAppResponseDto::error(
                    $response->errorMessage ?? 'Failed to create ticket'
                );
            }

            $data = $response->getJsonData();

            if (! ($data['success'] ?? false)) {
                /** @var string $errorMessage */
                $errorMessage = $data['detail'] ?? $data['message'] ?? 'Unknown error creating ticket';

                return TicketsAppResponseDto::error($errorMessage);
            }

            $ticket = is_array($data['ticket'] ?? null) ? $data['ticket'] : [];

            Log::info('Successfully created ticket in tickets-app', [
                'ticket_uid' => $ticket['ticket_uid'] ?? null,
                'short_title' => $dto->shortTitle,
            ]);

            /** @var array<string, mixed> $data */
            return TicketsAppResponseDto::success($data);

        } catch (\Throwable $e) {
            Log::error('Exception creating ticket in tickets-app', [
                'exception' => $e->getMessage(),
            ]);

            return TicketsAppResponseDto::error('Ошибка создания тикета: '.$e->getMessage());
        }
    }
}
