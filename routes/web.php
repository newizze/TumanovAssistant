<?php

use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

Route::post('/telegram/webhook', [TelegramController::class, 'webhook'])
    ->name('telegram.webhook');
