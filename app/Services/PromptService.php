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
        $filePath = base_path(self::PROMPTS_PATH . "/{$promptName}.xml");

        if (!File::exists($filePath)) {
            Log::error('Prompt file not found', ['file' => $filePath]);
            throw new Exception("Prompt file '{$promptName}.xml' not found");
        }

        try {
            $xmlContent = File::get($filePath);
            $xml = new SimpleXMLElement($xmlContent);
            
            $prompt = $this->buildPromptFromXml($xml);
            
            return $this->substituteVariables($prompt, $variables);
        } catch (Exception $e) {
            Log::error('Failed to load prompt', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            throw new Exception("Failed to load prompt '{$promptName}': " . $e->getMessage());
        }
    }

    private function buildPromptFromXml(SimpleXMLElement $xml): string
    {
        $parts = [];

        if (isset($xml->system)) {
            $parts[] = "System: " . trim((string) $xml->system);
        }

        if (isset($xml->instruction)) {
            $parts[] = "Instruction: " . trim((string) $xml->instruction);
        }

        return implode("\n\n", $parts);
    }

    private function substituteVariables(string $prompt, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $placeholder = "{{" . $key . "}}";
            $prompt = str_replace($placeholder, (string) $value, $prompt);
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
                'variables' => $missingVariables
            ]);
        }
    }

    public function getAvailablePrompts(): array
    {
        $promptsPath = base_path(self::PROMPTS_PATH);
        
        if (!File::isDirectory($promptsPath)) {
            return [];
        }

        $files = File::glob($promptsPath . '/*.xml');
        
        return array_map(function ($file) {
            return pathinfo($file, PATHINFO_FILENAME);
        }, $files);
    }
}