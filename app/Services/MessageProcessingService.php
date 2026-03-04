<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\OpenAI\ResponseRequestDto;
use App\Models\User;
use App\Tools\AddTicketToolDefinition;
use App\Tools\AddTicketToolHandler;
use Illuminate\Support\Facades\Log;

class MessageProcessingService
{
    /**
     * @var array<int, string>
     */
    private array $currentFileLinks = [];

    public function __construct(
        private readonly OpenAIResponseService $openAIService,
        private readonly PromptService $promptService,
        private readonly AddTicketToolHandler $toolHandler,
        private readonly ExecutorService $executorService
    ) {}

    /**
     * @param  array<int, string>  $fileLinks
     */
    public function processMessage(string $messageText, User $user, array $fileLinks = [], ?bool $requiresVerification = null): string
    {
        try {
            // Сохраняем файлы для последующего использования в ответе
            $this->currentFileLinks = $fileLinks;

            Log::info('Processing message with AI', [
                'user_id' => $user->id,
                'message_length' => strlen($messageText),
                'file_links_count' => count($fileLinks),
                'requires_verification' => $requiresVerification,
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

            // Получаем список исполнителей и справочники из tickets-app
            $executors = $this->executorService->getApprovedExecutors();
            $lookups = $this->executorService->getLookups();
            $priorities = $lookups['priorities'] ?? [];
            $taskTypes = $lookups['task_types'] ?? [];

            $executorsList = '';
            foreach ($executors as $executor) {
                /** @var int|string $execId */
                $execId = $executor['id'] ?? 0;
                /** @var string $execCode */
                $execCode = $executor['code'] ?? '';
                /** @var string $execFullName */
                $execFullName = $executor['full_name'] ?? '';
                /** @var string $execPosition */
                $execPosition = $executor['position'] ?? '';
                /** @var string $execDepartment */
                $execDepartment = $executor['department'] ?? '';
                /** @var string $execTg */
                $execTg = $executor['telegram_username'] ?? '';

                $executorsList .= "• ID:{$execId} Код: {$execCode} | ФИО: {$execFullName}";
                if ($execPosition !== '') {
                    $executorsList .= " | Должность: {$execPosition}";
                }
                if ($execDepartment !== '') {
                    $executorsList .= " | Отдел: {$execDepartment}";
                }
                if ($execTg !== '') {
                    $executorsList .= " | Telegram: {$execTg}";
                }
                $executorsList .= "\n";
            }

            $senderIdentifier = $this->determineSenderIdentifier($user, $executors);

            if (empty($senderIdentifier)) {
                throw new \RuntimeException('Failed to determine sender identifier for user: '.$user->id);
            }

            // Получаем определение инструмента для промпта
            $toolDefinitionArray = AddTicketToolDefinition::getDefinition(
                forcedSender: $senderIdentifier,
                executors: $executors,
                priorities: $priorities,
                taskTypes: $taskTypes,
            );
            $toolDefinition = json_encode($toolDefinitionArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';

            // Формируем список приоритетов и типов задач для промпта
            $prioritiesList = '';
            foreach ($priorities as $priority) {
                /** @var int|string $pId */
                $pId = $priority['id'] ?? 0;
                /** @var string $pName */
                $pName = $priority['name'] ?? '';
                $prioritiesList .= "• {$pId}:{$pName}\n";
            }

            $taskTypesList = '';
            foreach ($taskTypes as $taskType) {
                /** @var int|string $tId */
                $tId = $taskType['id'] ?? 0;
                /** @var string $tName */
                $tName = $taskType['name'] ?? '';
                $taskTypesList .= "• {$tId}:{$tName}\n";
            }

            // Получаем промпт из XML файла
            $systemPrompt = $this->promptService->loadPrompt('task_creation', [
                'current_date' => now()->format('Y-m-d'),
                'user_timezone' => 'Europe/Moscow',
                'user_message' => $messageText.$fileInfo,
                'executors_list' => trim($executorsList),
                'telegram_username' => $user->username ?: 'unknown',
                'sender_identifier' => $senderIdentifier,
                'tool_definition' => $toolDefinition,
                'priorities_list' => trim($prioritiesList),
                'task_types_list' => trim($taskTypesList),
            ]);

            // Подготавливаем запрос к AI с инструментами (включаем файлы)
            $fullMessage = $messageText.$fileInfo;
            $requestDto = new ResponseRequestDto(
                model: 'gpt-4.1',
                input: $fullMessage,
                instructions: $systemPrompt,
                tools: [$toolDefinitionArray],
            );

            // Выполняем цикличные запросы до получения финального ответа
            $finalResponse = $this->processWithToolCalls($requestDto, $user, $senderIdentifier, requiresVerification: $requiresVerification);

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
                    $toolCall['function']['name'] === 'create_ticket') {

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

                return $content ?: '💼 Задача поставлена 🔔 Ответственный уведомлен';
            }
        }

        return $content ?: 'Ответ получен, но содержимое отсутствует.';
    }

    private function processWithToolCalls(ResponseRequestDto $requestDto, User $user, ?string $senderIdentifier = null, int $maxIterations = 5, ?bool $requiresVerification = null): string
    {
        $currentRequest = $requestDto;
        $iteration = 0;
        $lastResponse = null;
        $resolvedSender = $senderIdentifier ?? $this->determineSenderIdentifier($user);

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

                $content = $response->getContent() ?: '💼 Задача поставлена 🔔 Ответственный уведомлен';

                return $this->parseAIResponse($content);
            }

            // Есть вызовы функций - обрабатываем их
            $functionCalls = $response->getFunctionCalls();
            Log::info('Function calls detected', [
                'iteration' => $iteration,
                'function_calls_count' => count($functionCalls),
                'function_calls' => $functionCalls,
            ]);

            $toolOutputs = $this->executeFunctionCalls($functionCalls, $user, $resolvedSender, $requiresVerification);

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

            // Проверяем, были ли успешно выполнены tool calls для создания тикета
            $hasSuccessfulTicketTool = false;
            foreach ($functionCalls as $functionCall) {
                if ($functionCall['name'] === 'create_ticket') {
                    foreach ($toolOutputs as $toolOutput) {
                        if ($toolOutput['tool_call_id'] === ($functionCall['id'] ?? $functionCall['call_id'] ?? '')) {
                            $output = json_decode((string) $toolOutput['output'], true);
                            if ($output['success']) {
                                $hasSuccessfulTicketTool = true;
                                break 2;
                            }
                        }
                    }
                }
            }

            // Если успешно создали тикет, сбрасываем conversation_id
            if ($hasSuccessfulTicketTool) {
                $user->update(['conversation_id' => null]);
                Log::info('Conversation ID reset after successful ticket creation', [
                    'user_id' => $user->id,
                ]);

                return '💼 Задача поставлена 🔔 Ответственный уведомлен';
            }

            $content = $response->getContent() ?: '💼 Задача поставлена 🔔 Ответственный уведомлен';

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

    private function executeFunctionCalls(array $functionCalls, User $user, string $senderIdentifier, ?bool $requiresVerification = null): array
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
                $toolCall['name'] === 'create_ticket') {

                $arguments = $toolCall['arguments'];

                // Если arguments это строка, декодируем JSON
                if (is_string($arguments)) {
                    $arguments = json_decode($arguments, true);
                }

                $originalSender = $arguments['sender_name'] ?? null;
                $arguments['sender_name'] = $senderIdentifier;

                // Переопределяем needs_review если указано через кнопку
                if ($requiresVerification !== null) {
                    $originalNeedsReview = $arguments['needs_review'] ?? null;
                    $arguments['needs_review'] = $requiresVerification ? 'Да' : 'Нет';

                    Log::info('Needs review overridden for tool call', [
                        'call_id' => $callId,
                        'original_needs_review' => $originalNeedsReview,
                        'resolved_needs_review' => $arguments['needs_review'],
                        'user_id' => $user->id,
                    ]);
                }

                if ($originalSender !== $senderIdentifier) {
                    Log::info('Sender overridden for tool call', [
                        'call_id' => $callId,
                        'original_sender' => $originalSender,
                        'resolved_sender' => $senderIdentifier,
                        'user_id' => $user->id,
                    ]);
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

    /**
     * Возвращает telegram username отправителя для идентификации автора в tickets-app
     *
     * @param  array<int, array<string, mixed>>|null  $executors
     */
    private function determineSenderIdentifier(User $user, ?array $executors = null): string
    {
        // Для tickets-app мы передаём telegram_username как идентификатор автора
        // tickets-app сам найдёт пользователя по telegram_username
        $username = $user->username ? '@'.ltrim($user->username, '@') : null;

        if ($username !== null) {
            Log::info('Sender identified by telegram username', [
                'user_id' => $user->id,
                'telegram_username' => $username,
            ]);

            return $username;
        }

        // Fallback: пробуем найти по executor code
        $executors ??= $this->executorService->getApprovedExecutors();
        $matchedExecutor = $this->executorService->findExecutorByTelegramUsername($user->username, $executors);

        if ($matchedExecutor !== null && ! empty($matchedExecutor['telegram_username'])) {
            return (string) $matchedExecutor['telegram_username'];
        }

        $fallback = $this->formatFallbackSenderName($user);

        Log::info('Sender fallback used', [
            'user_id' => $user->id,
            'telegram_username' => $user->username,
            'fallback_sender' => $fallback,
        ]);

        return $fallback;
    }

    private function formatFallbackSenderName(User $user): string
    {
        $name = trim((string) ($user->name ?? ''));
        $username = $user->username ? '@'.ltrim($user->username, '@') : null;

        if ($name !== '' && $username !== null) {
            return sprintf('%s (%s)', $name, $username);
        }

        if ($name !== '') {
            return $name;
        }

        if ($username !== null) {
            return $username;
        }

        return 'Неизвестный отправитель';
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

            $content = $decoded['content'];

            // Добавляем информацию о файлах только в черновик задачи (когда need_confirm = true)
            if ($this->currentFileLinks !== [] && isset($decoded['need_confirm']) && $decoded['need_confirm'] === true) {
                $fileSection = "\n\n📎 **Прикрепленные файлы** (".count($this->currentFileLinks)." шт.):\n";
                foreach ($this->currentFileLinks as $index => $fileLink) {
                    // Проверяем расширение файла для определения типа
                    $extension = strtolower(pathinfo($fileLink, PATHINFO_EXTENSION));
                    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

                    if (in_array($extension, $imageExtensions)) {
                        // Для изображений используем markdown синтаксис ![alt](url)
                        $fileSection .= "\n![Файл ".($index + 1).']('.$fileLink.")\n";
                    } else {
                        // Для других файлов просто ссылка
                        $fileSection .= ($index + 1).'. '.$fileLink."\n";
                    }
                }
                $content .= $fileSection;

                Log::info('Files added to draft task', [
                    'files_count' => count($this->currentFileLinks),
                ]);
            }

            // Возвращаем контент, а информацию о need_confirm передаем через специальный формат
            // need_confirm здесь означает что нужно показать кнопки подтверждения черновика
            if (isset($decoded['need_confirm']) && $decoded['need_confirm'] === true) {
                return $content."\n<!-- NEED_CONFIRM -->";
            }

            return $content;
        }

        Log::info('AI response is not JSON, returning as-is', [
            'response_length' => strlen($response),
            'json_error' => json_last_error_msg(),
        ]);

        // Если не JSON или нет поля content, возвращаем как есть
        return $response;
    }
}
