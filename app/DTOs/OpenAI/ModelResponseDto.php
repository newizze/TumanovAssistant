<?php

declare(strict_types=1);

namespace App\DTOs\OpenAI;

final readonly class ModelResponseDto
{
    public function __construct(
        public string $id,
        public string $object,
        public int $createdAt,
        public string $status,
        public ?array $error,
        public ?array $incompleteDetails,
        public ?string $instructions,
        public ?int $maxOutputTokens,
        public string $model,
        public array $output,
        public bool $parallelToolCalls,
        public ?string $previousResponseId,
        public ?array $reasoning,
        public bool $store,
        public float $temperature,
        public array $text,
        public string $toolChoice,
        public array $tools,
        public float $topP,
        public string $truncation,
        public ?array $usage,
        public ?string $user,
        public ?array $metadata,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            object: $data['object'],
            createdAt: $data['created_at'],
            status: $data['status'],
            error: $data['error'],
            incompleteDetails: $data['incomplete_details'],
            instructions: $data['instructions'],
            maxOutputTokens: $data['max_output_tokens'],
            model: $data['model'],
            output: $data['output'],
            parallelToolCalls: $data['parallel_tool_calls'],
            previousResponseId: $data['previous_response_id'],
            reasoning: $data['reasoning'],
            store: $data['store'],
            temperature: $data['temperature'],
            text: $data['text'],
            toolChoice: $data['tool_choice'],
            tools: $data['tools'],
            topP: $data['top_p'],
            truncation: $data['truncation'],
            usage: $data['usage'],
            user: $data['user'],
            metadata: $data['metadata'],
        );
    }

    public function getContent(): ?string
    {
        if ($this->output === []) {
            return null;
        }

        foreach ($this->output as $outputItem) {
            if ($outputItem['type'] === 'message' && isset($outputItem['content'])) {
                foreach ($outputItem['content'] as $contentItem) {
                    if ($contentItem['type'] === 'output_text') {
                        return $contentItem['text'];
                    }
                }
            }
        }

        return null;
    }

    public function hasFunctionCalls(): bool
    {
        if ($this->output === []) {
            return false;
        }

        foreach ($this->output as $outputItem) {
            if ($outputItem['type'] === 'function_call') {
                return true;
            }
        }

        return false;
    }

    public function getFunctionCalls(): array
    {
        if ($this->output === []) {
            return [];
        }

        $functionCalls = [];
        foreach ($this->output as $outputItem) {
            if ($outputItem['type'] === 'function_call') {
                $functionCalls[] = $outputItem;
            }
        }

        return $functionCalls;
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function getError(): ?array
    {
        return $this->error;
    }

    public function getUsage(): ?array
    {
        return $this->usage;
    }

    public function getTotalTokens(): ?int
    {
        return $this->usage['total_tokens'] ?? null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'object' => $this->object,
            'created_at' => $this->createdAt,
            'status' => $this->status,
            'error' => $this->error,
            'incomplete_details' => $this->incompleteDetails,
            'instructions' => $this->instructions,
            'max_output_tokens' => $this->maxOutputTokens,
            'model' => $this->model,
            'output' => $this->output,
            'parallel_tool_calls' => $this->parallelToolCalls,
            'previous_response_id' => $this->previousResponseId,
            'reasoning' => $this->reasoning,
            'store' => $this->store,
            'temperature' => $this->temperature,
            'text' => $this->text,
            'tool_choice' => $this->toolChoice,
            'tools' => $this->tools,
            'top_p' => $this->topP,
            'truncation' => $this->truncation,
            'usage' => $this->usage,
            'user' => $this->user,
            'metadata' => $this->metadata,
        ];
    }
}
