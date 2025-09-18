<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\HttpRequestDto;
use App\DTOs\OpenAI\ConversationRequestDto;
use App\DTOs\OpenAI\ConversationResponseDto;
use App\DTOs\OpenAI\ModelResponseDto;
use App\DTOs\OpenAI\ResponseRequestDto;
use App\DTOs\OpenAI\WhisperTranscriptionRequestDto;
use App\DTOs\OpenAI\WhisperTranscriptionResponseDto;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIResponseService extends HttpService
{
    private const OPENAI_BASE_URL = 'https://api.openai.com/v1';

    private const CONVERSATIONS_ENDPOINT = '/conversations';

    private const RESPONSES_ENDPOINT = '/responses';

    private const TRANSCRIPTIONS_ENDPOINT = '/audio/transcriptions';

    private const CONVERSATION_TIMEOUT_HOURS = 1;

    private readonly string $apiKey;

    public function __construct()
    {
        parent::__construct();
        $this->apiKey = config('services.openai.api_key');

        if ($this->apiKey === '' || $this->apiKey === '0') {
            throw new Exception('OpenAI API key not configured');
        }
    }

    public function createResponse(ResponseRequestDto $requestDto, User $user): ModelResponseDto
    {
        $conversationId = $this->getOrCreateConversation($user);

        if ($conversationId && ! $requestDto->conversationId) {
            $requestDto = new ResponseRequestDto(
                model: $requestDto->model,
                input: $requestDto->input,
                instructions: $requestDto->instructions,
                conversationId: $conversationId,
                previousResponseId: $requestDto->previousResponseId,
                maxOutputTokens: $requestDto->maxOutputTokens,
                temperature: $requestDto->temperature,
                tools: $requestDto->tools,
                toolChoice: $requestDto->toolChoice,
                metadata: $requestDto->metadata,
                store: $requestDto->store,
                stream: $requestDto->stream,
            );
        }

        $httpRequest = new HttpRequestDto(
            method: 'POST',
            url: self::OPENAI_BASE_URL.self::RESPONSES_ENDPOINT,
            data: $requestDto->toArray(),
            bearerToken: $this->apiKey,
            timeout: 120,
        );

        Log::info('Creating OpenAI response', [
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'model' => $requestDto->model,
            'has_tools' => $requestDto->tools !== null && $requestDto->tools !== [],
            'request_data' => $requestDto->toArray(), // Логируем весь запрос
        ]);

        $response = $this->request($httpRequest);

        if (! $response->isOk()) {
            Log::error('OpenAI response creation failed', [
                'user_id' => $user->id,
                'status_code' => $response->statusCode,
                'error' => $response->errorMessage,
                'response_body' => $response->body,
            ]);

            throw new Exception("OpenAI API request failed: {$response->errorMessage}");
        }

        $responseData = $response->getJsonData();

        Log::info('OpenAI API response received', [
            'response_id' => $responseData['id'] ?? 'unknown',
            'status' => $responseData['status'] ?? 'unknown',
            'output_count' => count($responseData['output'] ?? []),
            'full_response' => $responseData, // Логируем полный ответ для отладки
        ]);

        $modelResponse = ModelResponseDto::fromArray($responseData);

        $this->updateUserConversation($user, $conversationId ?? $responseData['conversation_id'] ?? null);

        Log::info('OpenAI response created successfully', [
            'user_id' => $user->id,
            'response_id' => $modelResponse->id,
            'status' => $modelResponse->status,
            'total_tokens' => $modelResponse->getTotalTokens(),
        ]);

        return $modelResponse;
    }

    public function createConversation(ConversationRequestDto $requestDto): ConversationResponseDto
    {
        $httpRequest = new HttpRequestDto(
            method: 'POST',
            url: self::OPENAI_BASE_URL.self::CONVERSATIONS_ENDPOINT,
            data: $requestDto->toArray(),
            bearerToken: $this->apiKey,
        );

        Log::info('Creating OpenAI conversation');

        $response = $this->request($httpRequest);

        if (! $response->isOk()) {
            Log::error('OpenAI conversation creation failed', [
                'status_code' => $response->statusCode,
                'error' => $response->errorMessage,
                'response_body' => $response->body,
            ]);

            throw new Exception("Failed to create conversation: {$response->errorMessage}");
        }

        $responseData = $response->getJsonData();
        $conversation = ConversationResponseDto::fromArray($responseData);

        Log::info('OpenAI conversation created successfully', [
            'conversation_id' => $conversation->id,
        ]);

        return $conversation;
    }

    private function getOrCreateConversation(User $user): ?string
    {
        if ($this->shouldCreateNewConversation($user)) {
            try {
                $conversationRequest = new ConversationRequestDto;
                $conversation = $this->createConversation($conversationRequest);

                $this->updateUserConversation($user, $conversation->id);

                return $conversation->id;
            } catch (Exception $e) {
                Log::error('Failed to create new conversation, proceeding without conversation', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        }

        return $user->conversation_id;
    }

    private function shouldCreateNewConversation(User $user): bool
    {
        if (empty($user->conversation_id)) {
            return true;
        }

        if (! $user->conversation_updated_at) {
            return true;
        }

        $hoursSinceUpdate = $user->conversation_updated_at->diffInHours(Carbon::now());

        return $hoursSinceUpdate >= self::CONVERSATION_TIMEOUT_HOURS;
    }

    private function updateUserConversation(User $user, ?string $conversationId): void
    {
        if (! $conversationId) {
            return;
        }

        $user->update([
            'conversation_id' => $conversationId,
            'conversation_updated_at' => Carbon::now(),
        ]);

        Log::info('Updated user conversation', [
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
        ]);
    }

    public function transcribeAudio(WhisperTranscriptionRequestDto $requestDto): WhisperTranscriptionResponseDto
    {
        Log::info('Starting audio transcription', [
            'model' => $requestDto->model,
            'file_path' => $requestDto->filePath,
            'language' => $requestDto->language,
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])->timeout(60)->attach(
                'file',
                file_get_contents($requestDto->filePath),
                basename($requestDto->filePath)
            )->post(
                self::OPENAI_BASE_URL.self::TRANSCRIPTIONS_ENDPOINT,
                $requestDto->toMultipartArray()
            );

            if (! $response->successful()) {
                Log::error('Audio transcription failed', [
                    'status_code' => $response->status(),
                    'error' => $response->body(),
                ]);

                throw new Exception("Transcription failed: {$response->body()}");
            }

            $responseData = $response->json();
            $transcription = WhisperTranscriptionResponseDto::fromArray($responseData);

            Log::info('Audio transcription completed successfully', [
                'text_length' => strlen($transcription->text),
                'language' => $transcription->language,
                'duration' => $transcription->duration,
            ]);

            return $transcription;

        } catch (RequestException $e) {
            Log::error('HTTP request failed during transcription', [
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Transcription request failed: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    public function transcribeAudioFromContent(string $audioContent, string $filename = 'audio.ogg', ?string $language = null): WhisperTranscriptionResponseDto
    {
        $tempPath = storage_path('app/temp/'.uniqid().'_'.$filename);

        try {
            if (! is_dir(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            file_put_contents($tempPath, $audioContent);

            $requestDto = new WhisperTranscriptionRequestDto(
                filePath: $tempPath,
                language: $language,
            );

            return $this->transcribeAudio($requestDto);
        } catch (Exception $e) {
            Log::error('Failed to transcribe audio from content', [
                'error' => $e->getMessage(),
                'filename' => $filename,
            ]);

            throw new Exception("Audio transcription failed: {$e->getMessage()}", $e->getCode(), $e);
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }
}
