<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class PromptService
{
    private const PROMPTS_PATH = 'resources/prompts';

    public function loadPrompt(string $promptName, array $variables = []): string
    {
        $filePath = base_path(self::PROMPTS_PATH."/{$promptName}.xml");

        if (! File::exists($filePath)) {
            Log::error('Prompt file not found', ['file' => $filePath]);
            throw new Exception("Prompt file '{$promptName}.xml' not found");
        }

        try {
            $xmlContent = File::get($filePath);

            // Сначала подставляем переменные в сырой XML
            $xmlContentWithVariables = $this->substituteVariables($xmlContent, $variables);

            // Парсим XML для валидации
            $xml = new SimpleXMLElement($xmlContentWithVariables);

            // Возвращаем XML строку с подставленными переменными
            return $xmlContentWithVariables;
        } catch (Exception $e) {
            Log::error('Failed to load prompt', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);
            throw new Exception("Failed to load prompt '{$promptName}': ".$e->getMessage(), $e->getCode(), $e);
        }
    }

    private function substituteVariables(string $prompt, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $placeholder = '{{'.$key.'}}';
            // Экранируем специальные символы XML
            $escapedValue = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $prompt = str_replace($placeholder, $escapedValue, $prompt);
        }

        $this->validateSubstitution($prompt);

        return $prompt;
    }

    private function validateSubstitution(string $prompt): void
    {
        if (preg_match('/\{\{.*?\}\}/', $prompt)) {
            preg_match_all('/\{\{(.*?)\}\}/', $prompt, $matches);
            $missingVariables = $matches[1];

            Log::warning('Unsubstituted variables found in prompt', [
                'variables' => $missingVariables,
            ]);
        }
    }

    public function getAvailablePrompts(): array
    {
        $promptsPath = base_path(self::PROMPTS_PATH);

        if (! File::isDirectory($promptsPath)) {
            return [];
        }

        $files = File::glob($promptsPath.'/*.xml');

        return array_map(fn ($file): string => pathinfo((string) $file, PATHINFO_FILENAME), $files);
    }
}
