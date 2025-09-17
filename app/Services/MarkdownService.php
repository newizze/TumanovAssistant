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
        // Fix AI-generated markdown for Telegram compatibility
        $text = $this->fixAIMarkdownForTelegram($text);
        
        // Truncate if too long
        $text = $this->truncateForTelegram($text);
        
        return $text;
    }

    /**
     * Fixes AI-generated markdown to be compatible with Telegram
     */
    private function fixAIMarkdownForTelegram(string $text): string
    {
        // Step 1: Fix double asterisks (**text**) to single (*text*) for Telegram
        $text = preg_replace('/\*\*(.*?)\*\*/', '*$1*', $text);
        
        // Step 2: Ensure all asterisks are properly paired
        $text = $this->fixUnpairedAsterisks($text);
        
        // Step 3: Escape characters that need escaping but preserve intentional markdown
        $text = $this->escapeSpecialCharacters($text);
        
        // Step 4: Fix common markdown issues
        $text = $this->fixCommonMarkdownIssues($text);
        
        return $text;
    }

    /**
     * Fixes unpaired asterisks that can break Telegram parsing
     */
    private function fixUnpairedAsterisks(string $text): string
    {
        // Find all asterisks and their positions
        preg_match_all('/\*/', $text, $matches, PREG_OFFSET_CAPTURE);
        
        // If odd number of asterisks, escape the last one
        if (count($matches[0]) % 2 !== 0) {
            $lastAsteriskPos = end($matches[0])[1];
            $text = substr_replace($text, '\\*', $lastAsteriskPos, 1);
        }
        
        return $text;
    }

    /**
     * Escapes special characters but preserves intentional markdown
     */
    private function escapeSpecialCharacters(string $text): string
    {
        // Characters that need escaping in Telegram: _ \ ~ > # + - = | { } . !
        // But we need to be careful not to break intentional markdown
        
        // Protect markdown formatting first
        $protectedRanges = [];
        
        // Protect text between asterisks (bold/italic)
        preg_match_all('/\*([^*]+)\*/', $text, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $match) {
            $protectedRanges[] = [$match[1], $match[1] + strlen($match[0])];
        }
        
        // Protect code blocks and inline code
        preg_match_all('/`([^`]+)`/', $text, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $match) {
            $protectedRanges[] = [$match[1], $match[1] + strlen($match[0])];
        }
        
        // Escape special characters outside protected ranges
        $specialChars = ['_', '\\', '~', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        
        foreach ($specialChars as $char) {
            $text = $this->escapeCharacterOutsideRanges($text, $char, $protectedRanges);
        }
        
        return $text;
    }

    /**
     * Escape a character only outside protected ranges
     */
    private function escapeCharacterOutsideRanges(string $text, string $char, array $protectedRanges): string
    {
        $result = '';
        $length = strlen($text);
        
        for ($i = 0; $i < $length; $i++) {
            if ($text[$i] === $char) {
                // Check if this position is in a protected range
                $inProtectedRange = false;
                foreach ($protectedRanges as [$start, $end]) {
                    if ($i >= $start && $i < $end) {
                        $inProtectedRange = true;
                        break;
                    }
                }
                
                if (!$inProtectedRange) {
                    $result .= '\\' . $char;
                } else {
                    $result .= $char;
                }
            } else {
                $result .= $text[$i];
            }
        }
        
        return $result;
    }

    /**
     * Fixes common markdown issues that break Telegram parsing
     */
    private function fixCommonMarkdownIssues(string $text): string
    {
        // Fix lines starting with numbers followed by period (can be interpreted as ordered list)
        $text = preg_replace('/^(\d+)\. /m', '$1\\. ', $text);
        
        // Fix standalone asterisks that are not part of formatting
        $text = preg_replace('/(?<!\*)\*(?!\*)(?![^*]*\*)/', '\\*', $text);
        
        // Fix empty markdown formatting
        $text = str_replace('**', '', $text);
        $text = str_replace('__', '', $text);
        
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