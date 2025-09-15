<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\Telegram\TelegramSendMessageDto;
use App\DTOs\Telegram\TelegramWebhookDto;
use App\Services\OpenAIResponseService;
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
            if (!$this->validateWebhookSecret($request)) {
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
            'is_private' => $message->isPrivateChat(),
        ]);

        if (!$message->isPrivateChat()) {
            Log::info('Ignoring non-private chat message', [
                'chat_type' => $message->chat->type,
                'chat_id' => $message->chat->id,
            ]);
            return;
        }

        if ($message->hasText()) {
            $this->processTextMessage($message);
        } elseif ($message->hasVoice()) {
            $this->processVoiceMessage($message);
        } else {
            Log::info('Message type not supported', [
                'message_id' => $message->messageId,
                'user_id' => $message->from->id,
            ]);

            $this->sendReply($message->chat->id, 'Извините, я поддерживаю только текстовые и голосовые сообщения.');
        }
    }

    private function processTextMessage($message): void
    {
        Log::info('Processing text message', [
            'message_id' => $message->messageId,
            'user_id' => $message->from->id,
            'text_length' => strlen($message->text),
        ]);

        $this->sendReply($message->chat->id, 'Получил ваше текстовое сообщение: ' . $message->text);
    }

    private function processVoiceMessage($message): void
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
            
            if (!$fileDto) {
                $this->sendReply($message->chat->id, 'Ошибка: не удалось получить информацию о файле.');
                return;
            }

            $audioContent = $this->telegramService->downloadFile($fileDto);
            
            if (!$audioContent) {
                $this->sendReply($message->chat->id, 'Ошибка: не удалось скачать аудиофайл.');
                return;
            }

            $transcription = $this->openAIService->transcribeAudioFromContent(
                audioContent: $audioContent,
                filename: 'voice_message.ogg',
                language: 'ru'
            );

            if (!$transcription->hasText()) {
                $this->sendReply($message->chat->id, 'Не удалось распознать текст из голосового сообщения.');
                return;
            }

            $responseText = "🎤 Распознанный текст:\n\n" . $transcription->text;
            $this->sendReply($message->chat->id, $responseText);

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
    }

    private function sendReply(int $chatId, string $text): void
    {
        $messageDto = new TelegramSendMessageDto(
            chatId: $chatId,
            text: $text,
        );

        $success = $this->telegramService->sendMessage($messageDto);

        if (!$success) {
            Log::error('Failed to send reply message', [
                'chat_id' => $chatId,
                'text_length' => strlen($text),
            ]);
        }
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
        
        if (!$isValid) {
            Log::warning('Telegram webhook secret token mismatch', [
                'expected_length' => strlen($expectedToken),
                'provided_length' => strlen($providedToken),
            ]);
        }

        return $isValid;
    }
}