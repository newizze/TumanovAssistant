<?php

namespace App\Console\Commands;

use App\DTOs\Telegram\TelegramSendMessageDto;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyBotRecovery extends Command
{
    protected $signature = 'bot:notify-recovery';

    protected $description = 'Отправить уведомление о восстановлении бота всем активным пользователям';

    public function __construct(
        private readonly TelegramService $telegramService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $message = 'Бот снова работает! 🦆😎 (Ваш голосовой бот восстановил свою работу и снова готов принимать задачи)';

        $activeUsers = User::active()->telegram()->get();

        if ($activeUsers->isEmpty()) {
            $this->info('Нет активных пользователей Telegram для отправки уведомлений.');
            return self::SUCCESS;
        }

        $this->info("Найдено {$activeUsers->count()} активных пользователей Telegram.");

        $successCount = 0;
        $failureCount = 0;

        $progressBar = $this->output->createProgressBar($activeUsers->count());
        $progressBar->start();

        foreach ($activeUsers as $user) {
            $messageDto = new TelegramSendMessageDto(
                chatId: $user->telegram_id,
                text: $message
            );

            $success = $this->telegramService->sendMessage($messageDto);

            if ($success) {
                $successCount++;
                Log::info('Уведомление о восстановлении бота отправлено', [
                    'user_id' => $user->id,
                    'telegram_id' => $user->telegram_id,
                ]);
            } else {
                $failureCount++;
                Log::error('Не удалось отправить уведомление о восстановлении бота', [
                    'user_id' => $user->id,
                    'telegram_id' => $user->telegram_id,
                ]);
            }

            $progressBar->advance();
            usleep(100000); // Задержка 100ms между отправками
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Результаты рассылки:");
        $this->info("✅ Успешно отправлено: {$successCount}");

        if ($failureCount > 0) {
            $this->error("❌ Ошибки отправки: {$failureCount}");
        }

        Log::info('Рассылка уведомлений о восстановлении бота завершена', [
            'total_users' => $activeUsers->count(),
            'success_count' => $successCount,
            'failure_count' => $failureCount,
        ]);

        return self::SUCCESS;
    }
}
