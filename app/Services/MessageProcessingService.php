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
        private readonly AddRowToSheetsToolHandler $toolHandler
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
            if (! empty($fileLinks)) {
                $fileInfo = "\n\nПрикрепленные файлы:\n";
                foreach ($fileLinks as $index => $fileLink) {
                    $fileInfo .= ($index + 1).'. '.$fileLink."\n";
                }
            }

            // Получаем список исполнителей из конфига
            $executors = config('project.executors', []);
            $executorsList = '';
            foreach ($executors as $executor) {
                $executorsList .= "• {$executor['short_code']} - {$executor['name']}";
                if ($executor['tg_username']) {
                    $executorsList .= " {$executor['tg_username']}";
                }
                $executorsList .= "\n";
            }

            // Получаем промпт из XML файла
            $systemPrompt = $this->promptService->loadPrompt('task_creation', [
                'current_date' => now()->format('Y-m-d'),
                'user_timezone' => 'Europe/Moscow',
                'user_message' => $messageText.$fileInfo,
                'file_links' => $fileLinks,
                'executors_list' => trim($executorsList),
            ]);

            // Подготавливаем запрос к AI с инструментами
            $requestDto = new ResponseRequestDto(
                model: 'gpt-4o',
                input: $messageText,
                instructions: $systemPrompt,
                tools: [AddRowToSheetsToolDefinition::getDefinition()],
                toolChoice: 'auto',
                maxOutputTokens: 4000,
                temperature: 0.3,
            );

            $response = $this->openAIService->createResponse($requestDto, $user);

            // Обрабатываем ответ и выполняем вызовы инструментов
            $finalResponse = $this->handleAIResponse($response);

            Log::info('Message processed successfully', [
                'user_id' => $user->id,
                'response_length' => strlen($finalResponse),
                'tool_calls_count' => count($response->getFunctionCalls()),
            ]);

            return $finalResponse;

        } catch (\Throwable $e) {
            Log::error('Failed to process message', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 'Произошла ошибка при обработке сообщения. Попробуйте еще раз.';
        }
    }

    private function handleAIResponse($response): string
    {
        $content = $response->getContent();
        $toolCalls = $response->getFunctionCalls();

        // Если есть вызовы инструментов, обрабатываем их
        if (! empty($toolCalls)) {
            foreach ($toolCalls as $toolCall) {
                // Проверяем тип вызова функции и имя функции
                if ($toolCall['type'] === 'function_call' &&
                    isset($toolCall['function']['name']) &&
                    $toolCall['function']['name'] === 'add_row_to_sheets') {

                    $arguments = json_decode((string) $toolCall['function']['arguments'], true);
                    $result = $this->toolHandler->handle($arguments);

                    Log::info('Tool call executed', [
                        'tool_name' => $toolCall['function']['name'],
                        'success' => $result['success'],
                        'arguments' => $arguments,
                    ]);

                    // Добавляем информацию о результате выполнения инструмента
                    if ($result['success']) {
                        $content .= "\n\n✅ ".$result['message'];
                    } else {
                        $content .= "\n\n❌ Ошибка при добавлении в таблицу: ".$result['error'];
                    }
                }
            }
        }

        return $content;
    }
}
