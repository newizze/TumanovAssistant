# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**TumanovAssistant** - Telegram bot for task management automation using Laravel, AI, and Google Sheets.

Core functionality:
- Accepts voice and text messages via Telegram Bot API
- Structures messages into tasks using GPT-4.1
- Transcribes voice messages using Whisper
- Clarifies details through AI interaction
- Automatically saves tasks to Google Sheets (as database)
- AI prompts stored in XML files with variable substitution

## Tech Stack

- **Backend**: Laravel 12 (PHP 8.2+)
- **APIs**: Telegram Bot API, OpenAI API (GPT-4o + Whisper), Google Sheets API
- **Queue**: Database-backed queues
- **Monitoring**: Laravel Telescope
- **Quality Tools**: PHPStan (level 10), Laravel Pint, Rector

## Development Commands

### Environment Setup
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### Development Server
```bash
composer run dev    # Runs server, queue, logs, and vite concurrently
# OR individually:
php artisan serve
php artisan queue:listen --tries=1
php artisan pail --timeout=0
```

### Testing
```bash
composer run test                                    # Run full test suite with config clear
php artisan test                                     # Run all tests
php artisan test tests/Feature/ExampleTest.php      # Run specific test file
php artisan test --filter=testName                  # Run specific test method
```

### Code Quality
```bash
vendor/bin/pint --dirty                             # Format code (run before committing)
vendor/bin/phpstan analyse                          # Static analysis (level 10)
vendor/bin/rector --dry-run                         # Preview code modernization
vendor/bin/rector                                   # Apply code modernization
```

## Architecture & Code Organization

### Required Patterns (MANDATORY)
- **DTOs**: All data transfer objects must use DTOs
- **Action Classes**: Business logic encapsulated in Action classes
- **Repositories**: Data access layer abstraction
- **Services**: Complex business logic coordination
- **Jobs**: Background/queued operations (implement ShouldQueue)
- **Events**: Domain events for AI responses and system events
- **Thin Controllers**: Controllers only handle HTTP concerns

### Laravel 12 Structure
- `bootstrap/app.php`: Middleware, exceptions, routing configuration
- `bootstrap/providers.php`: Application-specific service providers
- `routes/console.php`: Console command definitions
- Commands auto-register from `app/Console/Commands/`
- No `app/Http/Middleware/` or `app/Console/Kernel.php`

### Key Files & Directories
- `credentials.json`: Google API credentials
- `app/Models/`: Eloquent models with proper relationships
- `database/migrations/`: Database schema definitions
- `config/`: All environment-dependent configuration
- **XML prompt files**: AI prompts with variable substitution

### Database
- Primary: MySQL
- Queue backend: Database
- Session storage: Database
- Cache: Database
- Testing: SQLite in-memory

### Code Quality Requirements
- **PSR-12**: Code style compliance
- **PHPStan Level 10**: Strict static analysis
- **Full test coverage**: Unit and Feature tests mandatory
- **Proper logging**: All operations must be logged
- **Exception handling**: Comprehensive error handling
- **Documentation**: All functionality must be documented

## Google Sheets Integration

The application uses Google Sheets as the primary database through the Google Sheets API. Credentials are stored in `/credentials.json`.

## AI Integration

- **OpenAI GPT-4o**: Message structuring and task creation
- **Whisper**: Voice message transcription
- **Prompt Management**: XML files with variable substitution for easy modification
- **Context Handling**: AI maintains conversation context for clarifications

## HTTP Service Integration

**HttpService** is the unified client for all external API communications with built-in reliability features.

### Core Features
- **Retry Logic**: 3 configurable attempts on connection failures only
- **Bearer Authentication**: Automatic Authorization header handling
- **Comprehensive Logging**: All requests, retries, and failures logged
- **Type Safety**: DTOs for requests and responses

### Configuration
- Settings in `config/project.php`: retry attempts, delays, timeouts
- Default headers and user agent configuration
- Environment variables: `HTTP_RETRY_ATTEMPTS`, `HTTP_RETRY_DELAY`

### Usage Pattern
```php
$request = new HttpRequestDto(
    method: 'POST',
    url: 'https://api.example.com/endpoint',
    data: ['key' => 'value'],
    bearerToken: config('services.api.token')
);

$response = $httpService->request($request);

if ($response->isOk()) {
    $data = $response->getJsonData();
}
```

### API Integrations
- **Telegram Bot API**: Message sending, webhook handling
- **OpenAI API**: GPT-4o completions, Whisper transcription
- **Google Sheets API**: Task data persistence

### Error Handling
- No exceptions thrown - check `$response->hasError()` or `$response->isOk()`
- Retry only on `ConnectionException` - not on 4xx/5xx HTTP errors
- Detailed error information in `HttpResponseDto`

## Testing Strategy

- **PHPUnit 11**: Primary testing framework
- **Feature Tests**: End-to-end workflow testing (preferred)
- **Unit Tests**: Individual component testing
- **Factory Usage**: Use model factories for test data
- **In-Memory SQLite**: Fast test database
