<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ExecutorService
{
    private const CACHE_KEY = 'approved_executors';

    private const CACHE_TTL = 3600; // 1 hour

    private const LOOKUPS_CACHE_KEY = 'tickets_app_lookups';

    public function __construct(
        private readonly TicketsAppService $ticketsAppService
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getApprovedExecutors(): array
    {
        /** @var array<int, array<string, mixed>> $result */
        $result = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn (): array => $this->fetchExecutorsFromApi());

        return $result;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getLookups(): array
    {
        /** @var array<string, array<int, array<string, mixed>>> $result */
        $result = Cache::remember(self::LOOKUPS_CACHE_KEY, self::CACHE_TTL, fn (): array => $this->ticketsAppService->getLookups());

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function refreshExecutorsCache(): array
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::LOOKUPS_CACHE_KEY);

        return $this->getApprovedExecutors();
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $executors
     * @return array<string, mixed>|null
     */
    public function findExecutorByTelegramUsername(?string $username, ?array $executors = null): ?array
    {
        if ($username === null) {
            return null;
        }

        $normalizedUsername = $this->normalizeTelegramUsername($username);

        if ($normalizedUsername === '') {
            return null;
        }

        $approvedExecutors = $executors ?? $this->getApprovedExecutors();

        foreach ($approvedExecutors as $executor) {
            /** @var string|null $tgUsername */
            $tgUsername = $executor['telegram_username'] ?? null;
            $executorUsername = $this->normalizeTelegramUsername($tgUsername);

            if ($executorUsername !== '' && $executorUsername === $normalizedUsername) {
                return $executor;
            }
        }

        return null;
    }

    private function normalizeTelegramUsername(?string $username): string
    {
        if ($username === null) {
            return '';
        }

        $normalized = trim($username);

        if ($normalized === '') {
            return '';
        }

        return ltrim(strtolower($normalized), '@');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchExecutorsFromApi(): array
    {
        try {
            $executors = $this->ticketsAppService->getExecutors();

            if ($executors === []) {
                Log::warning('No executors returned from tickets-app API');

                return [];
            }

            Log::info('Successfully fetched executors from tickets-app API', [
                'count' => count($executors),
            ]);

            return $executors;

        } catch (\Throwable $e) {
            Log::error('Exception occurred while fetching executors from tickets-app', [
                'exception' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to fetch executors from tickets-app: {$e->getMessage()}", $e->getCode(), $e);
        }
    }
}
