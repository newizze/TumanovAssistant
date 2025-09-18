<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ExecutorService;
use Illuminate\Console\Command;

class RefreshExecutorsCommand extends Command
{
    protected $signature = 'executors:refresh';

    protected $description = 'Refresh executors cache from Google Sheets';

    public function handle(ExecutorService $executorService): int
    {
        $this->info('Refreshing executors cache from Google Sheets...');

        try {
            $executors = $executorService->refreshExecutorsCache();

            $this->info("✅ Successfully refreshed executors cache.");
            $this->info("Found " . count($executors) . " approved executors:");

            foreach ($executors as $executor) {
                $this->line("  • {$executor['short_code']} - {$executor['name']} {$executor['tg_username']}");
            }

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("❌ Failed to refresh executors cache: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}