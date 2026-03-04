<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\TicketsApp\TicketsAppResponseDto;
use App\DTOs\TicketsApp\TicketsAppTicketCreateDto;
use App\Services\TicketsAppService;
use Illuminate\Support\Facades\Log;

class CreateTicketInAppAction
{
    public function __construct(
        private readonly TicketsAppService $ticketsAppService
    ) {}

    /**
     * @param  array<int, string>  $fileLinks
     */
    public function execute(
        string $shortTitle,
        string $description,
        string $expectedResult,
        int $priorityId,
        int $taskTypeId,
        int $executorId,
        string $authorTelegramUsername,
        array $fileLinks = [],
        bool $needsReview = false,
    ): TicketsAppResponseDto {
        Log::info('Creating ticket in tickets-app', [
            'short_title' => $shortTitle,
            'priority_id' => $priorityId,
            'task_type_id' => $taskTypeId,
            'executor_id' => $executorId,
            'author_telegram_username' => $authorTelegramUsername,
            'file_links_count' => count($fileLinks),
            'needs_review' => $needsReview,
        ]);

        $dto = new TicketsAppTicketCreateDto(
            shortTitle: $shortTitle,
            description: $description,
            expectedResult: $expectedResult,
            priorityId: $priorityId,
            taskTypeId: $taskTypeId,
            executorId: $executorId,
            authorTelegramUsername: $authorTelegramUsername,
            fileLinks: $fileLinks,
            needsReview: $needsReview,
        );

        $result = $this->ticketsAppService->createTicket($dto);

        if ($result->hasError()) {
            Log::error('Failed to create ticket in tickets-app', [
                'error' => $result->errorMessage,
                'short_title' => $shortTitle,
            ]);
        } else {
            Log::info('Successfully created ticket in tickets-app', [
                'ticket_uid' => $result->getTicketUid(),
                'short_title' => $shortTitle,
            ]);
        }

        return $result;
    }
}
