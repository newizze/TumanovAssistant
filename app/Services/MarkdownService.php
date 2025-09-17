<?php

declare(strict_types=1);

namespace App\Services;

class MarkdownService
{
    /**
     * Converts text to Telegram-safe Markdown format
     */
    public function prepareForTelegram(string $text): string
    {
        // Escape problematic characters for Telegram Markdown
        $text = $this->escapeTelegramMarkdown($text);
        
        // Truncate if too long
        $text = $this->truncateForTelegram($text);
        
        return $text;
    }

    /**
     * Escapes characters that can cause issues in Telegram Markdown
     */
    private function escapeTelegramMarkdown(string $text): string
    {
        // Characters that need escaping in Telegram Markdown: _ \ ~ > # + - = | { } . !
        // But we need to be smart about it - don't escape markdown that ChatGPT intentionally used
        
        // First, protect existing markdown syntax
        $protectedAreas = [];
        
        // Protect code blocks ```
        $text = preg_replace_callback('/```[\s\S]*?```/', function($matches) use (&$protectedAreas) {
            $placeholder = '___PROTECTED_CODE_BLOCK_' . count($protectedAreas) . '___';
            $protectedAreas[$placeholder] = $matches[0];
            return $placeholder;
        }, $text);
        
        // Protect inline code `
        $text = preg_replace_callback('/`[^`]+`/', function($matches) use (&$protectedAreas) {
            $placeholder = '___PROTECTED_INLINE_CODE_' . count($protectedAreas) . '___';
            $protectedAreas[$placeholder] = $matches[0];
            return $placeholder;
        }, $text);
        
        // Protect bold **text**
        $text = preg_replace_callback('/\*\*[^*]+\*\*/', function($matches) use (&$protectedAreas) {
            $placeholder = '___PROTECTED_BOLD_' . count($protectedAreas) . '___';
            $protectedAreas[$placeholder] = $matches[0];
            return $placeholder;
        }, $text);
        
        // Protect italic *text*
        $text = preg_replace_callback('/\*[^*]+\*/', function($matches) use (&$protectedAreas) {
            $placeholder = '___PROTECTED_ITALIC_' . count($protectedAreas) . '___';
            $protectedAreas[$placeholder] = $matches[0];
            return $placeholder;
        }, $text);
        
        // Protect links [text](url)
        $text = preg_replace_callback('/\[[^\]]+\]\([^)]+\)/', function($matches) use (&$protectedAreas) {
            $placeholder = '___PROTECTED_LINK_' . count($protectedAreas) . '___';
            $protectedAreas[$placeholder] = $matches[0];
            return $placeholder;
        }, $text);
        
        // Now escape problematic characters in the remaining text
        $specialChars = ['_', '\\', '~', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        
        foreach ($specialChars as $char) {
            $text = str_replace($char, '\\' . $char, $text);
        }
        
        // Restore protected areas
        foreach ($protectedAreas as $placeholder => $original) {
            $text = str_replace($placeholder, $original, $text);
        }
        
        return $text;
    }

    /**
     * Safely truncates text to Telegram's message limit (4096 characters)
     */
    public function truncateForTelegram(string $text, int $maxLength = 4096): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }

        // Try to truncate at a word boundary
        $truncated = substr($text, 0, $maxLength - 3);
        $lastSpace = strrpos($truncated, ' ');
        
        if ($lastSpace !== false && $lastSpace > $maxLength * 0.8) {
            $truncated = substr($truncated, 0, $lastSpace);
        }
        
        return $truncated . '...';
    }
}