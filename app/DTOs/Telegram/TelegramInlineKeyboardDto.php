<?php

declare(strict_types=1);

namespace App\DTOs\Telegram;

final readonly class TelegramInlineKeyboardDto
{
    /**
     * @param array<array<TelegramInlineKeyboardButtonDto>> $keyboard
     */
    public function __construct(
        public array $keyboard,
    ) {}

    public function toArray(): array
    {
        $keyboardArray = [];
        
        foreach ($this->keyboard as $row) {
            $rowArray = [];
            foreach ($row as $button) {
                $rowArray[] = $button->toArray();
            }
            $keyboardArray[] = $rowArray;
        }

        return [
            'inline_keyboard' => $keyboardArray,
        ];
    }
}