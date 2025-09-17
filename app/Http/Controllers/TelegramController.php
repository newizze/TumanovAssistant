<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\Telegram\TelegramSendMessageDto;
use App\Services\MarkdownService;
use App\DTOs\Telegram\TelegramWebhookDto;
use App\Models\User;
use App\Services\MessageProcessingService;
use App\Services\OpenAIResponseService;
use App\Services\TelegramFileService;
use App\Services\TelegramService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramController extends Controller
{
    public function __construct(
        private readonly TelegramService $telegramService,
        private readonly OpenAIResponseService $openAIService,
        private readonly MessageProcessingService $messageProcessingService,
        private readonly TelegramFileService $telegramFileService,
        private readonly MarkdownService $markdownService,
    ) {}

    public function webhook(Request $request): JsonResponse
    {
        try {
            Log::info('Telegram webhook received', [
                'headers' => $request->headers->all(),
                'content_type' => $request->header('Content-Type'),
                'user_agent' => $request->header('User-Agent'),
            ]);

            // Validate webhook secret token if configured
            if (! $this->validateWebhookSecret($request)) {
                Log::warning('Telegram webhook secret token validation failed');

                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $data = $request->json()->all();

            if (empty($data)) {
                Log::warning('Telegram webhook received empty data');

                return response()->json(['ok' => true]);
            }

            Log::info('Telegram webhook data', [
                'update_id' => $data['update_id'] ?? null,
                'has_message' => isset($data['message']),
                'has_callback_query' => isset($data['callback_query']),
            ]);

            $webhookDto = TelegramWebhookDto::fromArray($data);

            $this->processWebhook($webhookDto);

            return response()->json(['ok' => true]);

        } catch (Exception $e) {
            Log::error('Error processing Telegram webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->json()->all(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    private function processWebhook(TelegramWebhookDto $webhook): void
    {
        if ($webhook->hasMessage()) {
            $this->processMessage($webhook->message);
        }

        if ($webhook->hasCallbackQuery()) {
            $this->processCallbackQuery($webhook->callbackQuery);
        }
    }

    private function processMessage($message): void
    {
        Log::info('Processing Telegram message', [
            'message_id' => $message->messageId,
            'user_id' => $message->from->id,
            'chat_id' => $message->chat->id,
            'has_text' => $message->hasText(),
            'has_voice' => $message->hasVoice(),
            'has_photo' => $message->hasPhoto(),
            'has_document' => $message->hasDocument(),
            'has_files' => $message->hasFiles(),
            'is_private' => $message->isPrivateChat(),
        ]);

        if (! $message->isPrivateChat()) {
            Log::info('Ignoring non-private chat message', [
                'chat_type' => $message->chat->type,
                'chat_id' => $message->chat->id,
            ]);

            return;
        }

        // Проверяем активность пользователя
        $user = $this->getOrCreateUser($message->from);

        if (! $user->isActive()) {
            Log::info('Inactive user tried to send message', [
                'message_id' => $message->messageId,
                'user_id' => $message->from->id,
                'telegram_id' => $user->telegram_id,
            ]);

            $this->sendReply($message->chat->id, 'Ваш аккаунт не активирован. Обратитесь к администратору для активации.');
            return;
        }

        if ($message->hasText() || $message->hasFiles()) {
            $this->processTextMessage($message, $user);
        } elseif ($message->hasVoice()) {
            $this->processVoiceMessage($message, $user);
        } else {
            Log::info('Message type not supported', [
                'message_id' => $message->messageId,
                'user_id' => $message->from->id,
            ]);

            $this->sendReply($message->chat->id, 'Извините, я поддерживаю только текстовые сообщения, фото, документы и голосовые сообщения.');
        }
    }

    private function processTextMessage($message, User $user): void
    {
        $messageText = $message->getMessageText();

        Log::info('Processing text message', [
            'message_id' => $message->messageId,
            'user_id' => $message->from->id,
            'text_length' => $messageText ? strlen((string) $messageText) : 0,
            'has_files' => $message->hasFiles(),
        ]);

        // Проверяем команду /restart
        if ($messageText === '/restart') {
            $this->handleRestartCommand($message, $user);
            return;
        }

        // Обрабатываем файлы если есть
        $fileLinks = [];
        if ($message->hasFiles()) {
            $this->sendReply($message->chat->id, 'Получил ваши файлы. Скачиваю...');

            $fileIds = $message->getAllFileIds();
            $fileLinks = $this->telegramFileService->processMessageFiles($fileIds);

            if (! empty($fileLinks)) {
                Log::info('Files processed successfully', [
                    'message_id' => $message->messageId,
                    'user_id' => $message->from->id,
                    'file_count' => count($fileLinks),
                ]);

                $this->sendReply(
                    $message->chat->id,
                    'Файлы успешно обработаны: '.count($fileLinks).' шт.'
                );
            } else {
                Log::warning('Failed to process files', [
                    'message_id' => $message->messageId,
                    'user_id' => $message->from->id,
                    'file_ids' => $fileIds,
                ]);

                $this->sendReply($message->chat->id, 'Не удалось обработать файлы.');
            }
        }

        // Если есть текст или файлы - обрабатываем через AI
        if ($messageText || ! empty($fileLinks)) {
            // Передаем текст и ссылки на файлы в MessageProcessingService
            $response = $this->messageProcessingService->processMessage(
                $messageText ?? '',
                $user,
                $fileLinks
            );

            $this->sendReply($message->chat->id, $response);
        } else {
            $this->sendReply($message->chat->id, 'Не удалось обработать ваше сообщение.');
        }
    }

    private function processVoiceMessage($message, User $user): void
    {
        Log::info('Processing voice message', [
            'message_id' => $message->messageId,
            'user_id' => $message->from->id,
            'voice_duration' => $message->voice->duration,
            'voice_file_id' => $message->voice->fileId,
        ]);

        $this->sendReply($message->chat->id, 'Получил ваше голосовое сообщение. Обрабатываю...');

        try {
            $fileDto = $this->telegramService->getFile($message->voice->fileId);

            if (! $fileDto) {
                $this->sendReply($message->chat->id, 'Ошибка: не удалось получить информацию о файле.');

                return;
            }

            $audioContent = $this->telegramService->downloadFile($fileDto);

            if (! $audioContent) {
                $this->sendReply($message->chat->id, 'Ошибка: не удалось скачать аудиофайл.');

                return;
            }

            $transcription = $this->openAIService->transcribeAudioFromContent(
                audioContent: $audioContent,
                filename: 'voice_message.ogg',
                language: 'ru'
            );

            if (! $transcription->hasText()) {
                $this->sendReply($message->chat->id, 'Не удалось распознать текст из голосового сообщения.');

                return;
            }

            // Обрабатываем распознанный текст через AI
            $response = $this->messageProcessingService->processMessage($transcription->text, $user);
            $this->sendReply($message->chat->id, $response);

            Log::info('Voice message processed successfully', [
                'message_id' => $message->messageId,
                'user_id' => $message->from->id,
                'transcription_length' => strlen($transcription->text),
                'detected_language' => $transcription->language,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to process voice message', [
                'message_id' => $message->messageId,
                'user_id' => $message->from->id,
                'error' => $e->getMessage(),
            ]);

            $this->sendReply($message->chat->id, 'Произошла ошибка при обработке голосового сообщения. Попробуйте еще раз.');
        }
    }

    private function processCallbackQuery($callbackQuery): void
    {
        Log::info('Processing callback query', [
            'callback_id' => $callbackQuery->id,
            'user_id' => $callbackQuery->from->id,
            'data' => $callbackQuery->data,
        ]);

        // Получаем пользователя
        $user = $this->getOrCreateUser($callbackQuery->from);

        if (!$user->isActive()) {
            Log::info('Inactive user tried to use callback', [
                'callback_id' => $callbackQuery->id,
                'user_id' => $callbackQuery->from->id,
            ]);
            return;
        }

        // Обрабатываем кнопку "Да" для подтверждения задачи
        if ($callbackQuery->data === 'confirm_yes') {
            $this->handleTaskConfirmation($callbackQuery, $user);
        }
    }

    private function handleTaskConfirmation($callbackQuery, User $user): void
    {
        Log::info('Processing task confirmation', [
            'callback_id' => $callbackQuery->id,
            'user_id' => $callbackQuery->from->id,
        ]);

        // Сначала отвечаем на callback query чтобы убрать загрузку
        $this->telegramService->answerCallbackQuery($callbackQuery->id);

        // Отправляем "Да" как текстовое сообщение для обработки AI
        $response = $this->messageProcessingService->processMessage('Да', $user);
        
        // Отправляем ответ пользователю
        $this->sendReply($callbackQuery->message->chat->id, $response);

        Log::info('Task confirmation processed', [
            'callback_id' => $callbackQuery->id,
            'user_id' => $callbackQuery->from->id,
        ]);
    }

    private function sendReply(int $chatId, string $text): void
    {
        // Очищаем служебные маркеры перед отправкой
        $cleanText = str_replace('<!-- NEED_CONFIRM -->', '', $text);
        
        // Проверяем, содержит ли сообщение черновик задачи с запросом подтверждения
        if ($this->isDraftConfirmationMessage($text)) {
            $success = $this->telegramService->sendMarkdownMessageWithYesButton($chatId, $cleanText);
        } else {
            // Prepare text for Telegram with proper Markdown formatting
            $safeText = $this->markdownService->prepareForTelegram($cleanText);

            $messageDto = new TelegramSendMessageDto(
                chatId: $chatId,
                text: $safeText,
                parseMode: 'MarkdownV2',
            );

            $success = $this->telegramService->sendMessage($messageDto);
        }

        if (! $success) {
            Log::error('Failed to send reply message', [
                'chat_id' => $chatId,
                'text_length' => strlen($text),
            ]);
        }
    }

    private function isDraftConfirmationMessage(string $text): bool
    {
        // Проверяем маркер need_confirm от AI
        return str_contains($text, '<!-- NEED_CONFIRM -->');
    }

    private function validateWebhookSecret(Request $request): bool
    {
        $expectedToken = config('services.telegram.webhook_secret_token');

        // If no secret token is configured, skip validation
        if (empty($expectedToken)) {
            return true;
        }

        $providedToken = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (empty($providedToken)) {
            Log::warning('Telegram webhook missing secret token header');

            return false;
        }

        $isValid = hash_equals($expectedToken, $providedToken);

        if (! $isValid) {
            Log::warning('Telegram webhook secret token mismatch', [
                'expected_length' => strlen((string) $expectedToken),
                'provided_length' => strlen($providedToken),
            ]);
        }

        return $isValid;
    }

    private function handleRestartCommand($message, User $user): void
    {
        Log::info('Processing /restart command', [
            'message_id' => $message->messageId,
            'user_id' => $message->from->id,
            'telegram_id' => $user->telegram_id,
        ]);

        try {
            // Очищаем conversation данные пользователя
            $user->clearConversationData();

            Log::info('User conversation data cleared successfully', [
                'user_id' => $user->id,
                'telegram_id' => $user->telegram_id,
            ]);

            $this->sendReply(
                $message->chat->id,
                '🔄 Готов'
            );

        } catch (Exception $e) {
            Log::error('Failed to clear conversation data', [
                'user_id' => $user->id,
                'telegram_id' => $user->telegram_id,
                'error' => $e->getMessage(),
            ]);

            $this->sendReply(
                $message->chat->id,
                '❌ Произошла ошибка при перезапуске. Попробуйте еще раз.'
            );
        }
    }

    private function getOrCreateUser($telegramUser): User
    {
        return User::firstOrCreate(
            ['telegram_id' => $telegramUser->id],
            [
                'name' => $telegramUser->firstName.($telegramUser->lastName ? ' '.$telegramUser->lastName : ''),
                'username' => $telegramUser->username,
                'telegram_id' => $telegramUser->id,
                'is_active' => false, // По умолчанию пользователь неактивен
            ]
        );
    }
}
