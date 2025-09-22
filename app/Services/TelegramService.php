<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\HttpRequestDto;
use App\DTOs\Telegram\TelegramFileDto;
use App\DTOs\Telegram\TelegramInlineKeyboardButtonDto;
use App\DTOs\Telegram\TelegramInlineKeyboardDto;
use App\DTOs\Telegram\TelegramSendMessageDto;
use Exception;
use Illuminate\Support\Facades\Log;

class TelegramService extends HttpService
{
    private const TELEGRAM_API_BASE_URL = 'https://api.telegram.org/bot';

    private readonly string $botToken;

    public function __construct(
        private readonly MarkdownService $markdownService
    ) {
        parent::__construct();
        $this->botToken = config('services.telegram.bot_token', '');
    }

    private function ensureBotTokenConfigured(): void
    {
        if ($this->botToken === '' || $this->botToken === '0') {
            throw new Exception('Telegram bot token not configured');
        }
    }

    public function sendMessage(TelegramSendMessageDto $messageDto): bool
    {
        $this->ensureBotTokenConfigured();

        $httpRequest = new HttpRequestDto(
            method: 'POST',
            url: $this->getApiUrl('sendMessage'),
            data: $messageDto->toArray(),
            timeout: 30,
        );

        Log::info('Sending Telegram message', [
            'chat_id' => $messageDto->chatId,
            'text_length' => strlen($messageDto->text),
        ]);

        $response = $this->request($httpRequest);

        if (! $response->isOk()) {
            Log::error('Failed to send Telegram message', [
                'chat_id' => $messageDto->chatId,
                'status_code' => $response->statusCode,
                'error' => $response->errorMessage,
                'response_body' => $response->body,
            ]);

            return false;
        }

        Log::info('Telegram message sent successfully', [
            'chat_id' => $messageDto->chatId,
        ]);

        return true;
    }

    public function sendMarkdownMessage(int|string $chatId, string $text): bool
    {
        $formattedText = $this->markdownService->prepareForTelegram($text);

        $messageDto = new TelegramSendMessageDto(
            chatId: $chatId,
            text: $formattedText,
            parseMode: 'MarkdownV2'
        );

        return $this->sendMessage($messageDto);
    }

    public function sendMarkdownMessageWithThreeButtons(int|string $chatId, string $text): bool
    {
        $formattedText = $this->markdownService->prepareForTelegram($text);

        $cancelButton = new TelegramInlineKeyboardButtonDto(
            text: 'Отмена',
            callbackData: 'task_cancel'
        );

        $newTaskButton = new TelegramInlineKeyboardButtonDto(
            text: 'Новая задача',
            callbackData: 'task_new'
        );

        $sendButton = new TelegramInlineKeyboardButtonDto(
            text: 'Отправить',
            callbackData: 'confirm_yes'
        );

        $keyboard = new TelegramInlineKeyboardDto([
            [$cancelButton, $newTaskButton, $sendButton],
        ]);

        $messageDto = new TelegramSendMessageDto(
            chatId: $chatId,
            text: $formattedText,
            parseMode: 'MarkdownV2',
            replyMarkup: $keyboard
        );

        return $this->sendMessage($messageDto);
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): bool
    {
        $this->ensureBotTokenConfigured();

        $data = ['callback_query_id' => $callbackQueryId];

        if ($text !== null) {
            $data['text'] = $text;
        }

        $httpRequest = new HttpRequestDto(
            method: 'POST',
            url: $this->getApiUrl('answerCallbackQuery'),
            data: $data,
            timeout: 30,
        );

        Log::info('Answering callback query', [
            'callback_query_id' => $callbackQueryId,
            'has_text' => $text !== null,
        ]);

        $response = $this->request($httpRequest);

        if (! $response->isOk()) {
            Log::error('Failed to answer callback query', [
                'callback_query_id' => $callbackQueryId,
                'status_code' => $response->statusCode,
                'error' => $response->errorMessage,
            ]);

            return false;
        }

        return true;
    }

    public function editMessageReplyMarkup(int|string $chatId, int $messageId, ?TelegramInlineKeyboardDto $replyMarkup = null): bool
    {
        $this->ensureBotTokenConfigured();

        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ];

        if ($replyMarkup instanceof \App\DTOs\Telegram\TelegramInlineKeyboardDto) {
            $data['reply_markup'] = $replyMarkup->toArray();
        } else {
            // Убираем кнопки полностью
            $data['reply_markup'] = json_encode(['inline_keyboard' => []]);
        }

        $httpRequest = new HttpRequestDto(
            method: 'POST',
            url: $this->getApiUrl('editMessageReplyMarkup'),
            data: $data,
            timeout: 30,
        );

