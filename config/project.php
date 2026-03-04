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
        'default_range' => env('GOOGLE_SHEETS_DEFAULT_RANGE', 'A:Z'),
        'credentials_path' => base_path('credentials.json'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tickets App Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tickets-app API integration. The bot creates
    | tickets via the tickets-app REST API instead of Google Sheets.
    |
    */
    'tickets_app' => [
        'base_url' => env('TICKETS_APP_BASE_URL', 'http://localhost:8000'),
        'api_key' => env('TICKETS_APP_API_KEY'),
    ],

];
