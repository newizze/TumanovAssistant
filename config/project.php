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
];