<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\OpenAI\ResponseRequestDto;
use App\Models\User;
use App\Tools\AddRowToSheetsToolDefinition;
use App\Tools\AddRowToSheetsToolHandler;
use Illuminate\Support\Facades\Log;

class MessageProcessingService
{
    public function __construct(
        private readonly OpenAIResponseService $openAIService,
        private readonly PromptService $promptService,
        private readonly AddRowToSheetsToolHandler $toolHandler,
        private readonly ExecutorService $executorService
    ) {}

    public function processMessage(string $messageText, User $user, array $fileLinks = []): string
    {
        try {
            Log::info('Processing message with AI', [
                'user_id' => $user->id,
                'message_length' => strlen($messageText),
                'file_links_count' => count($fileLinks),
            ]);

            // Подготавливаем информацию о файлах для промпта
            $fileInfo = '';
            if ($fileLinks !== []) {
                $fileInfo = "\n\n📎 Прикрепленные файлы (".count($fileLinks)." шт.):\n";
                foreach ($fileLinks as $index => $fileLink) {
                    $fileInfo .= 'Файл '.($index + 1).': '.$fileLink."\n";
                }
                $fileInfo .= "\n⚠️ ВАЖНО: При создании задачи эти ссылки ОБЯЗАТЕЛЬНО должны быть переданы в параметры file_link_1, file_link_2, file_link_3 соответственно!";
            }

            // Получаем список исполнителей из Google Sheets
            $executors = $this->executorService->getApprovedExecutors();
            $executorsList = '';
            foreach ($executors as $executor) {
                $executorsList .= "• Код: {$executor['short_code']} | ФИО: {$executor['full_name']}";
                if ($executor['position']) {
                    $executorsList .= " | Должность: {$executor['position']}";
                }
                if ($executor['tg_username']) {
                    $executorsList .= " | Telegram: {$executor['tg_username']}";
                }
                $executorsList .= "\n";
            }

            // Получаем определение инструмента для промпта
            $toolDefinition = json_encode(AddRowToSheetsToolDefinition::getDefinition(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            // Получаем промпт из XML файла
            $systemPrompt = $this->promptService->loadPrompt('task_creation', [
                'current_date' => now()->format('Y-m-d'),
                'user_timezone' => 'Europe/Moscow',
                'user_message' => $messageText.$fileInfo,
                'executors_list' => trim($executorsList),
                'telegram_username' => $user->username ?: 'unknown',
                'tool_definition' => $toolDefinition,
            ]);

            // Подготавливаем запрос к AI с инструментами (включаем файлы)
            $fullMessage = $messageText.$fileInfo;
            $requestDto = new ResponseRequestDto(
                model: 'gpt-4.1',
                input: $fullMessage,
                instructions: $systemPrompt,
                tools: [AddRowToSheetsToolDefinition::getDefinition()],
            );

            // Выполняем цикличные запросы до получения финального ответа
            $finalResponse = $this->processWithToolCalls($requestDto, $user);

            Log::info('Message processed successfully', [
                'user_id' => $user->id,
                'response_length' => strlen($finalResponse),
            ]);

            return $finalResponse;

        } catch (\Throwable $e) {
            Log::error('Failed to process message', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return "Произошла ошибка при обработке сообщения {$e->getMessage()}. Попробуйте еще раз.";
        }
    }

    private function handleAIResponse($response, User $user): string
    {
        $content = $response->getContent();
        $toolCalls = $response->getFunctionCalls();

        Log::info('Processing AI response', [
            'has_content' => ! empty($content),
            'tool_calls_count' => count($toolCalls),
            'tool_calls_structure' => $toolCalls,
        ]);

        // Если есть вызовы инструментов, обрабатываем их и делаем новый запрос
        if (! empty($toolCalls)) {
            $toolResults = [];

            foreach ($toolCalls as $toolCall) {
                Log::info('Processing tool call', [
                    'tool_call_structure' => $toolCall,
                ]);

                // Проверяем тип вызова функции и имя функции
                if ($toolCall['type'] === 'function_call' &&
                    isset($toolCall['function']) &&
                    $toolCall['function']['name'] === 'add_row_to_sheets') {

                    $arguments = $toolCall['function']['arguments'];

                    // Если arguments это строка, декодируем JSON
                    if (is_string($arguments)) {
                        $arguments = json_decode($arguments, true);
                    }

                    Log::info('Executing tool handler', [
                        'function_name' => $toolCall['function']['name'],
                        'arguments' => $arguments,
                    ]);

                    $result = $this->toolHandler->handle($arguments);

                    Log::info('Tool call executed', [
                        'tool_name' => $toolCall['function']['name'],
                        'success' => $result['success'],
                        'result' => $result,
                    ]);

                    // Подготавливаем результат для отправки в OpenAI
                    $toolResults[] = [
                        'call_id' => $toolCall['id'] ?? uniqid(),
                        'type' => 'function_result',
                        'function_result' => [
                            'output' => json_encode($result),
                        ],
                    ];
                }
            }

            // Если есть результаты функций, формируем ответ
            if ($toolResults !== []) {
                $toolSummary = '';
                foreach ($toolResults as $toolResult) {
                    $output = json_decode($toolResult['function_result']['output'], true);
                    if ($output['success']) {
                        $toolSummary .= "\n\n✅ ".$output['message'];
                    } else {
                        $toolSummary .= "\n\n❌ Ошибка: ".$output['error'];
                    }
                }

                return ($content ?: 'Задача обработана.');
            }
        }

        return $content ?: 'Ответ получен, но содержимое отсутствует.';
    }

    private function processWithToolCalls(ResponseRequestDto $requestDto, User $user, int $maxIterations = 5): string
    {
        $currentRequest = $requestDto;
        $iteration = 0;
        $lastResponse = null;

        while ($iteration < $maxIterations) {
            $iteration++;

            Log::info('Processing AI request iteration', [
                'iteration' => $iteration,
                'max_iterations' => $maxIterations,
                'user_id' => $user->id,
            ]);

            // В новом API /responses каждый запрос должен быть независимым
            // Убираем previous_response_id и conversation_id для избежания ошибок с tool outputs
            $cleanRequest = new ResponseRequestDto(
                model: $currentRequest->model,
                input: $currentRequest->input,
                instructions: $currentRequest->instructions,
                conversationId: null, // Временно убираем conversation для tool calls
                previousResponseId: null, // Убираем связь с предыдущим response
                maxOutputTokens: $currentRequest->maxOutputTokens,
                temperature: $currentRequest->temperature,
                tools: $currentRequest->tools,
                toolChoice: $currentRequest->toolChoice,
                metadata: $currentRequest->metadata,
                store: $currentRequest->store,
                stream: $currentRequest->stream,
            );

            $response = $this->openAIService->createResponse($cleanRequest, $user);
            $lastResponse = $response;

            if (! $response->hasFunctionCalls()) {
                // Нет вызовов функций - возвращаем финальный ответ
                Log::info('No function calls in response, returning final answer', [
                    'iteration' => $iteration,
                    'user_id' => $user->id,
                    'response_content' => $response->getContent(),
                ]);

                $content = $response->getContent() ?: 'Задача обработана.';

                return $this->parseAIResponse($content);
            }

            // Есть вызовы функций - обрабатываем их
            $functionCalls = $response->getFunctionCalls();
            Log::info('Function calls detected', [
                'iteration' => $iteration,
                'function_calls_count' => count($functionCalls),
                'function_calls' => $functionCalls,
            ]);

            $toolOutputs = $this->executeFunctionCalls($functionCalls);

            if ($toolOutputs === []) {
                // Функции не выполнились - возвращаем то что есть
                Log::warning('Function calls failed to execute', [
                    'iteration' => $iteration,
                    'user_id' => $user->id,
                ]);

                return $response->getContent() ?: 'Произошла ошибка при выполнении функций.';
            }

            Log::info('Tool outputs processed successfully', [
                'iteration' => $iteration,
                'conversation_id' => $user->conversation_id,
                'response_id' => $response->id,
                'tool_outputs_count' => count($toolOutputs),
            ]);

            // Проверяем, были ли успешно выполнены tool calls для добавления в Google Sheets
            $hasSuccessfulSheetsTool = false;
            foreach ($functionCalls as $functionCall) {
                if ($functionCall['name'] === 'add_row_to_sheets') {
                    // Находим соответствующий tool output
                    foreach ($toolOutputs as $toolOutput) {
                        if ($toolOutput['tool_call_id'] === ($functionCall['id'] ?? $functionCall['call_id'] ?? '')) {
                            $output = json_decode((string) $toolOutput['output'], true);
                            if ($output['success']) {
                                $hasSuccessfulSheetsTool = true;
                                break 2;
                            }
                        }
                    }
                }
            }

            // Если успешно добавили запись в Google Sheets, сбрасываем conversation_id для избежания конфликтов
            if ($hasSuccessfulSheetsTool) {
                $user->update(['conversation_id' => null]);
                Log::info('Conversation ID reset after successful Google Sheets operation', [
                    'user_id' => $user->id,
                ]);

                // Возвращаем простое сообщение без деталей
                return 'Задача обработана';
            }

            $content = $response->getContent() ?: 'Задача обработана.';

            return $this->parseAIResponse($content);
        }

        Log::warning('Reached maximum iterations without final response', [
            'max_iterations' => $maxIterations,
            'user_id' => $user->id,
            'last_response_content' => $lastResponse?->getContent(),
        ]);

        $content = $lastResponse?->getContent() ?: 'Превышено максимальное количество итераций. Задача может быть не полностью обработана.';

        return $this->parseAIResponse($content);
    }

    private function executeFunctionCalls(array $functionCalls): array
    {
        $toolOutputs = [];

        foreach ($functionCalls as $toolCall) {
            $callId = $toolCall['id'] ?? $toolCall['call_id'] ?? uniqid();
            $functionName = $toolCall['name'] ?? 'unknown';

            Log::info('Executing function call', [
                'tool_call_structure' => $toolCall,
                'extracted_call_id' => $callId,
                'function_name' => $functionName,
            ]);

            if ($toolCall['type'] === 'function_call' &&
                $toolCall['name'] === 'add_row_to_sheets') {

                $arguments = $toolCall['arguments'];

                // Если arguments это строка, декодируем JSON
                if (is_string($arguments)) {
                    $arguments = json_decode($arguments, true);
                }

                Log::info('Executing tool handler with arguments', [
                    'call_id' => $callId,
                    'arguments' => $arguments,
                ]);

                $result = $this->toolHandler->handle($arguments);

                Log::info('Function call executed', [
                    'call_id' => $callId,
                    'success' => $result['success'],
                    'result' => $result,
                ]);

                // Формируем правильный tool output согласно OpenAI API
                $toolOutput = [
                    'tool_call_id' => $callId,
                    'output' => json_encode($result),
                ];

                Log::info('Tool output prepared', [
                    'tool_output' => $toolOutput,
                ]);

                $toolOutputs[] = $toolOutput;
            } else {
                Log::warning('Unsupported function call', [
                    'tool_call' => $toolCall,
                ]);
            }
        }

        Log::info('All function calls executed', [
            'total_outputs' => count($toolOutputs),
            'tool_outputs' => $toolOutputs,
        ]);

        return $toolOutputs;
    }

    private function parseAIResponse(string $response): string
    {
        // Убираем markdown блоки кода, если они есть
        $cleanResponse = preg_replace('/^```json\s*|\s*```$/m', '', trim($response));

        // Пытаемся распарсить ответ как JSON
        $decoded = json_decode(trim($cleanResponse), true);

        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['content'])) {
            Log::info('AI response parsed as JSON', [
                'has_need_confirm' => isset($decoded['need_confirm']),
                'need_confirm' => $decoded['need_confirm'] ?? false,
                'content_length' => strlen((string) $decoded['content']),
            ]);

            // Возвращаем контент, а информацию о need_confirm передаем через специальный формат
            if (isset($decoded['need_confirm']) && $decoded['need_confirm'] === true) {
                return $decoded['content']."\n<!-- NEED_CONFIRM -->";
            }

            return $decoded['content'];
        }

        Log::info('AI response is not JSON, returning as-is', [
            'response_length' => strlen($response),
            'json_error' => json_last_error_msg(),
        ]);

        // Если не JSON или нет поля content, возвращаем как есть
        return $response;
    }
}
