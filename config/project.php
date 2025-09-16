<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | HTTP Client Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for HTTP client behavior including retry logic and
    | timeout settings for external API communications.
    |
    */
    'http' => [
        'retry_attempts' => env('HTTP_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('HTTP_RETRY_DELAY', 1000), // milliseconds
        'timeout' => env('HTTP_TIMEOUT', 30), // seconds
        'connect_timeout' => env('HTTP_CONNECT_TIMEOUT', 10), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | API Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for API integrations including user agent and
    | common headers configuration.
    |
    */
    'api' => [
        'user_agent' => env('API_USER_AGENT', 'TumanovAssistant/1.0'),
        'default_headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Sheets Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Google Sheets integration including default
    | spreadsheet and range settings for task management.
    |
    */
    'google_sheets' => [
        'default_spreadsheet_id' => env('GOOGLE_SHEETS_SPREADSHEET_ID'),
        'default_range' => env('GOOGLE_SHEETS_DEFAULT_RANGE', 'Sheet1!A:Z'),
        'credentials_path' => base_path('credentials.json'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Task Management Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for task management including available executors
    | and their corresponding full names and Telegram handles.
    |
    */
    'executors' => [
        [
            'name' => 'Николай Туманов',
            'short_code' => 'ГД НТ',
            'tg_username' => null,
        ],
        [
            'name' => 'Матюшин Денис',
            'short_code' => 'ОД ДМ',
            'tg_username' => '@albega1',
        ],
        [
            'name' => 'Абрамов Дмитрий Юрьевич',
            'short_code' => 'РОП ДА',
            'tg_username' => '@pro_abramov',
        ],
        [
            'name' => 'Коротков И. В.',
            'short_code' => 'РОМ ИК',
            'tg_username' => '@i_krtkv',
        ],
        [
            'name' => 'Голубева Александра Алексеевна',
            'short_code' => 'АС ГД',
            'tg_username' => '@leksiru',
        ],
        [
            'name' => 'Туктарова Диана Ильшатовна',
            'short_code' => 'ФД ДТ',
            'tg_username' => '@withlove_diana',
        ],
        [
            'name' => 'Владислав Умнов IT',
            'short_code' => 'ИТ ВУ',
            'tg_username' => '@VladislavUmnov',
        ],
        [
            'name' => 'Виктор Жиленко IT',
            'short_code' => 'ИТ ВЖ',
            'tg_username' => null,
        ],
        [
            'name' => 'Анастасия IT',
            'short_code' => 'ИТ Анаст',
            'tg_username' => null,
        ],
        [
            'name' => 'Александр IT',
            'short_code' => 'ИТ Алекс',
            'tg_username' => null,
        ],
    ],
];