        Log::info('Editing message reply markup', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'removing_markup' => ! $replyMarkup instanceof \App\DTOs\Telegram\TelegramInlineKeyboardDto,
        ]);

        $response = $this->request($httpRequest);

        if (! $response->isOk()) {
            Log::error('Failed to edit message reply markup', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'status_code' => $response->statusCode,
                'error' => $response->errorMessage,
            ]);

            return false;
        }

        return true;
    }

    public function getFile(string $fileId): ?TelegramFileDto
    {
        $this->ensureBotTokenConfigured();

        $httpRequest = new HttpRequestDto(
            method: 'GET',
            url: $this->getApiUrl('getFile'),
            data: ['file_id' => $fileId],
            timeout: 30,
        );

        Log::info('Getting Telegram file info', [
            'file_id' => $fileId,
        ]);

        $response = $this->request($httpRequest);

        if (! $response->isOk()) {
            Log::error('Failed to get Telegram file info', [
                'file_id' => $fileId,
                'status_code' => $response->statusCode,
                'error' => $response->errorMessage,
            ]);

            return null;
        }

        $responseData = $response->getJsonData();

        if (! $responseData['ok'] || ! isset($responseData['result'])) {
            Log::error('Invalid Telegram API response for file info', [
                'file_id' => $fileId,
                'response' => $responseData,
            ]);

            return null;
        }

        return TelegramFileDto::fromArray($responseData['result']);
    }

    public function downloadFile(TelegramFileDto $fileDto): ?string
    {
        $this->ensureBotTokenConfigured();

        if (! $fileDto->filePath) {
            Log::error('File path not available for download', [
                'file_id' => $fileDto->fileId,
            ]);

            return null;
        }

        $fileUrl = $fileDto->getFileUrl($this->botToken);

        $httpRequest = new HttpRequestDto(
            method: 'GET',
            url: $fileUrl,
            timeout: 60,
        );

        Log::info('Downloading Telegram file', [
            'file_id' => $fileDto->fileId,
            'file_path' => $fileDto->filePath,
        ]);

        $response = $this->request($httpRequest);

        if (! $response->isOk()) {
            Log::error('Failed to download Telegram file', [
                'file_id' => $fileDto->fileId,
                'status_code' => $response->statusCode,
                'error' => $response->errorMessage,
            ]);

            return null;
        }

        Log::info('Telegram file downloaded successfully', [
            'file_id' => $fileDto->fileId,
            'size_bytes' => strlen($response->body),
        ]);

        return $response->body;
    }

    public function setWebhook(string $url, ?string $secretToken = null, ?array $allowedUpdates = null): bool
    {
        $this->ensureBotTokenConfigured();

        $data = ['url' => $url];

        if ($secretToken) {
            $data['secret_token'] = $secretToken;
        }

        if ($allowedUpdates) {
            $data['allowed_updates'] = json_encode($allowedUpdates);
        }

        $httpRequest = new HttpRequestDto(
            method: 'POST',
            url: $this->getApiUrl('setWebhook'),
            data: $data,
            timeout: 30,
        );

        Log::info('Setting Telegram webhook', [
            'url' => $url,
            'has_secret_token' => $secretToken !== null,
        ]);

        $response = $this->request($httpRequest);

        if (! $response->isOk()) {
            Log::error('Failed to set Telegram webhook', [
                'url' => $url,
                'status_code' => $response->statusCode,
                'error' => $response->errorMessage,
                'response_body' => $response->body,
            ]);

            return false;
        }

        $responseData = $response->getJsonData();

        if (! $responseData['ok']) {
            Log::error('Telegram API returned error for webhook setup', [
                'url' => $url,
                'response' => $responseData,
            ]);

            return false;
        }

        Log::info('Telegram webhook set successfully', [
            'url' => $url,
        ]);

        return true;
    }

    public function getWebhookInfo(): array
    {
        $this->ensureBotTokenConfigured();

        $httpRequest = new HttpRequestDto(
            method: 'GET',
            url: $this->getApiUrl('getWebhookInfo'),
            timeout: 30,
        );

        $response = $this->request($httpRequest);

        if (! $response->isOk()) {
            Log::error('Failed to get Telegram webhook info', [
                'status_code' => $response->statusCode,
                'error' => $response->errorMessage,
            ]);

            return [];
        }

        $responseData = $response->getJsonData();

        return $responseData['ok'] ? $responseData['result'] : [];
    }

    private function getApiUrl(string $method): string
    {
        return self::TELEGRAM_API_BASE_URL.$this->botToken.'/'.$method;
    }
}
