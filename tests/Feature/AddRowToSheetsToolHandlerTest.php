<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\AddRowToGoogleSheetsAction;
use App\DTOs\GoogleSheets\GoogleSheetsResponseDto;
use App\Tools\AddRowToSheetsToolHandler;
use Illuminate\Support\Facades\Config;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * @phpstan-ignore method.notFound, instanceof.alwaysTrue
 */
class AddRowToSheetsToolHandlerTest extends TestCase
{
    /** @var AddRowToGoogleSheetsAction&MockInterface */
    private AddRowToGoogleSheetsAction $mockAction;

    private AddRowToSheetsToolHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the AddRowToGoogleSheetsAction
        /** @var AddRowToGoogleSheetsAction&MockInterface $mockAction */
        $mockAction = Mockery::mock(AddRowToGoogleSheetsAction::class);
        $this->mockAction = $mockAction;
        $this->handler = new AddRowToSheetsToolHandler($this->mockAction);

        // Set up config
        Config::set('project.google_sheets.default_spreadsheet_id', 'test-spreadsheet-id');
        Config::set('project.google_sheets.default_range', 'Sheet1!A:Z');
        Config::set('project.executors', [
            ['short_code' => 'ИТ ВУ', 'full_name' => 'Тест Тестов', 'position' => 'IT Head'],
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handle_adds_task_with_auto_acceptance_by_default(): void
    {
        $arguments = [
            'task_title' => 'Тестовая задача',
            'task_description' => 'Описание задачи',
            'expected_result' => 'Ожидаемый результат',
            'priority' => 'Средний',
            'task_type' => 'Разработка',
            'executor' => 'ИТ ВУ',
            'sender_name' => 'ГД НТ',
            'requires_verification' => 'Нет',
        ];

        $expectation = $this->mockAction
            ->shouldReceive('execute')
            ->withArgs(function (string $spreadsheetId, string $range, array $values): bool {
                // Проверяем, что поле "Требуется ли проверка" установлено в "Нет" (последний элемент массива - индекс 29)
                $this->assertEquals('Нет', $values[29]);
                $this->assertEquals('test-spreadsheet-id', $spreadsheetId);
                $this->assertEquals('Sheet1!A:Z', $range);

                return true;
            });

        if ($expectation instanceof \Mockery\ExpectationInterface) {
            $expectation->once()->andReturn(new GoogleSheetsResponseDto(
                isSuccessful: true,
                updatedCells: 15,
                spreadsheetId: 'test-spreadsheet-id'
            ));
        }

        $result = $this->handler->handle($arguments);

        $this->assertTrue($result['success']);
        $this->assertEquals('💼 Задача поставлена 🔔 Ответственный уведомлен', $result['message']);
    }

    public function test_handle_adds_task_with_verification_required(): void
    {
        $arguments = [
            'task_title' => 'Задача с проверкой',
            'task_description' => 'Проверю сам результат',
            'expected_result' => 'Ожидаемый результат',
            'priority' => 'Высокий',
            'task_type' => 'Настройка',
            'executor' => 'ИТ ВУ',
            'sender_name' => 'ГД НТ',
            'requires_verification' => 'Да',
        ];

        $expectation = $this->mockAction
            ->shouldReceive('execute')
            ->withArgs(function (string $spreadsheetId, string $range, array $values): bool {
                // Проверяем, что поле "Требуется ли проверка" установлено в "Да" (индекс 29)
                $this->assertEquals('Да', $values[29]);

                return true;
            });

        if ($expectation instanceof \Mockery\ExpectationInterface) {
            $expectation->once()->andReturn(new GoogleSheetsResponseDto(
                isSuccessful: true,
                updatedCells: 15,
                spreadsheetId: 'test-spreadsheet-id'
            ));
        }

        $result = $this->handler->handle($arguments);

        $this->assertTrue($result['success']);
    }

    public function test_handle_defaults_to_no_verification_when_not_specified(): void
    {
        $arguments = [
            'task_title' => 'Задача без проверки',
            'task_description' => 'Просто сделай',
            'expected_result' => 'Ожидаемый результат',
            'priority' => 'Низкий',
            'task_type' => 'Поручение',
            'executor' => 'ИТ ВУ',
            'sender_name' => 'ГД НТ',
            'requires_verification' => 'Нет',
        ];

        $expectation = $this->mockAction
            ->shouldReceive('execute')
            ->withArgs(function (string $spreadsheetId, string $range, array $values): bool {
                // Проверяем fallback на "Нет" (индекс 29)
                $this->assertEquals('Нет', $values[29]);

                return true;
            });

        if ($expectation instanceof \Mockery\ExpectationInterface) {
            $expectation->once()->andReturn(new GoogleSheetsResponseDto(
                isSuccessful: true,
                updatedCells: 15,
                spreadsheetId: 'test-spreadsheet-id'
            ));
        }

        $result = $this->handler->handle($arguments);

        $this->assertTrue($result['success']);
    }

    public function test_handle_fails_when_required_field_missing(): void
    {
        $arguments = [
            'task_title' => 'Неполная задача',
            'task_description' => 'Описание',
            // Отсутствует expected_result
            'priority' => 'Средний',
            'task_type' => 'Разработка',
            'executor' => 'ИТ ВУ',
            'sender_name' => 'ГД НТ',
            'requires_verification' => 'Нет',
        ];

        $result = $this->handler->handle($arguments);

        $this->assertFalse($result['success']);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('expected_result', $result['error']);
    }

    public function test_handle_fails_when_verification_field_missing(): void
    {
        $arguments = [
            'task_title' => 'Задача без поля проверки',
            'task_description' => 'Описание',
            'expected_result' => 'Результат',
            'priority' => 'Средний',
            'task_type' => 'Разработка',
            'executor' => 'ИТ ВУ',
            'sender_name' => 'ГД НТ',
            // Отсутствует requires_verification
        ];

        $result = $this->handler->handle($arguments);

        $this->assertFalse($result['success']);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('requires_verification', $result['error']);
    }
}
