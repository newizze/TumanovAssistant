<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\HttpRequestDto;
use App\DTOs\OpenAI\ConversationRequestDto;
use App\DTOs\OpenAI\ConversationResponseDto;
use App\DTOs\OpenAI\ModelResponseDto;
use App\DTOs\OpenAI\ResponseRequestDto;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class OpenAIResponseService extends HttpService
{
    private const OPENAI_BASE_URL = 'https://api.openai.com/v1';
    private const CONVERSATIONS_ENDPOINT = '/conversations';
    private const RESPONSES_ENDPOINT = '/responses';
    private const CONVERSATION_TIMEOUT_HOURS = 1;

    private string $apiKey;

    public function __construct()
    {
        parent::__construct();
        $this->apiKey = config('services.openai.api_key');
        
        if (empty($this->apiKey)) {
            throw new Exception('OpenAI API key not configured');
        }
    }

    public function createResponse(ResponseRequestDto $requestDto, User $user): ModelResponseDto
    {
        $conversationId = $this->getOrCreateConversation($user);
        
        if ($conversationId && !$requestDto->conversationId) {
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
            url: self::OPENAI_BASE_URL . self::RESPONSES_ENDPOINT,
            data: $requestDto->toArray(),
            bearerToken: $this->apiKey,
            timeout: 120,
        );

        Log::info('Creating OpenAI response', [
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'model' => $requestDto->model,
            'has_tools' => !empty($requestDto->tools),
        ]);

        $response = $this->request($httpRequest);

        if (!$response->isOk()) {
            Log::error('OpenAI response creation failed', [
                'user_id' => $user->id,
                'status_code' => $response->statusCode,
                'error' => $response->errorMessage,
                'response_body' => $response->body,
            ]);
            
            throw new Exception("OpenAI API request failed: {$response->errorMessage}");
        }

        $responseData = $response->getJsonData();
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
            url: self::OPENAI_BASE_URL . self::CONVERSATIONS_ENDPOINT,
            data: $requestDto->toArray(),
            bearerToken: $this->apiKey,
        );

        Log::info('Creating OpenAI conversation');

        $response = $this->request($httpRequest);

        if (!$response->isOk()) {
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
                $conversationRequest = new ConversationRequestDto();
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

        if (!$user->conversation_updated_at) {
            return true;
        }

        $hoursSinceUpdate = $user->conversation_updated_at->diffInHours(Carbon::now());
        
        return $hoursSinceUpdate >= self::CONVERSATION_TIMEOUT_HOURS;
    }

    private function updateUserConversation(User $user, ?string $conversationId): void
    {
        if (!$conversationId) {
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
}