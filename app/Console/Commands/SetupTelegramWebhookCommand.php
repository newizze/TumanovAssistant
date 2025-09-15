<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TelegramService;
use Exception;
use Illuminate\Console\Command;

class SetupTelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:setup-webhook 
                            {--url= : Custom webhook URL (defaults to APP_URL/telegram/webhook)}
                            {--secret-token= : Optional secret token for webhook security}
                            {--info : Show current webhook info instead of setting up}';

    protected $description = 'Setup Telegram bot webhook URL';

    public function __construct(
        private readonly TelegramService $telegramService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            if ($this->option('info')) {
                return $this->showWebhookInfo();
            }

            $url = $this->getWebhookUrl();
            $secretToken = $this->option('secret-token') ?: config('services.telegram.webhook_secret_token');

            $this->info("Setting up Telegram webhook...");
            $this->info("URL: {$url}");
            
            if ($secretToken) {
                $this->info("Secret token: provided");
            }

            $success = $this->telegramService->setWebhook($url, $secretToken);

            if ($success) {
                $this->info("✅ Telegram webhook setup successful!");
                
                $this->info("\nChecking webhook status...");
                $this->showWebhookInfo();
                
                return Command::SUCCESS;
            } else {
                $this->error("❌ Failed to setup Telegram webhook");
                return Command::FAILURE;
            }

        } catch (Exception $e) {
            $this->error("Error setting up webhook: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getWebhookUrl(): string
    {
        $customUrl = $this->option('url');
        
        if ($customUrl) {
            if (!filter_var($customUrl, FILTER_VALIDATE_URL)) {
                throw new Exception("Invalid URL provided: {$customUrl}");
            }
            return $customUrl;
        }

        $appUrl = config('app.url');
        
        if (!$appUrl || $appUrl === 'http://localhost') {
            throw new Exception(
                'APP_URL is not configured or set to localhost. ' .
                'Please set APP_URL in your .env file or use --url option.'
            );
        }

        return rtrim($appUrl, '/') . '/telegram/webhook';
    }

    private function showWebhookInfo(): int
    {
        try {
            $info = $this->telegramService->getWebhookInfo();

            if (empty($info)) {
                $this->error("Failed to get webhook info");
                return Command::FAILURE;
            }

            $this->info("📋 Current Telegram Webhook Info:");
            $this->line("URL: " . ($info['url'] ?: 'Not set'));
            $this->line("Has custom certificate: " . ($info['has_custom_certificate'] ? 'Yes' : 'No'));
            $this->line("Pending update count: " . ($info['pending_update_count'] ?? 0));
            
            if (isset($info['ip_address'])) {
                $this->line("IP address: " . $info['ip_address']);
            }
            
            if (isset($info['last_error_date'])) {
                $this->line("Last error date: " . date('Y-m-d H:i:s', $info['last_error_date']));
            }
            
            if (isset($info['last_error_message'])) {
                $this->line("Last error: " . $info['last_error_message']);
            }
            
            if (isset($info['max_connections'])) {
                $this->line("Max connections: " . $info['max_connections']);
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("Error getting webhook info: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}