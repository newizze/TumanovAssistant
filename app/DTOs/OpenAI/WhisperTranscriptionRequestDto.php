<?php

declare(strict_types=1);

namespace App\DTOs\OpenAI;

final readonly class WhisperTranscriptionRequestDto
{
    public function __construct(
        public string $filePath,
        public string $model = 'whisper-1',
        public ?string $language = null,
        public ?string $prompt = null,
        public string $responseFormat = 'json',
        public float $temperature = 0.0,
    ) {}

    public function toMultipartArray(): array
    {
        $data = [
            'model' => $this->model,
            'response_format' => $this->responseFormat,
            'temperature' => $this->temperature,
        ];

        if ($this->language !== null) {
            $data['language'] = $this->language;
        }

        if ($this->prompt !== null) {
            $data['prompt'] = $this->prompt;
        }

        return $data;
    }
}
