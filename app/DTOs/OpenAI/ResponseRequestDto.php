<?php

declare(strict_types=1);

namespace App\DTOs\OpenAI;

final readonly class ResponseRequestDto
{
    public function __construct(
        public string $model,
        public string|array $input,
        public ?string $instructions = null,
        public ?string $conversationId = null,
        public ?string $previousResponseId = null,
        public ?int $maxOutputTokens = null,
        public ?float $temperature = null,
        public ?array $tools = null,
        public ?string $toolChoice = null,
        public ?array $metadata = null,
        public ?bool $store = null,
        public ?bool $stream = null,
    ) {}

    public function toArray(): array
    {
        $data = [
            'model' => $this->model,
        ];

        if (is_string($this->input)) {
            $data['input'] = $this->input;
        } elseif (is_array($this->input)) {
            $data['input'] = $this->input;
        }

        if ($this->instructions !== null) {
            $data['instructions'] = $this->instructions;
        }

        if ($this->conversationId !== null) {
            $data['conversation'] = $this->conversationId;
        }

        if ($this->previousResponseId !== null) {
            $data['previous_response_id'] = $this->previousResponseId;
        }

        if ($this->maxOutputTokens !== null) {
            $data['max_output_tokens'] = $this->maxOutputTokens;
        }

        if ($this->temperature !== null) {
            $data['temperature'] = $this->temperature;
        }

        if ($this->tools !== null) {
            $data['tools'] = $this->tools;
        }

        if ($this->toolChoice !== null) {
            $data['tool_choice'] = $this->toolChoice;
        }

        if ($this->metadata !== null) {
            $data['metadata'] = $this->metadata;
        }

        if ($this->store !== null) {
            $data['store'] = $this->store;
        }

        if ($this->stream !== null) {
            $data['stream'] = $this->stream;
        }

        return $data;
    }
}