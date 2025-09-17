<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Готовит AI-текст к Telegram MarkdownV2:
 *  - нормализует маркдаун ИИ ( ** → * , ~~ → ~ )
 *  - защищает code/links/спойлеры/подчёркивание и т.д.
 *  - экранирует все требуемые символы по правилам MarkdownV2
 *  - аккуратно обрезает до 4096 символов
 *
 * Отправляй с parse_mode=MarkdownV2.
 */
final class MarkdownService
{
    private const TG_LIMIT = 4096;

    // набор экранируемых символов "во всех остальных местах" (см. оф. доку TG)
    private const ESCAPE_SET = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];

    public function prepareForTelegram(string $text): string
    {
        $text = $this->normalizeAiMarkdown($text);
        $text = $this->toTelegramMarkdownV2($text);
        return $this->truncateSafe($text, self::TG_LIMIT);
    }

    /**
     * Нормализация распространённого GitHub-MD от ИИ к синтаксису MarkdownV2.
     */
    private function normalizeAiMarkdown(string $t): string
    {
        // **bold** -> *bold*
        $t = preg_replace_callback('/\*\*(.+?)\*\*/s', fn($m) => '*' . $m[1] . '*', $t);

        // ~~strike~~ -> ~strike~
        $t = preg_replace('/~~(.+?)~~/s', '~$1~', $t);

        // заголовки (#, ## ...) телега не рендерит — просто экранируем при общем экранировании
        // списки: заменим "- " в начале строки на "• " чтобы не городить экранирование
        $t = preg_replace('/^(?:\s*[-*]\s+)/m', '• ', $t);

        return $t;
    }

    /**
     * Главный конвертер в корректный MarkdownV2.
     */
    private function toTelegramMarkdownV2(string $t): string
    {
        $place = []; // плейсхолдеры => оригинал
        $i = 0;

        $makePh = function(string $orig) use (&$place, &$i): string {
            $key = "\x1A".(++$i)."\x1A"; // маловероятный токен
            $place[$key] = $orig;
            return $key;
        };

        // 1) защитим тройные код-блоки ```...```
        $t = preg_replace_callback('/```(.*?)```/s', function($m) use ($makePh) {
            $code = preg_replace('/([\\\\`])/', '\\\\$1', $m[1]); // в code/pre экранируем только \ и `
            return $makePh("```{$code}```");
        }, $t);

        // 2) защитим инлайн-код `...`
        $t = preg_replace_callback('/`([^`]+)`/', function($m) use ($makePh) {
            $code = preg_replace('/([\\\\`])/', '\\\\$1', $m[1]);
            return $makePh("`{$code}`");
        }, $t);

        // 3) защитим ссылки [text](url) с правильным экранированием в скобках
        $t = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/s', function($m) use ($makePh) {
            $text = $this->escapeAll($m[1]);      // внутри "текста" — общий набор
            $url  = str_replace(['\\', ')'], ['\\\\', '\)'], $m[2]); // внутри (...) только ) и \
            return $makePh("[{$text}]({$url})");
        }, $t);

        // 4) защитим спойлеры ||...||
        $t = preg_replace_callback('/\|\|(.+?)\|\|/s', function($m) use ($makePh) {
            $inner = $this->escapeAll($m[1]);
            return $makePh("||{$inner}||");
        }, $t);

        // 5) защитим подчёркивание __...__ (underline)
        $t = preg_replace_callback('/__([^_]+)__/', function($m) use ($makePh) {
            $inner = $this->escapeAll($m[1]);
            return $makePh("__{$inner}__");
        }, $t);

        // 6) защитим курсив _..._
        $t = preg_replace_callback('/_(?!_)([^_\n]+)_(?!_)/', function($m) use ($makePh) {
            $inner = $this->escapeAll($m[1]);
            return $makePh("_{$inner}_");
        }, $t);

        // 7) защитим жирный *...*
        $t = preg_replace_callback('/(?<!\*)\*([^*\n]+)\*(?!\*)/', function($m) use ($makePh) {
            $inner = $this->escapeAll($m[1]);
            return $makePh("*{$inner}*");
        }, $t);

        // 8) защитим зачёркнутый ~...~
        $t = preg_replace_callback('/~([^~\n]+)~/', function($m) use ($makePh) {
            $inner = $this->escapeAll($m[1]);
            return $makePh("~{$inner}~");
        }, $t);

        // 9) защитим блок-цитаты в начале строк: '>' (оставим как разметку, не экранируем)
        $t = preg_replace_callback('/^(\s*>+)/m', function($m) use ($makePh) {
            return $makePh($m[1]);
        }, $t);

        // 10) экранируем всё остальное по правилам MarkdownV2
        $t = $this->escapeAll($t);

        // 11) вернём плейсхолдеры
        if (!empty($place)) {
            $t = strtr($t, $place);
        }

        // 12) подчистим одинокие звёздочки/подчёркивания (если остались) — экранируем
        $t = $this->escapeDanglingDelimiters($t);

        return $t;
    }

    private function escapeAll(string $t): string
    {
        // Особый случай: '>' как маркер цитаты мы уже защитили плейсхолдером, остальные '>' экранируем
        $from = self::ESCAPE_SET;
        $to   = array_map(fn($c) => '\\' . $c, self::ESCAPE_SET);
        return str_replace($from, $to, $t);
    }

    private function escapeDanglingDelimiters(string $t): string
    {
        // Подсчитываем парные символы и экранируем непарные
        $t = $this->fixUnpairedDelimiters($t, '*');
        $t = $this->fixUnpairedDelimiters($t, '_');
        $t = $this->fixUnpairedDelimiters($t, '~');
        return $t;
    }

    private function fixUnpairedDelimiters(string $text, string $delimiter): string
    {
        // Найдем все вхождения delimiter, исключая уже экранированные
        $pattern = '/(?<!\\\\)\\' . preg_quote($delimiter, '/') . '/';
        preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
        
        $count = count($matches[0]);
        
        // Если нечетное количество - экранируем последний
        if ($count % 2 !== 0) {
            $lastMatch = end($matches[0]);
            $offset = $lastMatch[1];
            $text = substr_replace($text, '\\' . $delimiter, $offset, 1);
        }
        
        return $text;
    }

    private function truncateSafe(string $t, int $max): string
    {
        if (mb_strlen($t, 'UTF-8') <= $max) return $t;

        $cut = mb_substr($t, 0, $max - 1, 'UTF-8'); // оставим место под "…"
        // не заканчиваемся на обратном слэше
        while (mb_substr($cut, -1, 1, 'UTF-8') === '\\') {
            $cut = mb_substr($cut, 0, mb_strlen($cut, 'UTF-8') - 1, 'UTF-8');
        }
        return $cut . '…';
    }
}
