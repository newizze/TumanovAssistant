<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class TestThreeButtonsCommand extends Command
{
    protected $signature = 'test:three-buttons {user_id=1}';

    protected $description = 'Test new three buttons interface for task creation';

    public function __construct(
        private readonly TelegramService $telegramService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $userId = (int) $this->argument('user_id');

        // Получаем пользователя из базы данных
        $user = User::find($userId);

        if (!$user) {
            $this->error("User with ID {$userId} not found");
            return Command::FAILURE;
        }

        if (!$user->telegram_id) {
            $this->error("User {$userId} doesn't have telegram_id");
            return Command::FAILURE;
        }

        $testMessage = "**Название:**
Настроить интеграцию с AmoCRM

**Описание:**
Настроить автоматическое поступление заявок из форм сайта в AmoCRM

**Тип задачи:** ⚙️ Настройка CRM/систем/интеграций
**Приоритет:** Средний
**Исполнитель:** ИТ ВУ
**Отправитель:** ГД ВТ<!-- NEED_CONFIRM -->";

        $success = $this->telegramService->sendMarkdownMessageWithThreeButtons($user->telegram_id, $testMessage);

        if ($success) {
            $this->info("Test message with three buttons sent successfully to user {$userId} (telegram_id: {$user->telegram_id})");
        } else {
            $this->error("Failed to send test message to user {$userId} (telegram_id: {$user->telegram_id})");
        }

        return $success ? Command::SUCCESS : Command::FAILURE;
    }
}